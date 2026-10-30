<?php

namespace Tests\Feature;

use App\Mail\TicketIssued;
use App\Models\Booking;
use App\Models\Location;
use App\Models\Route;
use App\Models\Schedule;
use App\Models\SeatArrangementType;
use App\Models\State;
use App\Models\Status;
use App\Models\Tenant;
use App\Models\User;
use App\Models\VehicleCategory;
use App\Models\VehicleType;
use App\Services\BookingService;
use App\Services\TicketService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Passport\Passport;
use Tests\TestCase;

class TicketTest extends TestCase
{
    use RefreshDatabase;

    public function setUp(): void
    {
        parent::setUp();
        $this->artisan('passport:client', ['--personal' => true, '--no-interaction' => true, '--name' => 'Testing Client']);
    }

    private function makeSchedule(): Schedule
    {
        $state  = State::firstOrCreate(['name' => 'Lagos'], ['code' => 'LA']);
        $status = Status::firstOrCreate(['name' => 'active']);

        $category    = VehicleCategory::firstOrCreate(['vehicle_category_name' => 'Bus']);
        $vehicleType = VehicleType::firstOrCreate(
            ['vehicle_name' => 'Toyota Hiace', 'vehicle_category_id' => $category->id, 'year' => 2022],
            ['status' => 'active']
        );
        $arrangement = SeatArrangementType::firstOrCreate(
            ['name' => 'Test 30 Seater'],
            ['number_of_seats' => 30, 'status' => 'active']
        );
        $vehicleType->seatArrangementTypes()->syncWithoutDetaching([$arrangement->id => ['is_default' => true]]);

        $tenant = Tenant::create(['name' => 'Test Operator ' . uniqid()]);
        $origin = Location::create(['name' => 'Origin', 'city' => 'City A', 'state_id' => $state->id, 'status_id' => $status->id]);
        $dest   = Location::create(['name' => 'Dest',   'city' => 'City B', 'state_id' => $state->id, 'status_id' => $status->id]);
        $route  = Route::create(['tenant_id' => $tenant->id, 'origin_id' => $origin->id, 'destination_id' => $dest->id, 'distance' => 200, 'estimated_duration' => 180]);

        return Schedule::create([
            'tenant_id'       => $tenant->id,
            'route_id'        => $route->id,
            'vehicle_type_id' => $vehicleType->id,
            'departure_time'  => now()->addDay(),
            'arrival_time'    => now()->addDay()->addHours(3),
            'fare'            => 4500,
            'status'          => 'scheduled',
        ]);
    }

    private function pendingBooking(Schedule $schedule, User $customer): Booking
    {
        return Booking::create([
            'tenant_id'    => $schedule->tenant_id,
            'user_id'      => $customer->id,
            'schedule_id'  => $schedule->id,
            'total_amount' => 9000,
            'passengers'   => [
                ['name' => 'Ada Obi', 'phone' => '08030000001', 'seat_number' => 1],
                ['name' => 'Bola Eze', 'phone' => '08030000002', 'seat_number' => 2],
            ],
            'status'       => 'pending',
        ]);
    }

    public function test_booking_gets_a_unique_reference_at_creation()
    {
        $schedule = $this->makeSchedule();
        $customer = User::factory()->create();

        $booking = $this->pendingBooking($schedule, $customer);

        $this->assertNotEmpty($booking->reference);
        $this->assertStringStartsWith('TY-', $booking->reference);
    }

    public function test_confirming_a_booking_issues_one_qr_ticket_per_passenger_and_emails_them()
    {
        Mail::fake();

        $schedule = $this->makeSchedule();
        $customer = User::factory()->create(['email' => 'customer@example.com']);
        $booking  = $this->pendingBooking($schedule, $customer);

        // Confirm as the operator (tenant user) — mimics a cash/offline payment.
        app(BookingService::class)->updateStatus(
            userId: $customer->id,
            tenantId: $schedule->tenant_id,
            isSuperAdmin: true,
            id: (string) $booking->id,
            data: ['status' => 'confirmed'],
        );

        $tickets = $booking->fresh()->tickets;
        $this->assertCount(2, $tickets);
        $this->assertEquals($booking->reference . '-1', $tickets[0]->qr_code);
        $this->assertEquals('valid', $tickets[0]->status);
        $this->assertEquals(1, $tickets[0]->seat_number);

        Mail::assertSent(TicketIssued::class, fn ($mail) => $mail->hasTo('customer@example.com'));
    }

    public function test_issuing_is_idempotent()
    {
        Mail::fake();

        $schedule = $this->makeSchedule();
        $customer = User::factory()->create();
        $booking  = $this->pendingBooking($schedule, $customer);

        $service = app(TicketService::class);
        $service->issueForBooking($booking);
        $service->issueForBooking($booking->fresh());

        $this->assertCount(2, $booking->fresh()->tickets);
    }

    public function test_qr_image_is_a_png()
    {
        $schedule = $this->makeSchedule();
        $customer = User::factory()->create();
        $booking  = $this->pendingBooking($schedule, $customer);
        $ticket   = app(TicketService::class)->issueForBooking($booking)->first();

        $png = $ticket->qrPng();
        // PNG magic bytes.
        $this->assertStringStartsWith("\x89PNG", $png);
        $this->assertStringStartsWith('data:image/png;base64,', $ticket->qrDataUri());
    }

    public function test_ticket_email_renders_with_inline_qr()
    {
        $schedule = $this->makeSchedule();
        $customer = User::factory()->create();
        $booking  = $this->pendingBooking($schedule, $customer);
        $tickets  = app(TicketService::class)->issueForBooking($booking);

        $html = (new TicketIssued($booking->fresh(), $tickets))->render();

        $this->assertStringContainsString($booking->reference, $html);
        $this->assertStringContainsString('Ada Obi', $html);
        $this->assertStringContainsString('cid:', $html); // inline embedded image
    }

    public function test_verify_and_check_in_endpoints()
    {
        $schedule = $this->makeSchedule();
        $customer = User::factory()->create();
        $booking  = $this->pendingBooking($schedule, $customer);
        $ticket   = app(TicketService::class)->issueForBooking($booking)->first();

        // Operator (tenant user) verifies and checks in.
        $operator = User::factory()->create(['tenant_id' => $schedule->tenant_id]);
        Passport::actingAs($operator);

        $this->getJson("/api/v1/tickets/verify/{$ticket->qr_code}")
            ->assertStatus(200)
            ->assertJsonPath('data.valid', true);

        $this->patchJson("/api/v1/tickets/{$ticket->qr_code}/check-in")
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'used');

        // Second check-in is rejected.
        $this->patchJson("/api/v1/tickets/{$ticket->qr_code}/check-in")
            ->assertStatus(400);
    }
}
