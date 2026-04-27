<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Laravel\Passport\Passport;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Location;
use App\Models\Route;
use App\Models\Vehicle;
use App\Models\Schedule;
use Tests\TestCase;

class ScheduleTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    public function test_tenant_user_can_create_schedule()
    {
        $tenant = Tenant::create(['name' => 'Fast Travel Ltd']);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);

        $origin = Location::create(['tenant_id' => $tenant->id, 'name' => 'Origin Term', 'city' => 'City A', 'state' => 'State A']);
        $destination = Location::create(['tenant_id' => $tenant->id, 'name' => 'Dest Term', 'city' => 'City B', 'state' => 'State B']);
        
        $route = Route::create([
            'tenant_id' => $tenant->id,
            'origin_id' => $origin->id,
            'destination_id' => $destination->id,
            'distance' => 100,
            'estimated_duration' => 120,
        ]);

        $vehicle = Vehicle::create([
            'tenant_id' => $tenant->id,
            'registration_number' => 'ABC-123',
            'type' => 'Bus',
            'capacity' => 15,
        ]);

        Passport::actingAs($user);

        $departureTime = now()->addDays(1)->format('Y-m-d H:i:s');
        $arrivalTime = now()->addDays(1)->addHours(2)->format('Y-m-d H:i:s');

        $response = $this->postJson('/api/v1/schedules', [
            'route_id' => $route->id,
            'vehicle_id' => $vehicle->id,
            'departure_time' => $departureTime,
            'arrival_time' => $arrivalTime,
            'fare' => 5000.00,
        ]);

        $response->assertStatus(201)
                 ->assertJsonFragment(['fare' => '5000.00']);

        $this->assertDatabaseHas('schedules', [
            'tenant_id' => $tenant->id,
            'route_id' => $route->id,
            'vehicle_id' => $vehicle->id,
        ]);
    }

    public function test_tenant_cannot_schedule_trip_with_cross_tenant_resources()
    {
        $tenant1 = Tenant::create(['name' => 'Tenant 1']);
        $tenant2 = Tenant::create(['name' => 'Tenant 2']);
        
        $user1 = User::factory()->create(['tenant_id' => $tenant1->id]);
        
        $origin1 = Location::create(['tenant_id' => $tenant1->id, 'name' => 'Orig 1', 'city' => 'C1', 'state' => 'S1']);
        $dest1 = Location::create(['tenant_id' => $tenant1->id, 'name' => 'Dest 1', 'city' => 'C2', 'state' => 'S2']);
        $route1 = Route::create(['tenant_id' => $tenant1->id, 'origin_id' => $origin1->id, 'destination_id' => $dest1->id]);
        
        // Vehicle belongs to tenant 2
        $vehicle2 = Vehicle::create(['tenant_id' => $tenant2->id, 'registration_number' => 'XYZ-999', 'type' => 'Van', 'capacity' => 10]);

        Passport::actingAs($user1);

        $departureTime = now()->addDays(1)->format('Y-m-d H:i:s');

        $response = $this->postJson('/api/v1/schedules', [
            'route_id' => $route1->id,
            'vehicle_id' => $vehicle2->id, // Adversary attempts to map another tenant's vehicle
            'departure_time' => $departureTime,
            'fare' => 3000,
        ]);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['vehicle_id']);
    }
}
