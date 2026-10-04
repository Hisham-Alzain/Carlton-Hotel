<?php

namespace Tests\Feature\Auth;

use App\Actions\Guest\DeleteGuestAccountAction;
use App\Enums\FolioStatus;
use App\Enums\GuestAccountStatus;
use App\Enums\ServiceBookingStatus;
use App\Exceptions\GuestAccountDeletionBlockedException;
use App\Models\DeviceToken;
use App\Models\Folio;
use App\Models\Guest;
use App\Models\GuestNote;
use App\Models\Reservation;
use App\Models\ServiceBooking;
use App\Support\HotelClock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 9.1 (D-07): DeleteGuestAccountAction refuses while the guest has a
 * live stay, an open folio or an upcoming service booking — and writes
 * nothing when it refuses. Stale/no-show and unverified stays never block.
 */
class GuestAccountDeletionGuardTest extends TestCase
{
    use RefreshDatabase;

    private Guest $guest;

    protected function setUp(): void
    {
        parent::setUp();
        $this->guest = Guest::factory()->create();
    }

    private function today(): string
    {
        return HotelClock::today()->toDateString();
    }

    private function days(int $offset): string
    {
        return HotelClock::today()->addDays($offset)->toDateString();
    }

    private function reservation(string $state, array $attributes = []): Reservation
    {
        return Reservation::factory()->{$state}()->create(array_merge(['guest_id' => $this->guest->id], $attributes));
    }

    /** @return array{reasons: array, booking_codes: array}|null */
    private function blockedContext(): ?array
    {
        try {
            app(DeleteGuestAccountAction::class)->handle($this->guest);
        } catch (GuestAccountDeletionBlockedException $e) {
            return $e->context();
        }

        return null;
    }

    private function assertBlocked(array $reasons): array
    {
        $context = $this->blockedContext();
        $this->assertNotNull($context, 'Expected the deletion to be blocked.');
        $this->assertSame($reasons, $context['reasons']);

        return $context;
    }

    private function assertNotBlocked(): void
    {
        $this->assertNull($this->blockedContext(), 'Expected the deletion to go through.');
        $this->assertTrue($this->guest->fresh()->isDeleted());
    }

    public function test_checked_in_stay_blocks_even_past_its_check_out(): void
    {
        $r = $this->reservation('checkedIn', ['check_in' => $this->days(-5), 'check_out' => $this->days(-1)]);

        $context = $this->assertBlocked(['active_reservation']);
        $this->assertSame([$r->booking_code], $context['booking_codes']);
    }

    public function test_confirmed_arrival_today_blocks(): void
    {
        $this->reservation('confirmed', ['check_in' => $this->today(), 'check_out' => $this->days(2)]);

        $this->assertBlocked(['active_reservation']);
    }

    public function test_pending_upcoming_stay_blocks(): void
    {
        $this->reservation('confirmed', ['status' => 'pending', 'check_in' => $this->days(10), 'check_out' => $this->days(12)]);

        $this->assertBlocked(['active_reservation']);
    }

    public function test_stale_confirmed_no_show_does_not_block(): void
    {
        $this->reservation('confirmed', ['check_in' => $this->days(-3), 'check_out' => $this->days(-1)]);

        $this->assertNotBlocked();
    }

    public function test_pending_verification_does_not_block(): void
    {
        $this->reservation('pendingVerification', ['check_in' => $this->days(3), 'check_out' => $this->days(5)]);

        $this->assertNotBlocked();
    }

    public function test_cancelled_does_not_block(): void
    {
        $this->reservation('cancelled', ['check_in' => $this->days(3), 'check_out' => $this->days(5)]);

        $this->assertNotBlocked();
    }

    public function test_checked_out_with_settled_folio_does_not_block(): void
    {
        $r = $this->reservation('checkedOut');
        Folio::factory()->create(['reservation_id' => $r->id, 'status' => FolioStatus::SETTLED, 'settled_at' => now()]);

        $this->assertNotBlocked();
    }

    public function test_checked_out_with_open_folio_blocks(): void
    {
        $r = $this->reservation('checkedOut');
        Folio::factory()->create(['reservation_id' => $r->id, 'status' => FolioStatus::OPEN]);

        $context = $this->assertBlocked(['open_folio']);
        $this->assertSame([$r->booking_code], $context['booking_codes']);
    }

    public function test_upcoming_pending_service_booking_blocks(): void
    {
        $r = $this->reservation('cancelled');
        ServiceBooking::factory()->create([
            'guest_id' => $this->guest->id, 'reservation_id' => $r->id,
            'scheduled_at' => now()->addDay(), 'status' => ServiceBookingStatus::PENDING,
        ]);

        $this->assertBlocked(['upcoming_service_booking']);
    }

    public function test_completed_or_past_service_bookings_do_not_block(): void
    {
        $r = $this->reservation('cancelled');
        ServiceBooking::factory()->create([
            'guest_id' => $this->guest->id, 'reservation_id' => $r->id,
            'scheduled_at' => now()->addDay(), 'status' => ServiceBookingStatus::COMPLETED,
        ]);
        ServiceBooking::factory()->create([
            'guest_id' => $this->guest->id, 'reservation_id' => $r->id,
            'scheduled_at' => now()->subHour(), 'status' => ServiceBookingStatus::CONFIRMED,
        ]);

        $this->assertNotBlocked();
    }

    public function test_all_reasons_are_collected_in_fixed_order(): void
    {
        $live = $this->reservation('confirmed', ['check_in' => $this->days(1), 'check_out' => $this->days(3)]);
        $done = $this->reservation('checkedOut');
        Folio::factory()->create(['reservation_id' => $done->id, 'status' => FolioStatus::OPEN]);
        ServiceBooking::factory()->create([
            'guest_id' => $this->guest->id, 'reservation_id' => $live->id,
            'scheduled_at' => now()->addDays(2), 'status' => ServiceBookingStatus::CONFIRMED,
        ]);

        $context = $this->assertBlocked(['active_reservation', 'open_folio', 'upcoming_service_booking']);

        $expected = [$live->booking_code, $done->booking_code];
        sort($expected);
        $this->assertSame($expected, $context['booking_codes']);
    }

    public function test_booking_codes_are_sorted_and_capped_at_ten(): void
    {
        $codes = [];
        for ($i = 0; $i < 12; $i++) {
            $codes[] = $this->reservation('confirmed', ['check_in' => $this->days(1 + $i), 'check_out' => $this->days(2 + $i)])->booking_code;
        }
        sort($codes);

        $context = $this->assertBlocked(['active_reservation']);
        $this->assertSame(array_slice($codes, 0, 10), $context['booking_codes']);
    }

    public function test_a_block_writes_nothing(): void
    {
        $this->reservation('checkedIn', ['check_in' => $this->days(-1), 'check_out' => $this->days(1)]);
        $this->guest->createToken('a');
        DeviceToken::factory()->create(['guest_id' => $this->guest->id]);
        GuestNote::factory()->create(['guest_id' => $this->guest->id]);
        $before = $this->guest->fresh()->getAttributes();

        $this->assertBlocked(['active_reservation']);

        $after = $this->guest->fresh();
        $this->assertSame($before, $after->getAttributes());
        $this->assertSame(GuestAccountStatus::ACTIVE, $after->account_status);
        $this->assertSame(1, $after->tokens()->count());
        $this->assertSame(1, $after->deviceTokens()->count());
        $this->assertSame(1, $after->notes()->count());
    }

    /** @return array<string, array{string}> */
    public static function locales(): array
    {
        return ['en' => ['en'], 'ar' => ['ar'], 'fr' => ['fr'], 'tr' => ['tr'], 'es' => ['es']];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('locales')]
    public function test_blocked_message_is_translated(string $locale): void
    {
        app()->setLocale($locale);
        $text = __('custom.errors.guest_account_deletion_blocked');

        $this->assertNotSame('custom.errors.guest_account_deletion_blocked', $text);
        $this->assertNotSame('', trim($text));
    }
}
