<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Laravel\Passport\Passport;
use Tests\TestCase;

class VehicleTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    public function setUp(): void
    {
        parent::setUp();
        $this->artisan('passport:client', ['--personal' => true, '--no-interaction' => true, '--name' => 'Testing Client']);
    }

    public function test_tenant_user_can_create_vehicle()
    {
        $tenant = Tenant::create(['name' => 'Alpha Transport']);
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'email_verified_at' => now()]);

        Passport::actingAs($user);

        $response = $this->postJson('/api/v1/vehicles', [
            'registration_number' => 'ABC-123',
            'type' => 'Bus',
            'capacity' => 50,
            'status' => 'active',
        ]);

        $response->assertStatus(201)
                 ->assertJsonFragment(['registration_number' => 'ABC-123']);
                 
        $this->assertDatabaseHas('vehicles', [
            'tenant_id' => $tenant->id,
            'registration_number' => 'ABC-123',
        ]);
    }

    public function test_user_without_tenant_is_forbidden_from_creating_vehicle()
    {
        $user = User::factory()->create(['tenant_id' => null, 'email_verified_at' => now()]);
        Passport::actingAs($user);

        $response = $this->postJson('/api/v1/vehicles', [
            'registration_number' => 'XYZ-999',
            'type' => 'Minivan',
            'capacity' => 15,
        ]);

        // Middleware returns 400 (Bad Request) for users without a tenant
        $response->assertStatus(400);
    }

    public function test_tenant_user_can_view_only_their_vehicles()
    {
        $tenant1 = Tenant::create(['name' => 'Tenant 1']);
        $tenant2 = Tenant::create(['name' => 'Tenant 2']);
        
        $user1 = User::factory()->create(['tenant_id' => $tenant1->id]);
        
        Vehicle::create(['tenant_id' => $tenant1->id, 'registration_number' => 'T1-001', 'type' => 'Bus', 'capacity' => 10]);
        Vehicle::create(['tenant_id' => $tenant2->id, 'registration_number' => 'T2-001', 'type' => 'Bus', 'capacity' => 20]);

        Passport::actingAs($user1);
        $response = $this->getJson('/api/v1/vehicles');

        $response->assertStatus(200)
                 ->assertJsonCount(1, 'data.data')
                 ->assertJsonFragment(['registration_number' => 'T1-001'])
                 ->assertJsonMissing(['registration_number' => 'T2-001']);
    }

    public function test_tenant_user_cannot_update_other_tenants_vehicle()
    {
        $tenant1 = Tenant::create(['name' => 'Tenant 1']);
        $tenant2 = Tenant::create(['name' => 'Tenant 2']);
        
        $user1 = User::factory()->create(['tenant_id' => $tenant1->id]);
        $vehicle2 = Vehicle::create(['tenant_id' => $tenant2->id, 'registration_number' => 'T2-001', 'type' => 'Bus', 'capacity' => 20]);

        Passport::actingAs($user1);
        $response = $this->putJson("/api/v1/vehicles/{$vehicle2->id}", [
            'capacity' => 55
        ]);

        // findOrFail will throw 404 because of `where('tenant_id', ...)`
        $response->assertStatus(404);
    }

    public function test_tenant_user_can_update_their_own_vehicle()
    {
        $tenant1 = Tenant::create(['name' => 'Tenant 1']);
        $user1 = User::factory()->create(['tenant_id' => $tenant1->id]);
        $vehicle1 = Vehicle::create(['tenant_id' => $tenant1->id, 'registration_number' => 'T1-001', 'type' => 'Bus', 'capacity' => 10]);

        Passport::actingAs($user1);
        $response = $this->putJson("/api/v1/vehicles/{$vehicle1->id}", [
            'capacity' => 55
        ]);

        $response->assertStatus(200)
                 ->assertJsonFragment(['capacity' => 55]);
    }
}
