<?php

namespace Tests\Feature\Booking;

use App\Enums\FolioStatus;
use App\Models\Folio;
use App\Models\Guest;
use App\Models\Reservation;
use App\Models\ReservationRoom;
use App\Models\Room;
use App\Models\RoomType;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StayTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    /** A reservation with its room type, and optionally an assigned room. */
    private function stayFor(Guest $guest, string $state, bool $assignRoom = false, array $attributes = []): Reservation
    {
        $reservation = Reservation::factory()->{$state}()->create(
            array_merge(['guest_id' => $guest->id], $attributes),
        );

        $roomType = RoomType::factory()->create(['name' => ['en' => 'Deluxe King', 'ar' => 'ديلوكس كينغ']]);
        $room     = $assignRoom
            ? Room::factory()->create(['room_type_id' => $roomType->id, 'number' => '812'])
            : null;

        ReservationRoom::create([
            'reservation_id' => $reservation->id,
            'room_type_id'   => $roomType->id,
            'room_id'        => $room?->id,
            'price_usd'      => 150.00,
        ]);

        return $reservation->fresh();
    }

    // ── Active ────────────────────────────────────────────────────────────

    public function test_active_stay_returns_room_number_and_nights_remaining(): void
    {
        $guest = Guest::factory()->create();
        $this->stayFor($guest, 'checkedIn', assignRoom: true, attributes: [
            'check_in'      => now()->subDays(1)->toDateString(),
            'check_out'     => now()->addDays(3)->toDateString(),
            'checked_in_at' => now()->subDays(1)->setTime(14, 30),
        ]);

        $this->actingAs($guest, 'guests')
            ->getJson('/api/stays/active')
            ->assertOk()
            ->assertJsonPath('data.room_number', '812')
            ->assertJsonPath('data.room_name.en', 'Deluxe King')
            ->assertJsonPath('data.nights', 4)
            ->assertJsonPath('data.nights_remaining', 3)
            ->assertJsonPath('data.dnd.enabled', false);

        $this->assertNotNull(
            $this->actingAs($guest, 'guests')->getJson('/api/stays/active')->json('data.checked_in_at'),
        );
    }

    public function test_no_active_stay_is_an_empty_state_not_an_error(): void
    {
        $this->actingAs(Guest::factory()->create(), 'guests')
            ->getJson('/api/stays/active')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data', null);
    }

    public function test_checking_in_stamps_the_arrival_time(): void
    {
        $guest       = Guest::factory()->create();
        $reservation = $this->stayFor($guest, 'confirmed');
        // AssignRoomAction requires the room to match the booked room type.
        $room = Room::factory()->create([
            'room_type_id' => $reservation->rooms()->first()->room_type_id,
        ]);

        app(\App\Actions\Booking\AssignRoomAction::class)->handle($reservation, $room);

        $this->assertNotNull($reservation->fresh()->checked_in_at);
    }

    // ── Upcoming ──────────────────────────────────────────────────────────

    public function test_upcoming_returns_booking_code_and_price(): void
    {
        $guest = Guest::factory()->create();
        $this->stayFor($guest, 'confirmed', attributes: [
            'check_in'  => now()->addDays(5)->toDateString(),
            'check_out' => now()->addDays(8)->toDateString(),
            'total_usd' => 450.00,
        ]);

        $res = $this->actingAs($guest, 'guests')->getJson('/api/stays/upcoming')->assertOk();

        $this->assertCount(1, $res->json('data'));
        $this->assertNotEmpty($res->json('data.0.booking_code'));
        $this->assertSame('450.00', $res->json('data.0.price_usd'));
        $this->assertSame('Deluxe King', $res->json('data.0.room_name.en'));
        $this->assertTrue($res->json('data.0.is_cancellable'));
        // Rooms are assigned at check-in, so this is null pre-arrival.
        $this->assertNull($res->json('data.0.room_number'));
    }

    public function test_unverified_holds_are_excluded_from_upcoming(): void
    {
        $guest = Guest::factory()->create();
        $this->stayFor($guest, 'pendingVerification', attributes: [
            'check_in'  => now()->addDays(5)->toDateString(),
            'check_out' => now()->addDays(8)->toDateString(),
        ]);

        $this->actingAs($guest, 'guests')
            ->getJson('/api/stays/upcoming')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_cancelling_an_upcoming_stay_reuses_the_reservation_endpoint(): void
    {
        $guest       = Guest::factory()->create();
        $reservation = $this->stayFor($guest, 'confirmed', attributes: [
            'check_in'  => now()->addDays(5)->toDateString(),
            'check_out' => now()->addDays(8)->toDateString(),
        ]);

        $this->actingAs($guest, 'guests')
            ->deleteJson("/api/reservations/{$reservation->uuid}")
            ->assertStatus(204);

        $this->actingAs($guest, 'guests')
            ->getJson('/api/stays/upcoming')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    // ── Past ──────────────────────────────────────────────────────────────

    public function test_past_stays_expose_totals_status_and_book_again_pointer(): void
    {
        $guest       = Guest::factory()->create();
        $reservation = $this->stayFor($guest, 'checkedOut', attributes: ['total_usd' => 300.00]);

        Folio::create([
            'reservation_id' => $reservation->id,
            'status'         => FolioStatus::SETTLED,
            'subtotal_usd'   => 340.00,
            'total_usd'      => 340.00,
            'settled_at'     => now(),
        ]);

        $res = $this->actingAs($guest, 'guests')->getJson('/api/stays/past')->assertOk();

        $item = $res->json('data.items.0');
        $this->assertSame('Deluxe King', $item['room_name']['en']);
        $this->assertSame(3, $item['total_nights']);
        // The folio total wins over the room-only reservation total.
        $this->assertSame('340.00', $item['total_charge_usd']);
        $this->assertSame('checked_out', $item['status']);
        $this->assertTrue($item['has_receipt']);
        $this->assertNotEmpty($item['room_type_uuid']);
    }

    public function test_cancelled_stays_appear_in_past_without_a_receipt(): void
    {
        $guest = Guest::factory()->create();
        $this->stayFor($guest, 'cancelled');

        $res = $this->actingAs($guest, 'guests')->getJson('/api/stays/past')->assertOk();

        $this->assertSame('cancelled', $res->json('data.items.0.status'));
        $this->assertFalse($res->json('data.items.0.has_receipt'));
    }

    public function test_a_guest_never_sees_another_guests_stays(): void
    {
        $this->stayFor(Guest::factory()->create(), 'checkedOut');

        $this->actingAs(Guest::factory()->create(), 'guests')
            ->getJson('/api/stays/past')
            ->assertOk()
            ->assertJsonCount(0, 'data.items');
    }

    // ── Receipt ───────────────────────────────────────────────────────────

    public function test_receipt_returns_folio_items_and_balance(): void
    {
        $guest       = Guest::factory()->create();
        $reservation = $this->stayFor($guest, 'checkedOut');

        $folio = Folio::create([
            'reservation_id' => $reservation->id,
            'status'         => FolioStatus::SETTLED,
            'subtotal_usd'   => 200.00,
            'total_usd'      => 200.00,
            'settled_at'     => now(),
        ]);
        $folio->items()->create([
            'description' => 'Room charge',
            'amount_usd'  => 200.00,
            'source_type' => 'reservation',
            'source_id'   => $reservation->id,
        ]);

        $res = $this->actingAs($guest, 'guests')
            ->getJson("/api/stays/{$reservation->uuid}/receipt")
            ->assertOk();

        $this->assertSame($reservation->booking_code, $res->json('data.reservation.booking_code'));
        $this->assertSame('200.00', $res->json('data.folio.total_usd'));
        $this->assertCount(1, $res->json('data.items'));
        $this->assertEquals(200, $res->json('data.balance_due_usd'));
    }

    public function test_receipt_for_a_stay_with_no_folio_is_404(): void
    {
        $guest       = Guest::factory()->create();
        $reservation = $this->stayFor($guest, 'cancelled');

        $this->actingAs($guest, 'guests')
            ->getJson("/api/stays/{$reservation->uuid}/receipt")
            ->assertNotFound();
    }

    public function test_receipt_of_another_guests_stay_is_404_not_403(): void
    {
        $reservation = $this->stayFor(Guest::factory()->create(), 'checkedOut');
        Folio::create([
            'reservation_id' => $reservation->id,
            'status'         => FolioStatus::SETTLED,
            'total_usd'      => 100.00,
        ]);

        $this->actingAs(Guest::factory()->create(), 'guests')
            ->getJson("/api/stays/{$reservation->uuid}/receipt")
            ->assertNotFound();
    }

    public function test_receipt_pdf_streams_a_pdf(): void
    {
        $guest       = Guest::factory()->create();
        $reservation = $this->stayFor($guest, 'checkedOut');

        $folio = Folio::create([
            'reservation_id' => $reservation->id,
            'status'         => FolioStatus::SETTLED,
            'subtotal_usd'   => 200.00,
            'total_usd'      => 200.00,
            'settled_at'     => now(),
        ]);
        $folio->items()->create([
            'description' => 'Room charge',
            'amount_usd'  => 200.00,
            'source_type' => 'reservation',
            'source_id'   => $reservation->id,
        ]);

        $res = $this->actingAs($guest, 'guests')
            ->get("/api/stays/{$reservation->uuid}/receipt/pdf")
            ->assertOk();

        $this->assertSame('application/pdf', $res->headers->get('Content-Type'));
        $this->assertStringContainsString("receipt-{$reservation->booking_code}.pdf", $res->headers->get('Content-Disposition'));
        $this->assertStringStartsWith('%PDF', $res->getContent());
    }

    public function test_arabic_receipt_pdf_renders(): void
    {
        $guest       = Guest::factory()->create();
        $reservation = $this->stayFor($guest, 'checkedOut');

        $folio = Folio::create([
            'reservation_id' => $reservation->id,
            'status'         => FolioStatus::SETTLED,
            'total_usd'      => 200.00,
        ]);
        $folio->items()->create([
            'description' => 'Room charge',
            'amount_usd'  => 200.00,
            'source_type' => 'reservation',
            'source_id'   => $reservation->id,
        ]);

        $res = $this->actingAs($guest, 'guests')
            ->withHeaders(['Accept-Language' => 'ar'])
            ->get("/api/stays/{$reservation->uuid}/receipt/pdf")
            ->assertOk();

        $this->assertStringStartsWith('%PDF', $res->getContent());
    }

    // ── Do not disturb ────────────────────────────────────────────────────

    public function test_guest_can_toggle_do_not_disturb(): void
    {
        $guest = Guest::factory()->create();
        $this->stayFor($guest, 'checkedIn', assignRoom: true);

        $this->actingAs($guest, 'guests')
            ->patchJson('/api/stays/active/dnd', ['enabled' => true])
            ->assertOk()
            ->assertJsonPath('data.enabled', true);

        $this->actingAs($guest, 'guests')
            ->getJson('/api/stays/active')
            ->assertOk()
            ->assertJsonPath('data.dnd.enabled', true);

        $this->actingAs($guest, 'guests')
            ->patchJson('/api/stays/active/dnd', ['enabled' => false])
            ->assertOk()
            ->assertJsonPath('data.enabled', false)
            ->assertJsonPath('data.until', null);
    }

    public function test_dnd_requires_an_in_progress_stay(): void
    {
        $this->actingAs(Guest::factory()->create(), 'guests')
            ->patchJson('/api/stays/active/dnd', ['enabled' => true])
            ->assertStatus(403);
    }

    // ── Auth ──────────────────────────────────────────────────────────────

    public function test_stay_endpoints_require_a_guest_token(): void
    {
        $this->getJson('/api/stays/active')->assertStatus(401);
        $this->getJson('/api/stays/upcoming')->assertStatus(401);
        $this->getJson('/api/stays/past')->assertStatus(401);
    }
}
