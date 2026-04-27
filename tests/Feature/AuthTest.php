<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Otp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Mail;
use App\Mail\SendOtpMail;
use Tests\TestCase;
use Laravel\Passport\Passport;

class AuthTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    public function setUp(): void
    {
        parent::setUp();
        $this->artisan('passport:client', ['--personal' => true, '--no-interaction' => true, '--name' => 'Testing Client']);
    }

    public function test_user_can_register_and_receive_otp(): void
    {
        Mail::fake();

        $response = $this->postJson('/api/v1/register', [
            'first_name'            => 'John',
            'last_name'             => 'Doe',
            'email'                 => 'john@example.com',
            'password'              => 'password',
            'password_confirmation' => 'password',
            'phone'                 => '1234567890',
        ]);

        $response->assertStatus(201)
                 ->assertJsonStructure(['status', 'message', 'data']);

        $this->assertDatabaseHas('users', ['email' => 'john@example.com']);

        $user = User::where('email', 'john@example.com')->first();
        $this->assertDatabaseHas('otps', ['user_id' => $user->id]);

        Mail::assertSent(SendOtpMail::class, fn($mail) => $mail->hasTo($user->email));
    }

    public function test_user_can_verify_otp(): void
    {
        $user = User::factory()->unverified()->create();
        $otp  = Otp::create(['user_id' => $user->id, 'otp' => '123456', 'expires_at' => now()->addMinutes(15)]);

        $response = $this->postJson('/api/v1/verify-otp', ['email' => $user->email, 'otp' => '123456']);

        $response->assertStatus(200)
                 ->assertJsonStructure(['status', 'message', 'data' => ['user', 'token']]);

        $this->assertNotNull($user->fresh()->email_verified_at);
        $this->assertDatabaseMissing('otps', ['id' => $otp->id]);
    }

    public function test_unverified_user_cannot_login(): void
    {
        $user = User::factory()->unverified()->create(['password' => bcrypt('password')]);

        $response = $this->postJson('/api/v1/login', ['email' => $user->email, 'password' => 'password']);

        $response->assertStatus(422)->assertJsonValidationErrors(['email']);
    }

    public function test_verified_user_can_login(): void
    {
        $user = User::factory()->create(['password' => bcrypt('password'), 'status' => 'active']);

        $response = $this->postJson('/api/v1/login', ['email' => $user->email, 'password' => 'password']);

        $response->assertStatus(200)
                 ->assertJsonStructure(['status', 'message', 'data' => ['user', 'token']]);
    }

    public function test_inactive_user_cannot_login(): void
    {
        $user = User::factory()->create(['password' => bcrypt('password'), 'status' => 'inactive']);

        $response = $this->postJson('/api/v1/login', ['email' => $user->email, 'password' => 'password']);

        $response->assertStatus(422)->assertJsonValidationErrors(['email']);
    }

    public function test_user_can_get_profile(): void
    {
        $user = User::factory()->create();
        Passport::actingAs($user);

        $response = $this->getJson('/api/v1/user');

        $response->assertStatus(200)
                 ->assertJsonPath('data.email', $user->email);
    }
}
