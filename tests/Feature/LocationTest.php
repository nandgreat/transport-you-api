<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\Role;
use App\Models\State;
use App\Models\Status;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class LocationTest extends TestCase
{
    use RefreshDatabase;

    public function setUp(): void
    {
        parent::setUp();
        $this->artisan('passport:client', ['--personal' => true, '--no-interaction' => true, '--name' => 'Testing Client']);
    }

    private function makeStatus(string $name = 'active'): Status
    {
        return Status::firstOrCreate(['name' => $name]);
    }

    private function makeState(string $name = 'Lagos', string $code = 'LA'): State
    {
        return State::firstOrCreate(['name' => $name, 'code' => $code]);
    }

    private function makeLocation(array $attributes = []): Location
    {
        $status = $this->makeStatus();
        $state  = $this->makeState();

        return Location::create(array_merge([
            'name'      => 'Central Terminal',
            'city'      => 'Lagos',
            'state_id'  => $state->id,
            'status_id' => $status->id,
        ], $attributes));
    }

    private function makeSuperAdmin(): User
    {
        $role       = Role::firstOrCreate(['name' => 'super_admin', 'tenant_id' => null]);
        $superAdmin = User::factory()->create(['tenant_id' => null]);
        $superAdmin->roles()->attach($role->id);
        return $superAdmin;
    }

    public function test_unauthenticated_user_can_list_locations()
    {
        $this->makeLocation(['name' => 'Terminal A', 'city' => 'Lagos']);
        $this->makeLocation(['name' => 'Terminal B', 'city' => 'Abuja']);

        $response = $this->getJson('/api/v1/locations');

        $response->assertStatus(200)
                 ->assertJsonCount(2, 'data')
                 ->assertJsonFragment(['name' => 'Terminal A'])
                 ->assertJsonFragment(['name' => 'Terminal B']);
    }

    public function test_unauthenticated_user_can_show_location()
    {
        $location = $this->makeLocation(['name' => 'Terminal A', 'city' => 'Lagos']);

        $response = $this->getJson("/api/v1/locations/{$location->id}");

        $response->assertStatus(200)
                 ->assertJsonFragment(['name' => 'Terminal A']);
    }

    public function test_unauthenticated_user_cannot_create_location()
    {
        $state = $this->makeState();

        $response = $this->postJson('/api/v1/locations', [
            'name'     => 'Central Station',
            'city'     => 'Lagos',
            'state_id' => $state->id,
        ]);

        $response->assertStatus(401);
    }

    public function test_authenticated_user_can_list_locations()
    {
        $user = User::factory()->create();
        $this->makeLocation(['name' => 'Terminal A', 'city' => 'Lagos']);
        $this->makeLocation(['name' => 'Terminal B', 'city' => 'Abuja']);

        Passport::actingAs($user);

        $response = $this->getJson('/api/v1/locations');

        $response->assertStatus(200)
                 ->assertJsonCount(2, 'data')
                 ->assertJsonFragment(['name' => 'Terminal A'])
                 ->assertJsonFragment(['name' => 'Terminal B']);
    }

    public function test_can_search_locations_by_search_param_matching_name()
    {
        $user = User::factory()->create();
        $this->makeLocation(['name' => 'Jabi Park', 'city' => 'Abuja']);
        $this->makeLocation(['name' => 'Ojota Motor Park', 'city' => 'Lagos']);

        Passport::actingAs($user);

        $response = $this->getJson('/api/v1/locations?search=Jabi');

        $response->assertStatus(200)
                 ->assertJsonCount(1, 'data')
                 ->assertJsonFragment(['name' => 'Jabi Park'])
                 ->assertJsonMissing(['name' => 'Ojota Motor Park']);
    }

    public function test_can_search_locations_by_search_param_matching_city()
    {
        $user = User::factory()->create();
        $this->makeLocation(['name' => 'Jabi Park', 'city' => 'Abuja']);
        $this->makeLocation(['name' => 'Nyanya Terminal', 'city' => 'Abuja']);
        $this->makeLocation(['name' => 'Ojota Motor Park', 'city' => 'Lagos']);

        Passport::actingAs($user);

        $response = $this->getJson('/api/v1/locations?search=Abuja');

        $response->assertStatus(200)
                 ->assertJsonCount(2, 'data')
                 ->assertJsonFragment(['name' => 'Jabi Park'])
                 ->assertJsonFragment(['name' => 'Nyanya Terminal'])
                 ->assertJsonMissing(['name' => 'Ojota Motor Park']);
    }

    public function test_can_search_locations_by_city_param()
    {
        $user = User::factory()->create();
        $this->makeLocation(['name' => 'Jabi Park', 'city' => 'Abuja']);
        $this->makeLocation(['name' => 'Ojota Motor Park', 'city' => 'Lagos']);

        Passport::actingAs($user);

        $response = $this->getJson('/api/v1/locations?city=Lagos');

        $response->assertStatus(200)
                 ->assertJsonCount(1, 'data')
                 ->assertJsonFragment(['name' => 'Ojota Motor Park'])
                 ->assertJsonMissing(['name' => 'Jabi Park']);
    }

    public function test_can_search_locations_by_name_param()
    {
        $user = User::factory()->create();
        $this->makeLocation(['name' => 'Nyanya Terminal', 'city' => 'Abuja']);
        $this->makeLocation(['name' => 'Ojota Motor Park', 'city' => 'Lagos']);

        Passport::actingAs($user);

        $response = $this->getJson('/api/v1/locations?name=Nyanya');

        $response->assertStatus(200)
                 ->assertJsonCount(1, 'data')
                 ->assertJsonFragment(['name' => 'Nyanya Terminal'])
                 ->assertJsonMissing(['name' => 'Ojota Motor Park']);
    }

    public function test_can_search_locations_by_location_name_alias()
    {
        $user = User::factory()->create();
        $this->makeLocation(['name' => 'Utako Modern Terminal', 'city' => 'Abuja']);
        $this->makeLocation(['name' => 'Ojota Motor Park', 'city' => 'Lagos']);

        Passport::actingAs($user);

        $response = $this->getJson('/api/v1/locations?location_name=Utako');

        $response->assertStatus(200)
                 ->assertJsonCount(1, 'data')
                 ->assertJsonFragment(['name' => 'Utako Modern Terminal'])
                 ->assertJsonMissing(['name' => 'Ojota Motor Park']);
    }

    public function test_search_is_case_insensitive()
    {
        $user = User::factory()->create();
        $this->makeLocation(['name' => 'Central Station', 'city' => 'Port Harcourt']);

        Passport::actingAs($user);

        $response = $this->getJson('/api/v1/locations?search=port harcourt');

        $response->assertStatus(200)
                 ->assertJsonCount(1, 'data')
                 ->assertJsonFragment(['name' => 'Central Station', 'city' => 'Port Harcourt']);
    }

    public function test_search_with_no_matches_returns_empty_list()
    {
        $user = User::factory()->create();
        $this->makeLocation(['name' => 'Central Station', 'city' => 'Lagos']);

        Passport::actingAs($user);

        $response = $this->getJson('/api/v1/locations?search=NonExistentPlace');

        $response->assertStatus(200)
                 ->assertJsonCount(0, 'data');
    }

    public function test_can_paginate_locations()
    {
        $user = User::factory()->create();
        $this->makeLocation(['name' => 'Station 1', 'city' => 'Lagos']);
        $this->makeLocation(['name' => 'Station 2', 'city' => 'Lagos']);
        $this->makeLocation(['name' => 'Station 3', 'city' => 'Lagos']);

        Passport::actingAs($user);

        $response = $this->getJson('/api/v1/locations?per_page=2');

        $response->assertStatus(200)
                 ->assertJsonCount(2, 'data')
                 ->assertJsonPath('pagination.per_page', 2)
                 ->assertJsonPath('pagination.total', 3);
    }

    public function test_super_admin_can_create_location()
    {
        $admin = $this->makeSuperAdmin();
        $state = $this->makeState();
        $this->makeStatus();

        Passport::actingAs($admin);

        $response = $this->postJson('/api/v1/locations', [
            'name'     => 'Central Station',
            'city'     => 'Lagos',
            'state_id' => $state->id,
        ]);

        $response->assertStatus(201)
                 ->assertJsonFragment(['name' => 'Central Station']);

        $this->assertDatabaseHas('locations', [
            'name'     => 'Central Station',
            'city'     => 'Lagos',
            'state_id' => $state->id,
        ]);
    }
}

