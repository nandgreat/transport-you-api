<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Mail;
use App\Mail\SendOtpMail;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Role;
use Tests\TestCase;

class TenantOnboardingTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    public function test_tenant_can_onboard_successfully()
    {
        Mail::fake();

        $response = $this->postJson('/api/v1/tenant-onboarding', [
            'company_name' => 'Fast Travel Ltd',
            'company_email' => 'contact@fasttravel.com',
            'company_phone' => '1234567890',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'admin@fasttravel.com',
            'password' => 'secretPassword',
            'password_confirmation' => 'secretPassword',
            'phone' => '0987654321',
        ]);

        $response->assertStatus(201)
                 ->assertJsonStructure(['message']);

        // Assert Tenant created
        $this->assertDatabaseHas('tenants', [
            'name' => 'Fast Travel Ltd',
            'email' => 'contact@fasttravel.com',
        ]);

        $tenant = Tenant::where('name', 'Fast Travel Ltd')->first();

        // Assert User created and linked
        $this->assertDatabaseHas('users', [
            'first_name' => 'John',
            'email' => 'admin@fasttravel.com',
            'tenant_id' => $tenant->id,
        ]);

        $user = User::where('email', 'admin@fasttravel.com')->first();

        // Assert Role created and linked
        $this->assertDatabaseHas('roles', [
            'name' => 'admin',
            'tenant_id' => $tenant->id,
        ]);

        $role = Role::where('tenant_id', $tenant->id)->first();
        $this->assertTrue($user->roles->contains($role));
        
        // Assert OTP created
        $this->assertDatabaseHas('otps', ['user_id' => $user->id]);

        Mail::assertSent(SendOtpMail::class, function ($mail) use ($user) {
            return $mail->hasTo($user->email);
        });
    }

    public function test_tenant_onboarding_requires_unique_company_name()
    {
        Tenant::create(['name' => 'Existing Company']);

        $response = $this->postJson('/api/v1/tenant-onboarding', [
            'company_name' => 'Existing Company',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'admin@fasttravel.com',
            'password' => 'secretPassword',
            'password_confirmation' => 'secretPassword',
        ]);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['company_name']);
    }
}
