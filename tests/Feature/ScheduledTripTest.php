<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Driver;
use App\Models\Location;
use App\Models\Route;
use App\Models\Schedule;
use App\Models\SeatArrangementType;
use App\Models\State;
use App\Models\Status;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleCategory;
use App\Models\VehicleType;
use App\Services\TicketService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class ScheduledTripTest extends TestCase
{
    use RefreshDatabase;

    public function setUp(): void
    {
        parent::setUp();
        $this->artisan('passport:client', ['--personal' => true, '--no-interaction' => true, '--name' => 'Testing Client']);
    }

    private function makeSchedule(Tenant $tenant): Schedule
    {
        $state  = State::firstOrCreate(['name' => 'Lagos'], ['code' => 'LA']);
        $status = Status::firstOrCreate(['name' => 'active']);

        $category    = VehicleCategory::firstOrCreate(['vehicle_category_name' => 'Bus']);
        $vehicleType = VehicleType::firstOrCreate(
            ['vehicle_name' => 'Toyota Hiace', 'vehicle_category_id' => $category->id, 'year' => 2022],
            ['status' => 'active']
        );
        $arrangement = SeatArrangementType::firstOrCreate(['name' => 'Test 30 Seater'], ['number_of_seats' => 30, 'status' => 'active']);
        $vehicleType->seatArrangementTypes()->syncWithoutDetaching([$arrangement->id => ['is_default' => true]]);

        $origin = Location::create(['name' => 'Origin', 'city' => 'City A', 'state_id' => $state->id, 'status_id' => $status->id]);
        $dest   = Location::create(['name' => 'Dest',   'city' => 'City B', 'state_id' => $state->id, 'status_id' => $status->id]);
        $route  = Route::create(['tenant_id' => $tenant->id, 'origin_id' => $origin->id, 'destination_id' => $dest->id, 'distance' => 200, 'estimated_duration' => 180]);

        return Schedule::create([
            'tenant_id'       => $tenant->id,
            'route_id'        => $route->id,
            'vehicle_type_id' => $vehicleType->id,
            'departure_time'  => now()->addDay(),
            'arrival_time'    => now()->addDay()->addHours(3),
            'fare'            => 4500,
            'status'          => 'scheduled',
        ]);
    }

    private function confirmedBooking(Schedule $schedule): Booking
    {
        $booking = Booking::create([
            'tenant_id'    => $schedule->tenant_id,
            'user_id'      => User::factory()->create()->id,
            'schedule_id'  => $schedule->id,
            'total_amount' => 9000,
            'passengers'   => [
                ['name' => 'Ada Obi', 'phone' => '08030000001', 'seat_number' => 1],
                ['name' => 'Bola Eze', 'phone' => '08030000002', 'seat_number' => 2],
            ],
            'status'       => 'confirmed',
        ]);
        app(TicketService::class)->issueForBooking($booking);

        return $booking;
    }

    private function operatorFor(Tenant $tenant): User
    {
        $operator = User::factory()->create(['tenant_id' => $tenant->id]);
        Passport::actingAs($operator);
        return $operator;
    }

    public function test_only_schedules_with_confirmed_bookings_are_listed_as_scheduled_trips()
    {
        $tenant = Tenant::create(['name' => 'Operator ' . uniqid()]);

        $withBooking = $this->makeSchedule($tenant);
        $this->confirmedBooking($withBooking);

        $empty = $this->makeSchedule($tenant); // no bookings — not a scheduled trip

        $this->operatorFor($tenant);

        $response = $this->getJson('/api/v1/scheduled-trips')->assertStatus(200);
        $ids = collect($response->json('data'))->pluck('id');

        $this->assertTrue($ids->contains($withBooking->id));
        $this->assertFalse($ids->contains($empty->id));
        $response->assertJsonPath('data.0.passenger_count', 2);
        $response->assertJsonPath('data.0.checked_in_count', 0);
        $response->assertJsonPath('data.0.trip_status', 'not_started');
    }

    public function test_passenger_manifest_reflects_check_in_state()
    {
        $tenant  = Tenant::create(['name' => 'Operator ' . uniqid()]);
        $trip    = $this->makeSchedule($tenant);
        $booking = $this->confirmedBooking($trip);
        $this->operatorFor($tenant);

        // Before boarding: nobody checked in.
        $this->getJson("/api/v1/scheduled-trips/{$trip->id}/passengers")
            ->assertStatus(200)
            ->assertJsonPath('data.passenger_count', 2)
            ->assertJsonPath('data.checked_in_count', 0)
            ->assertJsonPath('data.not_checked_in_count', 2);

        // Check one passenger in via their ticket.
        $ticket = $booking->tickets()->first();
        $this->patchJson("/api/v1/tickets/{$ticket->qr_code}/check-in")->assertStatus(200);

        $this->getJson("/api/v1/scheduled-trips/{$trip->id}/passengers")
            ->assertStatus(200)
            ->assertJsonPath('data.checked_in_count', 1)
            ->assertJsonPath('data.not_checked_in_count', 1);
    }

    public function test_status_moves_through_lifecycle_and_rejects_bad_values()
    {
        $tenant = Tenant::create(['name' => 'Operator ' . uniqid()]);
        $trip   = $this->makeSchedule($tenant);
        $this->confirmedBooking($trip);
        $this->operatorFor($tenant);

        // A trip cannot start until a vehicle and driver are assigned.
        $this->patchJson("/api/v1/scheduled-trips/{$trip->id}/status", ['status' => 'ongoing'])
            ->assertStatus(422);

        $trip->update(['vehicle_id' => $this->vehicleFor($trip)->id, 'driver_id' => $this->driverFor($tenant)->id]);

        $this->patchJson("/api/v1/scheduled-trips/{$trip->id}/status", ['status' => 'ongoing'])
            ->assertStatus(200)
            ->assertJsonPath('data.trip_status', 'ongoing');

        $this->patchJson("/api/v1/scheduled-trips/{$trip->id}/status", ['status' => 'in_transit'])
            ->assertStatus(422);
    }

    public function test_cancelling_a_trip_cancels_its_bookings_and_drops_it_from_search()
    {
        $tenant  = Tenant::create(['name' => 'Operator ' . uniqid()]);
        $trip    = $this->makeSchedule($tenant);
        $booking = $this->confirmedBooking($trip);
        $this->operatorFor($tenant);

        $this->patchJson("/api/v1/scheduled-trips/{$trip->id}/cancel")
            ->assertStatus(200)
            ->assertJsonPath('data.trip_status', 'cancelled');

        $this->assertEquals('cancelled', $trip->fresh()->status);
        $this->assertEquals('cancelled', $booking->fresh()->status);
    }

    private function vehicleFor(Schedule $schedule, ?int $vehicleTypeId = null): Vehicle
    {
        return Vehicle::create([
            'tenant_id'           => $schedule->tenant_id,
            'vehicle_type_id'     => $vehicleTypeId ?? $schedule->vehicle_type_id,
            'registration_number' => 'REG-' . strtoupper(uniqid()),
            'status'              => 'active',
        ]);
    }

    private function driverFor(Tenant $tenant): Driver
    {
        return Driver::create([
            'tenant_id'      => $tenant->id,
            'name'           => 'Driver ' . uniqid(),
            'phone'          => '08030000009',
            'licence_number' => 'LIC-' . strtoupper(uniqid()),
            'status'         => 'active',
        ]);
    }

    public function test_operator_can_assign_a_matching_vehicle_and_driver()
    {
        $tenant  = Tenant::create(['name' => 'Operator ' . uniqid()]);
        $trip    = $this->makeSchedule($tenant);
        $vehicle = $this->vehicleFor($trip);
        $driver  = $this->driverFor($tenant);
        $this->operatorFor($tenant);

        $this->patchJson("/api/v1/scheduled-trips/{$trip->id}/assignment", [
            'vehicle_id' => $vehicle->id,
            'driver_id'  => $driver->id,
        ])
            ->assertStatus(200)
            ->assertJsonPath('data.assignment.assigned', true)
            ->assertJsonPath('data.assignment.vehicle.id', $vehicle->id)
            ->assertJsonPath('data.assignment.driver.id', $driver->id);

        $this->assertEquals($vehicle->id, $trip->fresh()->vehicle_id);
        $this->assertEquals($driver->id, $trip->fresh()->driver_id);
    }

    public function test_cannot_assign_a_vehicle_of_a_different_type()
    {
        $tenant = Tenant::create(['name' => 'Operator ' . uniqid()]);
        $trip   = $this->makeSchedule($tenant);

        // A vehicle of some other type.
        $otherType   = VehicleType::firstOrCreate(
            ['vehicle_name' => 'Mercedes Sprinter', 'vehicle_category_id' => VehicleCategory::firstOrCreate(['vehicle_category_name' => 'Bus'])->id, 'year' => 2023],
            ['status' => 'active']
        );
        $wrongVehicle = $this->vehicleFor($trip, $otherType->id);
        $driver       = $this->driverFor($tenant);
        $this->operatorFor($tenant);

        $this->patchJson("/api/v1/scheduled-trips/{$trip->id}/assignment", [
            'vehicle_id' => $wrongVehicle->id,
            'driver_id'  => $driver->id,
        ])->assertStatus(422);
    }

    public function test_cannot_double_book_a_vehicle_for_an_overlapping_trip()
    {
        $tenant   = Tenant::create(['name' => 'Operator ' . uniqid()]);
        $tripA    = $this->makeSchedule($tenant);
        $tripB    = $this->makeSchedule($tenant); // same route/time window (both depart +1 day)
        $vehicle  = $this->vehicleFor($tripA);
        $driverA  = $this->driverFor($tenant);
        $driverB  = $this->driverFor($tenant);
        $this->operatorFor($tenant);

        $this->patchJson("/api/v1/scheduled-trips/{$tripA->id}/assignment", [
            'vehicle_id' => $vehicle->id,
            'driver_id'  => $driverA->id,
        ])->assertStatus(200);

        // Same vehicle, overlapping window → rejected.
        $this->patchJson("/api/v1/scheduled-trips/{$tripB->id}/assignment", [
            'vehicle_id' => $vehicle->id,
            'driver_id'  => $driverB->id,
        ])->assertStatus(422);
    }

    public function test_operator_can_clear_an_assignment()
    {
        $tenant  = Tenant::create(['name' => 'Operator ' . uniqid()]);
        $trip    = $this->makeSchedule($tenant);
        $vehicle = $this->vehicleFor($trip);
        $driver  = $this->driverFor($tenant);
        $this->operatorFor($tenant);

        $this->patchJson("/api/v1/scheduled-trips/{$trip->id}/assignment", [
            'vehicle_id' => $vehicle->id,
            'driver_id'  => $driver->id,
        ])->assertStatus(200);

        $this->deleteJson("/api/v1/scheduled-trips/{$trip->id}/assignment")
            ->assertStatus(200)
            ->assertJsonPath('data.assignment.assigned', false);

        $this->assertNull($trip->fresh()->vehicle_id);
        $this->assertNull($trip->fresh()->driver_id);
    }
}
