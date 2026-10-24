<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleCategory;
use App\Models\VehicleType;
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

    private function makeVehicleType(): VehicleType
    {
        $category = VehicleCategory::create(['vehicle_category_name' => 'Bus']);
        return VehicleType::create([
            'vehicle_name'        => 'Toyota Hiace',
            'vehicle_category_id' => $category->id,
            'year'                => 2022,
            'status'              => 'active',
        ]);
    }

    private function makeVehicle(int $tenantId, string $reg = 'T1-001'): Vehicle
    {
        return Vehicle::create([
            'tenant_id'           => $tenantId,
            'registration_number' => $reg,
            'status'              => 'active',
        ]);
    }

    public function test_tenant_user_can_create_vehicle()
    {
        $tenant      = Tenant::create(['name' => 'Alpha Transport']);
        $user        = User::factory()->create(['tenant_id' => $tenant->id, 'email_verified_at' => now()]);
        $vehicleType = $this->makeVehicleType();

        Passport::actingAs($user);

        $response = $this->postJson('/api/v1/vehicles', [
            'registration_number' => 'ABC-123',
            'vehicle_type_id'     => $vehicleType->id,
            'status'              => 'active',
        ]);

        $response->assertStatus(201)
                 ->assertJsonFragment(['registration_number' => 'ABC-123'])
                 ->assertJsonPath('data.vehicle_type.id', $vehicleType->id);

        $this->assertDatabaseHas('vehicles', [
            'tenant_id'           => $tenant->id,
            'registration_number' => 'ABC-123',
            'vehicle_type_id'     => $vehicleType->id,
        ]);
    }

    public function test_vehicle_can_be_created_without_a_vehicle_type()
    {
        $tenant = Tenant::create(['name' => 'Alpha Transport']);
        $user   = User::factory()->create(['tenant_id' => $tenant->id, 'email_verified_at' => now()]);

        Passport::actingAs($user);

        $response = $this->postJson('/api/v1/vehicles', [
            'registration_number' => 'NO-TYPE-001',
            'status'              => 'active',
        ]);

        $response->assertStatus(201)
                 ->assertJsonFragment(['registration_number' => 'NO-TYPE-001']);
    }

    public function test_user_without_tenant_is_forbidden_from_creating_vehicle()
    {
        $user = User::factory()->create(['tenant_id' => null, 'email_verified_at' => now()]);
        Passport::actingAs($user);

        $response = $this->postJson('/api/v1/vehicles', [
            'registration_number' => 'XYZ-999',
            'status'              => 'active',
        ]);

        $response->assertStatus(400);
    }

    public function test_tenant_user_can_view_only_their_vehicles()
    {
        $tenant1 = Tenant::create(['name' => 'Tenant 1']);
        $tenant2 = Tenant::create(['name' => 'Tenant 2']);
        $user1   = User::factory()->create(['tenant_id' => $tenant1->id]);

        $this->makeVehicle($tenant1->id, 'T1-001');
        $this->makeVehicle($tenant2->id, 'T2-001');

        Passport::actingAs($user1);
        $response = $this->getJson('/api/v1/vehicles');

        $response->assertStatus(200)
                 ->assertJsonCount(1, 'data')
                 ->assertJsonFragment(['registration_number' => 'T1-001'])
                 ->assertJsonMissing(['registration_number' => 'T2-001']);
    }

    public function test_tenant_user_cannot_update_other_tenants_vehicle()
    {
        $tenant1  = Tenant::create(['name' => 'Tenant 1']);
        $tenant2  = Tenant::create(['name' => 'Tenant 2']);
        $user1    = User::factory()->create(['tenant_id' => $tenant1->id]);
        $vehicle2 = $this->makeVehicle($tenant2->id, 'T2-001');

        Passport::actingAs($user1);
        $response = $this->putJson("/api/v1/vehicles/{$vehicle2->id}", [
            'status' => 'maintenance',
        ]);

        $response->assertStatus(404);
    }

    public function test_tenant_user_can_update_their_own_vehicle()
    {
        $tenant1  = Tenant::create(['name' => 'Tenant 1']);
        $user1    = User::factory()->create(['tenant_id' => $tenant1->id]);
        $vehicle1 = $this->makeVehicle($tenant1->id, 'T1-001');

        Passport::actingAs($user1);
        $response = $this->putJson("/api/v1/vehicles/{$vehicle1->id}", [
            'status' => 'maintenance',
        ]);

        $response->assertStatus(200)
                 ->assertJsonFragment(['status' => 'maintenance']);
    }
}
