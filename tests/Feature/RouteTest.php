<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Laravel\Passport\Passport;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Location;
use App\Models\Route;
use Tests\TestCase;

class RouteTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    public function test_tenant_user_can_create_route_with_locations()
    {
        $tenant = Tenant::create(['name' => 'Fast Travel Ltd']);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);

        $origin = Location::create(['tenant_id' => $tenant->id, 'name' => 'Lagos Terminal', 'city' => 'Lagos', 'state' => 'Lagos']);
        $destination = Location::create(['tenant_id' => $tenant->id, 'name' => 'Abuja Terminal', 'city' => 'Abuja', 'state' => 'FCT']);

        Passport::actingAs($user);

        $response = $this->postJson('/api/v1/routes', [
            'origin_id' => $origin->id,
            'destination_id' => $destination->id,
            'distance' => 750.5,
            'estimated_duration' => 600,
        ]);

        $response->assertStatus(201)
                 ->assertJsonFragment(['distance' => 750.5]);

        $this->assertDatabaseHas('routes', [
            'tenant_id' => $tenant->id,
            'origin_id' => $origin->id,
            'destination_id' => $destination->id,
        ]);
    }

    public function test_tenant_cannot_create_route_with_cross_tenant_location()
    {
        $tenant1 = Tenant::create(['name' => 'Tenant 1']);
        $tenant2 = Tenant::create(['name' => 'Tenant 2']);
        
        $user1 = User::factory()->create(['tenant_id' => $tenant1->id]);
        
        $origin = Location::create(['tenant_id' => $tenant1->id, 'name' => 'Lagos Terminal 1', 'city' => 'Lagos', 'state' => 'Lagos']);
        // destination belongs to tenant 2
        $destination = Location::create(['tenant_id' => $tenant2->id, 'name' => 'Abuja Terminal 2', 'city' => 'Abuja', 'state' => 'FCT']);

        Passport::actingAs($user1);

        $response = $this->postJson('/api/v1/routes', [
            'origin_id' => $origin->id,
            'destination_id' => $destination->id,
        ]);

        // It should fail validation because destination_id is restricted to tenant_id of user 1 natively via StoreRouteRequest Rule::exists
        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['destination_id']);
    }
}
