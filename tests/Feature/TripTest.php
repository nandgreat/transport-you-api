<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Location;
use App\Models\Route;
use App\Models\Schedule;
use App\Models\SeatArrangementType;
use App\Models\State;
use App\Models\Status;
use App\Models\Tenant;
use App\Models\User;
use App\Models\VehicleCategory;
use App\Models\VehicleType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TripTest extends TestCase
{
    use RefreshDatabase;

    public function setUp(): void
    {
        parent::setUp();
        $this->artisan('passport:client', ['--personal' => true, '--no-interaction' => true, '--name' => 'Testing Client']);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function seatArrangement(int $seats): SeatArrangementType
    {
        return SeatArrangementType::firstOrCreate(
            ['name' => "Test {$seats} Seater"],
            ['number_of_seats' => $seats, 'status' => 'active']
        );
    }

    private function vehicleTypeWithSeats(VehicleCategory $category, int $seats): VehicleType
    {
        $vehicleType = VehicleType::firstOrCreate(
            ['vehicle_name' => 'Toyota Hiace', 'vehicle_category_id' => $category->id, 'year' => 2022],
            ['status' => 'active']
        );
        $vehicleType->seatArrangementTypes()->syncWithoutDetaching([
            $this->seatArrangement($seats)->id => ['is_default' => true],
        ]);

        return $vehicleType;
    }

    private function makeTrip(array $overrides = []): Schedule
    {
        $state  = State::firstOrCreate(['name' => 'Lagos'], ['code' => 'LA']);
        $status = Status::firstOrCreate(['name' => 'active']);

        $category    = VehicleCategory::firstOrCreate(['vehicle_category_name' => 'Bus']);
        $vehicleType = $this->vehicleTypeWithSeats($category, 30);

        $tenant = Tenant::create(['name' => 'Test Operator ' . uniqid()]);
        $origin = Location::create(['name' => 'Origin', 'city' => 'City A', 'state_id' => $state->id, 'status_id' => $status->id]);
        $dest   = Location::create(['name' => 'Dest',   'city' => 'City B', 'state_id' => $state->id, 'status_id' => $status->id]);
        $route   = Route::create(['tenant_id' => $tenant->id, 'origin_id' => $origin->id, 'destination_id' => $dest->id, 'distance' => 200, 'estimated_duration' => 180]);
        return Schedule::create(array_merge([
            'tenant_id'       => $tenant->id,
            'route_id'        => $route->id,
            'vehicle_type_id' => $vehicleType->id,
            'departure_time' => now()->addDay(),
            'arrival_time'   => now()->addDay()->addHours(3),
            'fare'           => 4500,
            'status'         => 'scheduled',
        ], $overrides));
    }

    // -------------------------------------------------------------------------
    // Public listing
    // -------------------------------------------------------------------------

    public function test_anyone_can_list_available_trips_without_auth()
    {
        $this->makeTrip();

        $response = $this->getJson('/api/v1/trips');

        $response->assertStatus(200)->assertJsonCount(1, 'data');
    }

    public function test_cancelled_and_past_trips_are_excluded()
    {
        $this->makeTrip(['status' => 'cancelled']);
        $this->makeTrip(['status' => 'completed']);
        $this->makeTrip(['departure_time' => now()->subHour(), 'status' => 'scheduled']);
        $this->makeTrip(); // only this one should appear

        $response = $this->getJson('/api/v1/trips');

        $response->assertStatus(200)->assertJsonCount(1, 'data');
    }

    public function test_trips_response_includes_route_vehicle_operator_and_available_seats()
    {
        $this->makeTrip();

        $response = $this->getJson('/api/v1/trips');

        $response->assertStatus(200)
                 ->assertJsonStructure(['data' => [['id', 'departure_time', 'arrival_time', 'fare', 'status', 'available_seats', 'route', 'vehicle_type', 'operator']]]);
    }

    public function test_available_seats_decrements_with_bookings()
    {
        $schedule = $this->makeTrip();
        $user     = User::factory()->create(['tenant_id' => $schedule->tenant_id, 'email_verified_at' => now()]);

        // Two bookings (seats 1 and 2) on a 30-seat vehicle
        Booking::create(['tenant_id' => $schedule->tenant_id, 'user_id' => $user->id, 'schedule_id' => $schedule->id, 'total_amount' => 4500, 'status' => 'confirmed', 'passengers' => [['name' => 'A', 'seat_number' => 1]]]);
        Booking::create(['tenant_id' => $schedule->tenant_id, 'user_id' => $user->id, 'schedule_id' => $schedule->id, 'total_amount' => 4500, 'status' => 'pending', 'passengers' => [['name' => 'B', 'seat_number' => 2]]]);

        $response = $this->getJson('/api/v1/trips');

        $response->assertStatus(200)
                 ->assertJsonPath('data.0.available_seats', 28);
    }

    public function test_cancelled_bookings_do_not_reduce_available_seats()
    {
        $schedule = $this->makeTrip();
        $user     = User::factory()->create(['tenant_id' => $schedule->tenant_id, 'email_verified_at' => now()]);

        Booking::create(['tenant_id' => $schedule->tenant_id, 'user_id' => $user->id, 'schedule_id' => $schedule->id, 'total_amount' => 4500, 'status' => 'cancelled', 'passengers' => [['name' => 'A', 'seat_number' => 1]]]);

        $response = $this->getJson('/api/v1/trips');

        $response->assertStatus(200)
                 ->assertJsonPath('data.0.available_seats', 30);
    }

    // -------------------------------------------------------------------------
    // Filtering
    // -------------------------------------------------------------------------

    public function test_can_filter_trips_by_origin()
    {
        $state  = State::firstOrCreate(['name' => 'Lagos'], ['code' => 'LA']);
        $status = Status::firstOrCreate(['name' => 'active']);

        $category    = VehicleCategory::firstOrCreate(['vehicle_category_name' => 'Bus']);
        $vehicleType = $this->vehicleTypeWithSeats($category, 20);

        $tenant  = Tenant::create(['name' => 'Filter Operator']);
        $origin1 = Location::create(['name' => 'Lagos Terminal', 'city' => 'Lagos', 'state_id' => $state->id, 'status_id' => $status->id]);
        $origin2 = Location::create(['name' => 'Abuja Terminal', 'city' => 'Abuja', 'state_id' => $state->id, 'status_id' => $status->id]);
        $dest    = Location::create(['name' => 'PH Terminal',    'city' => 'PH',    'state_id' => $state->id, 'status_id' => $status->id]);

        $route1 = Route::create(['tenant_id' => $tenant->id, 'origin_id' => $origin1->id, 'destination_id' => $dest->id]);
        $route2 = Route::create(['tenant_id' => $tenant->id, 'origin_id' => $origin2->id, 'destination_id' => $dest->id]);

        Schedule::create(['tenant_id' => $tenant->id, 'route_id' => $route1->id, 'vehicle_type_id' => $vehicleType->id, 'departure_time' => now()->addDay(), 'fare' => 3000, 'status' => 'scheduled']);
        Schedule::create(['tenant_id' => $tenant->id, 'route_id' => $route2->id, 'vehicle_type_id' => $vehicleType->id, 'departure_time' => now()->addDay(), 'fare' => 3500, 'status' => 'scheduled']);

        $response = $this->getJson("/api/v1/trips?origin_id={$origin1->id}");

        $response->assertStatus(200)->assertJsonCount(1, 'data');
    }

    public function test_can_filter_trips_by_departure_date()
    {
        $tomorrow      = now()->addDay()->format('Y-m-d');
        $dayAfter      = now()->addDays(2)->format('Y-m-d');

        $this->makeTrip(['departure_time' => now()->addDay()->setTime(9, 0)]);
        $this->makeTrip(['departure_time' => now()->addDays(2)->setTime(9, 0)]);

        $response = $this->getJson("/api/v1/trips?departure_date={$tomorrow}");

        $response->assertStatus(200)->assertJsonCount(1, 'data');
    }

    public function test_can_filter_trips_by_tenant()
    {
        $trip1 = $this->makeTrip();
        $this->makeTrip();

        $response = $this->getJson("/api/v1/trips?tenant_id={$trip1->tenant_id}");

        $response->assertStatus(200)->assertJsonCount(1, 'data');
    }

    public function test_can_filter_trips_by_origin_city()
    {
        $state  = State::firstOrCreate(['name' => 'Lagos'], ['code' => 'LA']);
        $status = Status::firstOrCreate(['name' => 'active']);

        $category    = VehicleCategory::firstOrCreate(['vehicle_category_name' => 'Bus']);
        $vehicleType = $this->vehicleTypeWithSeats($category, 20);

        $tenant = Tenant::create(['name' => 'City Trip Operator']);
        $abuja  = \App\Models\Location::create(['name' => 'Abuja Terminal',  'city' => 'Abuja',        'state_id' => $state->id, 'status_id' => $status->id]);
        $ph     = \App\Models\Location::create(['name' => 'PH Terminal',     'city' => 'Port Harcourt','state_id' => $state->id, 'status_id' => $status->id]);
        $lagos  = \App\Models\Location::create(['name' => 'Lagos Terminal',  'city' => 'Lagos',        'state_id' => $state->id, 'status_id' => $status->id]);

        $routeAL = \App\Models\Route::create(['tenant_id' => $tenant->id, 'origin_id' => $abuja->id, 'destination_id' => $lagos->id]);
        $routePL = \App\Models\Route::create(['tenant_id' => $tenant->id, 'origin_id' => $ph->id,    'destination_id' => $lagos->id]);

        Schedule::create(['tenant_id' => $tenant->id, 'route_id' => $routeAL->id, 'vehicle_type_id' => $vehicleType->id, 'departure_time' => now()->addDay(), 'fare' => 3000, 'status' => 'scheduled']);
        Schedule::create(['tenant_id' => $tenant->id, 'route_id' => $routePL->id, 'vehicle_type_id' => $vehicleType->id, 'departure_time' => now()->addDay(), 'fare' => 4000, 'status' => 'scheduled']);

        $response = $this->getJson('/api/v1/trips?origin_city=Abuja');

        $response->assertStatus(200)->assertJsonCount(1, 'data');
    }

    public function test_can_filter_trips_by_destination_city()
    {
        $state  = State::firstOrCreate(['name' => 'Lagos'], ['code' => 'LA']);
        $status = Status::firstOrCreate(['name' => 'active']);

        $category    = VehicleCategory::firstOrCreate(['vehicle_category_name' => 'Bus']);
        $vehicleType = $this->vehicleTypeWithSeats($category, 20);

        $tenant = Tenant::create(['name' => 'Dest City Operator']);
        $abuja  = \App\Models\Location::create(['name' => 'Abuja Terminal', 'city' => 'Abuja', 'state_id' => $state->id, 'status_id' => $status->id]);
        $kano   = \App\Models\Location::create(['name' => 'Kano Terminal',  'city' => 'Kano',  'state_id' => $state->id, 'status_id' => $status->id]);
        $lagos  = \App\Models\Location::create(['name' => 'Lagos Terminal', 'city' => 'Lagos', 'state_id' => $state->id, 'status_id' => $status->id]);

        $routeAL = \App\Models\Route::create(['tenant_id' => $tenant->id, 'origin_id' => $abuja->id, 'destination_id' => $lagos->id]);
        $routeAK = \App\Models\Route::create(['tenant_id' => $tenant->id, 'origin_id' => $abuja->id, 'destination_id' => $kano->id]);

        Schedule::create(['tenant_id' => $tenant->id, 'route_id' => $routeAL->id, 'vehicle_type_id' => $vehicleType->id, 'departure_time' => now()->addDay(), 'fare' => 3000, 'status' => 'scheduled']);
        Schedule::create(['tenant_id' => $tenant->id, 'route_id' => $routeAK->id, 'vehicle_type_id' => $vehicleType->id, 'departure_time' => now()->addDay(), 'fare' => 4000, 'status' => 'scheduled']);

        $response = $this->getJson('/api/v1/trips?destination_city=Lagos');

        $response->assertStatus(200)->assertJsonCount(1, 'data');
    }

    public function test_invalid_filter_values_return_422()
    {
        $response = $this->getJson('/api/v1/trips?departure_date=not-a-date');

        $response->assertStatus(422)->assertJsonValidationErrors(['departure_date']);
    }

    // -------------------------------------------------------------------------
    // Single trip detail
    // -------------------------------------------------------------------------

    public function test_anyone_can_view_a_single_available_trip()
    {
        $schedule = $this->makeTrip();

        $response = $this->getJson("/api/v1/trips/{$schedule->id}");

        $response->assertStatus(200)
                 ->assertJsonPath('data.id', $schedule->id)
                 ->assertJsonStructure(['data' => ['id', 'available_seats', 'route', 'vehicle_type', 'operator']]);
    }

    public function test_cancelled_trip_returns_404_on_detail()
    {
        $schedule = $this->makeTrip(['status' => 'cancelled']);

        $response = $this->getJson("/api/v1/trips/{$schedule->id}");

        $response->assertStatus(404);
    }

    // -------------------------------------------------------------------------
    // Authenticated schedule index filters
    // -------------------------------------------------------------------------

    public function test_tenant_schedule_index_can_filter_by_status()
    {
        $state  = State::firstOrCreate(['name' => 'Lagos'], ['code' => 'LA']);
        $status = Status::firstOrCreate(['name' => 'active']);

        $category    = VehicleCategory::firstOrCreate(['vehicle_category_name' => 'Bus']);
        $vehicleType = $this->vehicleTypeWithSeats($category, 10);

        $tenant = Tenant::create(['name' => 'Filter Tenant']);
        $user   = User::factory()->create(['tenant_id' => $tenant->id, 'email_verified_at' => now()]);
        $origin = Location::create(['name' => 'A', 'city' => 'A', 'state_id' => $state->id, 'status_id' => $status->id]);
        $dest   = Location::create(['name' => 'B', 'city' => 'B', 'state_id' => $state->id, 'status_id' => $status->id]);
        $route  = Route::create(['tenant_id' => $tenant->id, 'origin_id' => $origin->id, 'destination_id' => $dest->id]);

        Schedule::create(['tenant_id' => $tenant->id, 'route_id' => $route->id, 'vehicle_type_id' => $vehicleType->id, 'departure_time' => now()->addDay(), 'fare' => 2000, 'status' => 'scheduled']);
        Schedule::create(['tenant_id' => $tenant->id, 'route_id' => $route->id, 'vehicle_type_id' => $vehicleType->id, 'departure_time' => now()->addDay(), 'fare' => 2000, 'status' => 'cancelled']);

        \Laravel\Passport\Passport::actingAs($user);

        $response = $this->getJson('/api/v1/schedules?status=scheduled');

        $response->assertStatus(200)->assertJsonCount(1, 'data');
    }
}
