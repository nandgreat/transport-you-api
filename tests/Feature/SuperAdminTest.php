<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Laravel\Passport\Passport;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Role;
use App\Models\Vehicle;
use Tests\TestCase;

class SuperAdminTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    public function test_super_admin_can_view_all_tenant_vehicles()
    {
        $role = Role::create(['name' => 'super_admin', 'tenant_id' => null]);
        $superAdmin = User::factory()->create(['tenant_id' => null]);
        $superAdmin->roles()->attach($role->id);

        $tenant1 = Tenant::create(['name' => 'Tenant 1']);
        $tenant2 = Tenant::create(['name' => 'Tenant 2']);
        
        Vehicle::create(['tenant_id' => $tenant1->id, 'registration_number' => 'T1-001', 'type' => 'Bus', 'capacity' => 10]);
        Vehicle::create(['tenant_id' => $tenant2->id, 'registration_number' => 'T2-001', 'type' => 'Bus', 'capacity' => 20]);

        Passport::actingAs($superAdmin);
        
        $response = $this->getJson('/api/v1/vehicles');

        $response->assertStatus(200)
                 ->assertJsonCount(2, 'data.data'); // Should see both vehicles across all tenants
    }

    public function test_super_admin_can_create_vehicle_for_specific_tenant()
    {
        $role = Role::create(['name' => 'super_admin', 'tenant_id' => null]);
        $superAdmin = User::factory()->create(['tenant_id' => null]);
        $superAdmin->roles()->attach($role->id);

        $tenant = Tenant::create(['name' => 'Target Tenant']);

        Passport::actingAs($superAdmin);

        $response = $this->postJson('/api/v1/vehicles', [
            'tenant_id' => $tenant->id,
            'registration_number' => 'SA-001',
            'type' => 'Bus',
            'capacity' => 30,
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('vehicles', [
            'tenant_id' => $tenant->id,
            'registration_number' => 'SA-001',
        ]);
    }
}
