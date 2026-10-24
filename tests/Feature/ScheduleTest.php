<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\ScheduleGroup;
use App\Models\Role;
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
use Illuminate\Foundation\Testing\WithFaker;
use Laravel\Passport\Passport;
use Tests\TestCase;

class ScheduleTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    public function setUp(): void
    {
        parent::setUp();
        $this->artisan('passport:client', ['--personal' => true, '--no-interaction' => true, '--name' => 'Testing Client']);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeSuperAdmin(): User
    {
        $role = Role::create(['name' => 'super_admin', 'tenant_id' => null]);
        $user = User::factory()->create(['tenant_id' => null, 'email_verified_at' => now()]);
        $user->roles()->attach($role->id);
        return $user;
    }

    private function makeVehicleType(string $name = 'Toyota Hiace'): VehicleType
    {
        $category = VehicleCategory::firstOrCreate(['vehicle_category_name' => 'Bus']);
        $vehicleType = VehicleType::firstOrCreate(
            ['vehicle_name' => $name, 'vehicle_category_id' => $category->id, 'year' => 2022],
            ['status' => 'active']
        );

        // Capacity is derived from the seat arrangement, so attach a default one.
        $arrangement = SeatArrangementType::firstOrCreate(
            ['name' => 'Test 14 Seater'],
            ['number_of_seats' => 14, 'status' => 'active']
        );
        $vehicleType->seatArrangementTypes()->syncWithoutDetaching([$arrangement->id => ['is_default' => true]]);

        return $vehicleType;
    }

    private function makeTenantWithResources(string $tenantName = 'Fast Travel Ltd'): array
    {
        $state  = State::firstOrCreate(['name' => 'Lagos'], ['code' => 'LA']);
        $status = Status::firstOrCreate(['name' => 'active']);

        $tenant = Tenant::create(['name' => $tenantName]);
        $user   = User::factory()->create(['tenant_id' => $tenant->id, 'email_verified_at' => now()]);

        $origin      = Location::create(['name' => 'Origin Terminal', 'city' => 'City A', 'state_id' => $state->id, 'status_id' => $status->id]);
        $destination = Location::create(['name' => 'Dest Terminal',   'city' => 'City B', 'state_id' => $state->id, 'status_id' => $status->id]);
        $route       = Route::create(['tenant_id' => $tenant->id, 'origin_id' => $origin->id, 'destination_id' => $destination->id, 'distance' => 100, 'estimated_duration' => 120]);
        $vehicleType = $this->makeVehicleType();

        return compact('tenant', 'user', 'route', 'vehicleType');
    }

    private function schedulePayload(int $routeId, int $vehicleTypeId, array $overrides = []): array
    {
        return array_merge([
            'route_id'        => $routeId,
            'vehicle_type_id' => $vehicleTypeId,
            'departure_time'  => now()->addDay()->format('Y-m-d H:i:s'),
            'arrival_time'    => now()->addDay()->addHours(2)->format('Y-m-d H:i:s'),
            'fare'            => 5000.00,
        ], $overrides);
    }

    // -------------------------------------------------------------------------
    // Tenant — create
    // -------------------------------------------------------------------------

    public function test_tenant_user_can_create_schedule()
    {
        ['tenant' => $tenant, 'user' => $user, 'route' => $route, 'vehicleType' => $vehicleType] = $this->makeTenantWithResources();

        Passport::actingAs($user);

        $response = $this->postJson('/api/v1/schedules', $this->schedulePayload($route->id, $vehicleType->id));

        $response->assertStatus(201)
                 ->assertJsonFragment(['fare' => '5000.00'])
                 ->assertJsonPath('data.route.id', $route->id)
                 ->assertJsonPath('data.vehicle_type.id', $vehicleType->id);

        $this->assertDatabaseHas('schedules', [
            'tenant_id'       => $tenant->id,
            'route_id'        => $route->id,
            'vehicle_type_id' => $vehicleType->id,
        ]);
    }

    public function test_inactive_vehicle_type_cannot_be_used_in_a_schedule()
    {
        ['user' => $user, 'route' => $route] = $this->makeTenantWithResources();
        $category    = VehicleCategory::firstOrCreate(['vehicle_category_name' => 'Bus']);
        $inactiveType = VehicleType::create([
            'vehicle_name'        => 'Old Clunker',
            'vehicle_category_id' => $category->id,
            'capacity'            => 10,
            'year'                => 2000,
            'status'              => 'inactive',
        ]);

        Passport::actingAs($user);

        $response = $this->postJson('/api/v1/schedules', $this->schedulePayload($route->id, $inactiveType->id));

        $response->assertStatus(422)->assertJsonValidationErrors(['vehicle_type_id']);
    }

    public function test_tenant_user_cannot_create_schedule_with_cross_tenant_route()
    {
        ['user' => $user, 'vehicleType' => $vehicleType] = $this->makeTenantWithResources('Tenant A');
        ['route' => $otherRoute]                          = $this->makeTenantWithResources('Tenant B');

        Passport::actingAs($user);

        $response = $this->postJson('/api/v1/schedules', $this->schedulePayload($otherRoute->id, $vehicleType->id));

        $response->assertStatus(422)->assertJsonValidationErrors(['route_id']);
    }

    public function test_departure_time_must_be_in_the_future()
    {
        ['user' => $user, 'route' => $route, 'vehicleType' => $vehicleType] = $this->makeTenantWithResources();

        Passport::actingAs($user);

        $response = $this->postJson('/api/v1/schedules', $this->schedulePayload($route->id, $vehicleType->id, [
            'departure_time' => now()->subHour()->format('Y-m-d H:i:s'),
        ]));

        $response->assertStatus(422)->assertJsonValidationErrors(['departure_time']);
    }

    public function test_arrival_time_must_be_after_departure()
    {
        ['user' => $user, 'route' => $route, 'vehicleType' => $vehicleType] = $this->makeTenantWithResources();

        Passport::actingAs($user);

        $response = $this->postJson('/api/v1/schedules', $this->schedulePayload($route->id, $vehicleType->id, [
            'departure_time' => now()->addDays(2)->format('Y-m-d H:i:s'),
            'arrival_time'   => now()->addDay()->format('Y-m-d H:i:s'),
        ]));

        $response->assertStatus(422)->assertJsonValidationErrors(['arrival_time']);
    }

    public function test_user_without_tenant_cannot_create_schedule()
    {
        ['route' => $route, 'vehicleType' => $vehicleType] = $this->makeTenantWithResources();
        $user = User::factory()->create(['tenant_id' => null, 'email_verified_at' => now()]);

        Passport::actingAs($user);

        $response = $this->postJson('/api/v1/schedules', $this->schedulePayload($route->id, $vehicleType->id));

        $response->assertStatus(400);
    }

    // -------------------------------------------------------------------------
    // Tenant — read
    // -------------------------------------------------------------------------

    public function test_tenant_user_can_list_only_their_schedules()
    {
        ['user' => $user, 'tenant' => $tenant, 'route' => $route, 'vehicleType' => $vehicleType] = $this->makeTenantWithResources('Tenant A');
        ['tenant' => $tenant2, 'route' => $route2]                                               = $this->makeTenantWithResources('Tenant B');

        Schedule::create(array_merge($this->schedulePayload($route->id, $vehicleType->id),  ['tenant_id' => $tenant->id]));
        Schedule::create(array_merge($this->schedulePayload($route2->id, $vehicleType->id), ['tenant_id' => $tenant2->id]));

        Passport::actingAs($user);

        $response = $this->getJson('/api/v1/schedules');

        $response->assertStatus(200)->assertJsonCount(1, 'data');
    }

    public function test_tenant_user_can_show_their_own_schedule()
    {
        ['user' => $user, 'tenant' => $tenant, 'route' => $route, 'vehicleType' => $vehicleType] = $this->makeTenantWithResources();

        $schedule = Schedule::create(array_merge($this->schedulePayload($route->id, $vehicleType->id), ['tenant_id' => $tenant->id]));

        Passport::actingAs($user);

        $response = $this->getJson("/api/v1/schedules/{$schedule->id}");

        $response->assertStatus(200)->assertJsonPath('data.id', $schedule->id);
    }

    public function test_tenant_user_cannot_view_another_tenants_schedule()
    {
        ['user' => $user]                                                    = $this->makeTenantWithResources('Tenant A');
        ['tenant' => $tenant2, 'route' => $route2, 'vehicleType' => $vt]    = $this->makeTenantWithResources('Tenant B');

        $schedule = Schedule::create(array_merge($this->schedulePayload($route2->id, $vt->id), ['tenant_id' => $tenant2->id]));

        Passport::actingAs($user);

        $response = $this->getJson("/api/v1/schedules/{$schedule->id}");

        $response->assertStatus(404);
    }

    // -------------------------------------------------------------------------
    // Tenant — update
    // -------------------------------------------------------------------------

    public function test_tenant_user_can_update_their_own_schedule()
    {
        ['user' => $user, 'tenant' => $tenant, 'route' => $route, 'vehicleType' => $vehicleType] = $this->makeTenantWithResources();

        $schedule = Schedule::create(array_merge($this->schedulePayload($route->id, $vehicleType->id), ['tenant_id' => $tenant->id]));

        Passport::actingAs($user);

        $response = $this->putJson("/api/v1/schedules/{$schedule->id}", ['fare' => 7500.00, 'status' => 'cancelled']);

        $response->assertStatus(200)
                 ->assertJsonFragment(['fare' => '7500.00'])
                 ->assertJsonFragment(['status' => 'cancelled']);
    }

    public function test_tenant_user_cannot_update_another_tenants_schedule()
    {
        ['user' => $user]                                                 = $this->makeTenantWithResources('Tenant A');
        ['tenant' => $tenant2, 'route' => $route2, 'vehicleType' => $vt] = $this->makeTenantWithResources('Tenant B');

        $schedule = Schedule::create(array_merge($this->schedulePayload($route2->id, $vt->id), ['tenant_id' => $tenant2->id]));

        Passport::actingAs($user);

        $response = $this->putJson("/api/v1/schedules/{$schedule->id}", ['fare' => 1.00]);

        $response->assertStatus(404);
    }

    // -------------------------------------------------------------------------
    // Tenant — delete
    // -------------------------------------------------------------------------

    public function test_tenant_user_can_delete_their_own_schedule()
    {
        ['user' => $user, 'tenant' => $tenant, 'route' => $route, 'vehicleType' => $vehicleType] = $this->makeTenantWithResources();

        $schedule = Schedule::create(array_merge($this->schedulePayload($route->id, $vehicleType->id), ['tenant_id' => $tenant->id]));

        Passport::actingAs($user);

        $response = $this->deleteJson("/api/v1/schedules/{$schedule->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('schedules', ['id' => $schedule->id]);
    }

    public function test_tenant_user_cannot_delete_another_tenants_schedule()
    {
        ['user' => $user]                                                 = $this->makeTenantWithResources('Tenant A');
        ['tenant' => $tenant2, 'route' => $route2, 'vehicleType' => $vt] = $this->makeTenantWithResources('Tenant B');

        $schedule = Schedule::create(array_merge($this->schedulePayload($route2->id, $vt->id), ['tenant_id' => $tenant2->id]));

        Passport::actingAs($user);

        $response = $this->deleteJson("/api/v1/schedules/{$schedule->id}");

        $response->assertStatus(404);
        $this->assertDatabaseHas('schedules', ['id' => $schedule->id]);
    }

    // -------------------------------------------------------------------------
    // Super Admin — full CRUD across tenants
    // -------------------------------------------------------------------------

    public function test_super_admin_can_create_schedule_for_a_tenant()
    {
        $superAdmin  = $this->makeSuperAdmin();
        ['tenant' => $tenant, 'route' => $route, 'vehicleType' => $vehicleType] = $this->makeTenantWithResources();

        Passport::actingAs($superAdmin);

        $response = $this->postJson('/api/v1/schedules', array_merge(
            $this->schedulePayload($route->id, $vehicleType->id),
            ['tenant_id' => $tenant->id]
        ));

        $response->assertStatus(201)
                 ->assertJsonPath('data.route.id', $route->id)
                 ->assertJsonPath('data.vehicle_type.id', $vehicleType->id);

        $this->assertDatabaseHas('schedules', ['tenant_id' => $tenant->id, 'route_id' => $route->id]);
    }

    public function test_super_admin_can_list_all_schedules_across_tenants()
    {
        $superAdmin  = $this->makeSuperAdmin();
        ['tenant' => $tenant1, 'route' => $route1, 'vehicleType' => $vt] = $this->makeTenantWithResources('Tenant A');
        ['tenant' => $tenant2, 'route' => $route2]                        = $this->makeTenantWithResources('Tenant B');

        Schedule::create(array_merge($this->schedulePayload($route1->id, $vt->id), ['tenant_id' => $tenant1->id]));
        Schedule::create(array_merge($this->schedulePayload($route2->id, $vt->id), ['tenant_id' => $tenant2->id]));

        Passport::actingAs($superAdmin);

        $response = $this->getJson('/api/v1/schedules');

        $response->assertStatus(200)->assertJsonCount(2, 'data');
    }

    public function test_super_admin_can_filter_schedules_by_tenant()
    {
        $superAdmin  = $this->makeSuperAdmin();
        ['tenant' => $tenant1, 'route' => $route1, 'vehicleType' => $vt] = $this->makeTenantWithResources('Tenant A');
        ['tenant' => $tenant2, 'route' => $route2]                        = $this->makeTenantWithResources('Tenant B');

        Schedule::create(array_merge($this->schedulePayload($route1->id, $vt->id), ['tenant_id' => $tenant1->id]));
        Schedule::create(array_merge($this->schedulePayload($route2->id, $vt->id), ['tenant_id' => $tenant2->id]));

        Passport::actingAs($superAdmin);

        $response = $this->getJson("/api/v1/schedules?tenant_id={$tenant1->id}");

        $response->assertStatus(200)->assertJsonCount(1, 'data');
    }

    public function test_super_admin_can_show_any_schedule()
    {
        $superAdmin  = $this->makeSuperAdmin();
        ['tenant' => $tenant, 'route' => $route, 'vehicleType' => $vehicleType] = $this->makeTenantWithResources();

        $schedule = Schedule::create(array_merge($this->schedulePayload($route->id, $vehicleType->id), ['tenant_id' => $tenant->id]));

        Passport::actingAs($superAdmin);

        $response = $this->getJson("/api/v1/schedules/{$schedule->id}");

        $response->assertStatus(200)->assertJsonPath('data.id', $schedule->id);
    }

    public function test_super_admin_can_update_any_schedule()
    {
        $superAdmin  = $this->makeSuperAdmin();
        ['tenant' => $tenant, 'route' => $route, 'vehicleType' => $vehicleType] = $this->makeTenantWithResources();

        $schedule = Schedule::create(array_merge($this->schedulePayload($route->id, $vehicleType->id), ['tenant_id' => $tenant->id]));

        Passport::actingAs($superAdmin);

        $response = $this->putJson("/api/v1/schedules/{$schedule->id}", ['status' => 'cancelled']);

        $response->assertStatus(200)->assertJsonFragment(['status' => 'cancelled']);
    }

    public function test_super_admin_can_delete_any_schedule()
    {
        $superAdmin  = $this->makeSuperAdmin();
        ['tenant' => $tenant, 'route' => $route, 'vehicleType' => $vehicleType] = $this->makeTenantWithResources();

        $schedule = Schedule::create(array_merge($this->schedulePayload($route->id, $vehicleType->id), ['tenant_id' => $tenant->id]));

        Passport::actingAs($superAdmin);

        $response = $this->deleteJson("/api/v1/schedules/{$schedule->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('schedules', ['id' => $schedule->id]);
    }

    public function test_super_admin_must_supply_tenant_id_when_creating_schedule()
    {
        $superAdmin  = $this->makeSuperAdmin();
        ['route' => $route, 'vehicleType' => $vehicleType] = $this->makeTenantWithResources();

        Passport::actingAs($superAdmin);

        $response = $this->postJson('/api/v1/schedules', $this->schedulePayload($route->id, $vehicleType->id));

        $response->assertStatus(422)->assertJsonValidationErrors(['tenant_id']);
    }

    // -------------------------------------------------------------------------
    // Schedule response shape
    // -------------------------------------------------------------------------

    public function test_schedule_response_includes_available_seats_route_and_operator()
    {
        ['user' => $user, 'tenant' => $tenant, 'route' => $route, 'vehicleType' => $vehicleType] = $this->makeTenantWithResources();

        $schedule = Schedule::create(array_merge($this->schedulePayload($route->id, $vehicleType->id), ['tenant_id' => $tenant->id]));

        Passport::actingAs($user);

        $response = $this->getJson("/api/v1/schedules/{$schedule->id}");

        $response->assertStatus(200)
                 ->assertJsonStructure(['data' => [
                     'id', 'departure_time', 'arrival_time', 'fare', 'status', 'available_seats',
                     'route'        => ['id', 'distance', 'estimated_duration', 'origin', 'destination'],
                     'vehicle_type' => ['id', 'vehicle_name', 'seat_arrangements'],
                     'operator'     => ['id', 'name'],
                 ]]);
    }

    // -------------------------------------------------------------------------
    // City-name filtering (external integration path)
    // -------------------------------------------------------------------------

    public function test_tenant_can_filter_schedules_by_origin_city()
    {
        $state  = State::firstOrCreate(['name' => 'Lagos'], ['code' => 'LA']);
        $status = Status::firstOrCreate(['name' => 'active']);
        $vt     = $this->makeVehicleType();

        $tenant = Tenant::create(['name' => 'City Filter Tenant']);
        $user   = User::factory()->create(['tenant_id' => $tenant->id, 'email_verified_at' => now()]);

        $abuja  = Location::create(['name' => 'Abuja Terminal', 'city' => 'Abuja', 'state_id' => $state->id, 'status_id' => $status->id]);
        $lagos  = Location::create(['name' => 'Lagos Terminal', 'city' => 'Lagos', 'state_id' => $state->id, 'status_id' => $status->id]);
        $ph     = Location::create(['name' => 'PH Terminal',    'city' => 'Port Harcourt', 'state_id' => $state->id, 'status_id' => $status->id]);

        $routeAL = Route::create(['tenant_id' => $tenant->id, 'origin_id' => $abuja->id, 'destination_id' => $lagos->id]);
        $routePL = Route::create(['tenant_id' => $tenant->id, 'origin_id' => $ph->id,    'destination_id' => $lagos->id]);

        Schedule::create(array_merge($this->schedulePayload($routeAL->id, $vt->id), ['tenant_id' => $tenant->id]));
        Schedule::create(array_merge($this->schedulePayload($routePL->id, $vt->id), ['tenant_id' => $tenant->id]));

        Passport::actingAs($user);

        $response = $this->getJson('/api/v1/schedules?origin_city=Abuja');

        $response->assertStatus(200)->assertJsonCount(1, 'data');
    }

    public function test_tenant_can_filter_schedules_by_destination_city()
    {
        $state  = State::firstOrCreate(['name' => 'Lagos'], ['code' => 'LA']);
        $status = Status::firstOrCreate(['name' => 'active']);
        $vt     = $this->makeVehicleType();

        $tenant = Tenant::create(['name' => 'Dest City Tenant']);
        $user   = User::factory()->create(['tenant_id' => $tenant->id, 'email_verified_at' => now()]);

        $abuja  = Location::create(['name' => 'Abuja Terminal', 'city' => 'Abuja', 'state_id' => $state->id, 'status_id' => $status->id]);
        $lagos  = Location::create(['name' => 'Lagos Terminal', 'city' => 'Lagos', 'state_id' => $state->id, 'status_id' => $status->id]);
        $kano   = Location::create(['name' => 'Kano Terminal',  'city' => 'Kano',  'state_id' => $state->id, 'status_id' => $status->id]);

        $routeAL = Route::create(['tenant_id' => $tenant->id, 'origin_id' => $abuja->id, 'destination_id' => $lagos->id]);
        $routeAK = Route::create(['tenant_id' => $tenant->id, 'origin_id' => $abuja->id, 'destination_id' => $kano->id]);

        Schedule::create(array_merge($this->schedulePayload($routeAL->id, $vt->id), ['tenant_id' => $tenant->id]));
        Schedule::create(array_merge($this->schedulePayload($routeAK->id, $vt->id), ['tenant_id' => $tenant->id]));

        Passport::actingAs($user);

        $response = $this->getJson('/api/v1/schedules?destination_city=Lagos');

        $response->assertStatus(200)->assertJsonCount(1, 'data');
    }

    public function test_city_filter_is_case_insensitive()
    {
        $state  = State::firstOrCreate(['name' => 'Lagos'], ['code' => 'LA']);
        $status = Status::firstOrCreate(['name' => 'active']);
        $vt     = $this->makeVehicleType();

        $tenant = Tenant::create(['name' => 'Case Test Tenant']);
        $user   = User::factory()->create(['tenant_id' => $tenant->id, 'email_verified_at' => now()]);

        $abuja = Location::create(['name' => 'Abuja Terminal', 'city' => 'Abuja', 'state_id' => $state->id, 'status_id' => $status->id]);
        $lagos = Location::create(['name' => 'Lagos Terminal', 'city' => 'Lagos', 'state_id' => $state->id, 'status_id' => $status->id]);
        $route = Route::create(['tenant_id' => $tenant->id, 'origin_id' => $abuja->id, 'destination_id' => $lagos->id]);

        Schedule::create(array_merge($this->schedulePayload($route->id, $vt->id), ['tenant_id' => $tenant->id]));

        Passport::actingAs($user);

        $response = $this->getJson('/api/v1/schedules?origin_city=abuja');

        $response->assertStatus(200)->assertJsonCount(1, 'data');
    }

    // -------------------------------------------------------------------------
    // Recurring schedules
    // -------------------------------------------------------------------------

    private function recurringPayload(int $routeId, int $vehicleTypeId, array $overrides = []): array
    {
        return array_merge([
            'route_id'        => $routeId,
            'vehicle_type_id' => $vehicleTypeId,
            'start_date'      => now()->addDay()->format('Y-m-d'),
            'end_date'        => now()->addDays(14)->format('Y-m-d'),
            'days_of_week'    => ['monday', 'wednesday', 'friday'],
            'departure_time'  => '08:00',
            'arrival_time'    => '20:00',
            'fare'            => 5000,
        ], $overrides);
    }

    public function test_tenant_can_create_recurring_schedule()
    {
        ['user' => $user, 'tenant' => $tenant, 'route' => $route, 'vehicleType' => $vehicleType] = $this->makeTenantWithResources();

        Passport::actingAs($user);

        $response = $this->postJson('/api/v1/schedules', $this->recurringPayload($route->id, $vehicleType->id));

        $response->assertStatus(201)
                 ->assertJsonStructure(['data' => [
                     'group'           => ['id', 'start_date', 'end_date', 'days_of_week', 'departure_time', 'fare', 'route', 'vehicle_type', 'operator'],
                     'schedules_count',
                     'schedules',
                 ]]);

        $this->assertDatabaseHas('schedule_groups', ['tenant_id' => $tenant->id, 'route_id' => $route->id]);
    }

    public function test_recurring_schedule_generates_correct_occurrences()
    {
        ['user' => $user, 'route' => $route, 'vehicleType' => $vehicleType] = $this->makeTenantWithResources();

        Passport::actingAs($user);

        // Use a fixed range: Mon 2026-07-06 → Sun 2026-07-12 (Mon, Wed, Fri = 3 occurrences)
        $response = $this->postJson('/api/v1/schedules', $this->recurringPayload($route->id, $vehicleType->id, [
            'start_date'   => '2026-07-06',
            'end_date'     => '2026-07-12',
            'days_of_week' => ['monday', 'wednesday', 'friday'],
        ]));

        $response->assertStatus(201)
                 ->assertJsonPath('data.schedules_count', 3);
    }

    public function test_recurring_schedule_respects_selected_days_only()
    {
        ['user' => $user, 'route' => $route, 'vehicleType' => $vehicleType] = $this->makeTenantWithResources();

        Passport::actingAs($user);

        // Only Tuesday in a Mon-Sun range = 1 occurrence
        $response = $this->postJson('/api/v1/schedules', $this->recurringPayload($route->id, $vehicleType->id, [
            'start_date'   => '2026-07-06',
            'end_date'     => '2026-07-12',
            'days_of_week' => ['tuesday'],
        ]));

        $response->assertStatus(201)
                 ->assertJsonPath('data.schedules_count', 1);
    }

    public function test_recurring_departure_times_are_set_correctly()
    {
        ['user' => $user, 'route' => $route, 'vehicleType' => $vehicleType] = $this->makeTenantWithResources();

        Passport::actingAs($user);

        $response = $this->postJson('/api/v1/schedules', $this->recurringPayload($route->id, $vehicleType->id, [
            'start_date'     => '2026-07-06', // Monday
            'end_date'       => '2026-07-06',
            'days_of_week'   => ['monday'],
            'departure_time' => '08:30',
            'arrival_time'   => '20:00',
        ]));

        $response->assertStatus(201);

        $this->assertDatabaseHas('schedules', [
            'departure_time' => '2026-07-06 08:30:00',
            'arrival_time'   => '2026-07-06 20:00:00',
        ]);
    }

    public function test_overnight_arrival_rolls_to_next_day()
    {
        ['user' => $user, 'route' => $route, 'vehicleType' => $vehicleType] = $this->makeTenantWithResources();

        Passport::actingAs($user);

        $response = $this->postJson('/api/v1/schedules', $this->recurringPayload($route->id, $vehicleType->id, [
            'start_date'     => '2026-07-06', // Monday
            'end_date'       => '2026-07-06',
            'days_of_week'   => ['monday'],
            'departure_time' => '22:00',
            'arrival_time'   => '06:00', // next day
        ]));

        $response->assertStatus(201);

        $this->assertDatabaseHas('schedules', [
            'departure_time' => '2026-07-06 22:00:00',
            'arrival_time'   => '2026-07-07 06:00:00',
        ]);
    }

    public function test_recurring_requires_at_least_one_day_of_week()
    {
        ['user' => $user, 'route' => $route, 'vehicleType' => $vehicleType] = $this->makeTenantWithResources();

        Passport::actingAs($user);

        $response = $this->postJson('/api/v1/schedules', $this->recurringPayload($route->id, $vehicleType->id, [
            'days_of_week' => [],
        ]));

        $response->assertStatus(422)->assertJsonValidationErrors(['days_of_week']);
    }

    public function test_recurring_rejects_invalid_day_names()
    {
        ['user' => $user, 'route' => $route, 'vehicleType' => $vehicleType] = $this->makeTenantWithResources();

        Passport::actingAs($user);

        $response = $this->postJson('/api/v1/schedules', $this->recurringPayload($route->id, $vehicleType->id, [
            'days_of_week' => ['monday', 'funday'],
        ]));

        $response->assertStatus(422)->assertJsonValidationErrors(['days_of_week.1']);
    }

    public function test_recurring_end_date_must_be_after_start_date()
    {
        ['user' => $user, 'route' => $route, 'vehicleType' => $vehicleType] = $this->makeTenantWithResources();

        Passport::actingAs($user);

        $response = $this->postJson('/api/v1/schedules', $this->recurringPayload($route->id, $vehicleType->id, [
            'start_date' => '2026-07-10',
            'end_date'   => '2026-07-08', // before start_date
        ]));

        $response->assertStatus(422)->assertJsonValidationErrors(['end_date']);
    }

    public function test_recurring_creates_a_schedule_group_record()
    {
        ['user' => $user, 'route' => $route, 'vehicleType' => $vehicleType] = $this->makeTenantWithResources();

        Passport::actingAs($user);

        $this->postJson('/api/v1/schedules', $this->recurringPayload($route->id, $vehicleType->id, [
            'start_date'   => '2026-07-06',
            'end_date'     => '2026-07-12',
            'days_of_week' => ['monday'],
        ]));

        $this->assertDatabaseCount('schedule_groups', 1);

        $group = ScheduleGroup::first();
        $this->assertEquals(['monday'], $group->days_of_week);
        $this->assertEquals('2026-07-06', $group->start_date->format('Y-m-d'));
    }

    public function test_city_and_date_filters_can_combine()
    {
        $state  = State::firstOrCreate(['name' => 'Lagos'], ['code' => 'LA']);
        $status = Status::firstOrCreate(['name' => 'active']);
        $vt     = $this->makeVehicleType();

        $tenant  = Tenant::create(['name' => 'Combo Filter Tenant']);
        $user    = User::factory()->create(['tenant_id' => $tenant->id, 'email_verified_at' => now()]);

        $abuja = Location::create(['name' => 'Abuja Terminal', 'city' => 'Abuja', 'state_id' => $state->id, 'status_id' => $status->id]);
        $lagos = Location::create(['name' => 'Lagos Terminal', 'city' => 'Lagos', 'state_id' => $state->id, 'status_id' => $status->id]);
        $route = Route::create(['tenant_id' => $tenant->id, 'origin_id' => $abuja->id, 'destination_id' => $lagos->id]);

        $tomorrow  = now()->addDay()->format('Y-m-d');
        $dayAfter  = now()->addDays(2)->format('Y-m-d');

        Schedule::create(array_merge($this->schedulePayload($route->id, $vt->id, ['departure_time' => now()->addDay()->setTime(9, 0)->format('Y-m-d H:i:s')]),   ['tenant_id' => $tenant->id]));
        Schedule::create(array_merge($this->schedulePayload($route->id, $vt->id, ['departure_time' => now()->addDays(2)->setTime(9, 0)->format('Y-m-d H:i:s')]), ['tenant_id' => $tenant->id]));

        Passport::actingAs($user);

        $response = $this->getJson("/api/v1/schedules?origin_city=Abuja&departure_date={$tomorrow}");

        $response->assertStatus(200)->assertJsonCount(1, 'data');
    }
}
