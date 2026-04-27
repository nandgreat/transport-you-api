<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Location;
use App\Models\State;
use Tests\TestCase;

class LocationTest extends TestCase
{
    use RefreshDatabase;

    private function makeState(string $name = 'Lagos', string $code = 'LA'): State
    {
        return State::create(['name' => $name, 'code' => $code]);
    }

    public function test_tenant_user_can_create_location()
    {
        $tenant = Tenant::create(['name' => 'Fast Travel Ltd']);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $state = $this->makeState();

        Passport::actingAs($user);

        $response = $this->postJson('/api/v1/locations', [
            'name'     => 'Central Station',
            'city'     => 'Lagos',
            'state_id' => $state->id,
        ]);

        $response->assertStatus(201)
                 ->assertJsonFragment(['name' => 'Central Station']);

        $this->assertDatabaseHas('locations', [
            'tenant_id' => $tenant->id,
            'name'      => 'Central Station',
            'state_id'  => $state->id,
        ]);
    }

    public function test_tenant_user_can_view_only_their_locations()
    {
        $tenant1 = Tenant::create(['name' => 'Tenant 1']);
        $tenant2 = Tenant::create(['name' => 'Tenant 2']);

        $user1 = User::factory()->create(['tenant_id' => $tenant1->id]);

        $state1 = $this->makeState('Lagos', 'LA');
        $state2 = $this->makeState('Kano', 'KN');

        Location::create(['tenant_id' => $tenant1->id, 'name' => 'T1 Station', 'city' => 'Abuja', 'state_id' => $state1->id]);
        Location::create(['tenant_id' => $tenant2->id, 'name' => 'T2 Station', 'city' => 'Kano',  'state_id' => $state2->id]);

        Passport::actingAs($user1);
        $response = $this->getJson('/api/v1/locations');

        $response->assertStatus(200)
                 ->assertJsonCount(1, 'data.data')
                 ->assertJsonFragment(['name' => 'T1 Station'])
                 ->assertJsonMissing(['name' => 'T2 Station']);
    }
}
