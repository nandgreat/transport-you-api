<?php

namespace Tests\Feature;

use App\Models\Driver;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class DriverTest extends TestCase
{
    use RefreshDatabase;

    public function setUp(): void
    {
        parent::setUp();
        $this->artisan('passport:client', ['--personal' => true, '--no-interaction' => true, '--name' => 'Testing Client']);
    }

    private function operatorFor(Tenant $tenant): User
    {
        $operator = User::factory()->create(['tenant_id' => $tenant->id]);
        Passport::actingAs($operator);
        return $operator;
    }

    public function test_operator_can_create_a_driver()
    {
        $tenant = Tenant::create(['name' => 'Operator ' . uniqid()]);
        $this->operatorFor($tenant);

        $this->postJson('/api/v1/drivers', [
            'name'           => 'Musa Bello',
            'phone'          => '08030000009',
            'licence_number' => 'LIC-12345',
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.name', 'Musa Bello')
            ->assertJsonPath('data.status', 'active');

        $this->assertDatabaseHas('drivers', [
            'tenant_id'      => $tenant->id,
            'name'           => 'Musa Bello',
            'licence_number' => 'LIC-12345',
        ]);
    }

    public function test_driver_listing_is_scoped_to_the_operator()
    {
        $mine   = Tenant::create(['name' => 'Mine ' . uniqid()]);
        $other  = Tenant::create(['name' => 'Other ' . uniqid()]);
        $keep   = Driver::create(['tenant_id' => $mine->id, 'name' => 'Mine Driver', 'status' => 'active']);
        $hidden = Driver::create(['tenant_id' => $other->id, 'name' => 'Other Driver', 'status' => 'active']);
        $this->operatorFor($mine);

        $ids = collect($this->getJson('/api/v1/drivers')->assertStatus(200)->json('data'))->pluck('id');

        $this->assertTrue($ids->contains($keep->id));
        $this->assertFalse($ids->contains($hidden->id));
    }

    public function test_duplicate_licence_number_is_rejected_within_an_operator()
    {
        $tenant = Tenant::create(['name' => 'Operator ' . uniqid()]);
        Driver::create(['tenant_id' => $tenant->id, 'name' => 'First', 'licence_number' => 'LIC-DUP', 'status' => 'active']);
        $this->operatorFor($tenant);

        $this->postJson('/api/v1/drivers', ['name' => 'Second', 'licence_number' => 'LIC-DUP'])
            ->assertStatus(422);
    }
}
