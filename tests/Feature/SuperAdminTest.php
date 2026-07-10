<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleCategory;
use App\Models\VehicleType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Laravel\Passport\Passport;
use Tests\TestCase;

class SuperAdminTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    private function makeSuperAdmin(): User
    {
        $role       = Role::create(['name' => 'super_admin', 'tenant_id' => null]);
        $superAdmin = User::factory()->create(['tenant_id' => null]);
        $superAdmin->roles()->attach($role->id);
        return $superAdmin;
    }

    private function makeVehicleType(): VehicleType
    {
        $category = VehicleCategory::create(['vehicle_category_name' => 'Bus']);
        return VehicleType::create([
            'vehicle_name'        => 'Toyota Hiace',
            'capacity'            => 14,
            'vehicle_category_id' => $category->id,
            'year'                => 2022,
            'status'              => 'active',
        ]);
    }

    public function test_super_admin_can_view_all_tenant_vehicles()
    {
        $superAdmin = $this->makeSuperAdmin();

        $tenant1 = Tenant::create(['name' => 'Tenant 1']);
        $tenant2 = Tenant::create(['name' => 'Tenant 2']);

        Vehicle::create(['tenant_id' => $tenant1->id, 'registration_number' => 'T1-001', 'status' => 'active']);
        Vehicle::create(['tenant_id' => $tenant2->id, 'registration_number' => 'T2-001', 'status' => 'active']);

        Passport::actingAs($superAdmin);

        $response = $this->getJson('/api/v1/vehicles');

        $response->assertStatus(200)
                 ->assertJsonCount(2, 'data');
    }

    public function test_super_admin_can_create_vehicle_for_specific_tenant()
    {
        $superAdmin  = $this->makeSuperAdmin();
        $tenant      = Tenant::create(['name' => 'Target Tenant']);
        $vehicleType = $this->makeVehicleType();

        Passport::actingAs($superAdmin);

        $response = $this->postJson('/api/v1/vehicles', [
            'tenant_id'           => $tenant->id,
            'registration_number' => 'SA-001',
            'vehicle_type_id'     => $vehicleType->id,
            'status'              => 'active',
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('vehicles', [
            'tenant_id'           => $tenant->id,
            'registration_number' => 'SA-001',
            'vehicle_type_id'     => $vehicleType->id,
        ]);
    }
}
