<?php

namespace Tests\Feature\Loyalty;

use App\Actions\Loyalty\NotifyExpiringLoyaltyPointsAction;
use App\Contracts\FirebaseServiceInterface;
use App\Enums\NotificationType;
use App\Models\DeviceToken;
use App\Models\Guest;
use App\Models\GuestNotification;
use App\Models\LoyaltyEarnBatch;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\Concerns\BuildsLoyaltyFixtures;
use Tests\Support\FakeFirebaseService;
use Tests\TestCase;

/**
 * Phase 10 LOY-10 (Q15, Q22): every guest is warned once per batch, N days
 * ahead, in their language, in one push per guest per run. The marker and the
 * notification row commit together, so a failed push is retried.
 */
class LoyaltyExpiryWarningTest extends TestCase
{
    use BuildsLoyaltyFixtures;
    use RefreshDatabase;

    private FakeFirebaseService $firebase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-10-04 12:00:00', 'UTC'));
        $this->firebase = new FakeFirebaseService;
        $this->app->instance(FirebaseServiceInterface::class, $this->firebase);
        $this->configureLoyalty(['expiry_warning_days' => 30]);
    }

    private function guestWithDevice(?string $locale = null, string $token = 'device-token'): Guest
    {
        $guest = Guest::factory()->create(['preferred_locale' => $locale]);
        DeviceToken::factory()->create(['guest_id' => $guest->id, 'token' => $token]);

        return $guest;
    }

    private function warnings(): Collection
    {
        return GuestNotification::query()
            ->where('type', NotificationType::LOYALTY_POINTS_EXPIRING->value)
            ->orderBy('id')
            ->get();
    }

    public function test_one_push_per_guest_aggregates_the_in_window_batches(): void
    {
        $guest = $this->guestWithDevice();
        $soon = $this->grantPoints($guest, 100, now()->addDays(5));
        $mid = $this->grantPoints($guest, 50, now()->addDays(20));
        $later = $this->grantPoints($guest, 200, now()->addDays(45));
        $gone = $this->grantPoints($guest, 70, now()->subDay());
        $empty = LoyaltyEarnBatch::factory()->depleted()->create([
            'guest_id' => $guest->id,
            'expires_at' => now()->addDays(3),
        ]);

        $this->artisan('loyalty:notify-expiring')
            ->expectsOutputToContain('Warned 1 guest(s)')
            ->assertSuccessful();

        $this->assertCount(1, $this->firebase->pushes);
        $notification = $this->warnings()->sole();
        $this->assertSame($guest->id, $notification->guest_id);
        $this->assertSame(150, $notification->data['points']);
        $this->assertTrue(CarbonImmutable::parse($notification->data['expires_at'])->equalTo(now()->addDays(5)));
        $this->assertSame(150, $this->firebase->pushes[0]['data']['points']);

        $this->assertNotNull($soon->refresh()->expiry_warned_at);
        $this->assertNotNull($mid->refresh()->expiry_warned_at);
        $this->assertNull($later->refresh()->expiry_warned_at);
        $this->assertNull($gone->refresh()->expiry_warned_at);
        $this->assertNull($empty->refresh()->expiry_warned_at);
    }

    public function test_a_batch_is_never_warned_twice_and_a_later_one_is_warned_alone(): void
    {
        $guest = $this->guestWithDevice();
        $this->grantPoints($guest, 100, now()->addDays(5));
        $this->grantPoints($guest, 50, now()->addDays(20));
        $this->grantPoints($guest, 200, now()->addDays(45));

        $this->artisan('loyalty:notify-expiring')->assertSuccessful();
        $this->artisan('loyalty:notify-expiring')->expectsOutputToContain('Warned 0 guest(s)')->assertSuccessful();

        $this->assertCount(1, $this->firebase->pushes);
        $this->assertCount(1, $this->warnings());

        $this->travelTo(now()->addDays(16));
        $this->artisan('loyalty:notify-expiring')->assertSuccessful();

        $this->assertCount(2, $this->firebase->pushes);
        $second = $this->warnings()->last();
        $this->assertSame(200, $second->data['points']);

        $this->artisan('loyalty:notify-expiring')->assertSuccessful();
        $this->assertCount(2, $this->firebase->pushes);
    }

    public function test_the_text_follows_the_guests_locale_and_the_date_is_hotel_local(): void
    {
        config(['hotel.timezone' => 'Asia/Tokyo', 'app.locale' => 'fr']);
        $arabic = $this->guestWithDevice('ar', 'tok-ar');
        $default = $this->guestWithDevice(null, 'tok-default');
        $expiresAt = CarbonImmutable::parse('2026-10-09 20:00:00', 'UTC'); // 2026-10-10 05:00 in Tokyo
        $this->grantPoints($arabic, 120, $expiresAt);
        $this->grantPoints($default, 75, $expiresAt);

        $this->artisan('loyalty:notify-expiring')->assertSuccessful();

        $forArabic = GuestNotification::query()->where('guest_id', $arabic->id)->sole();
        $forDefault = GuestNotification::query()->where('guest_id', $default->id)->sole();

        $this->assertSame(
            __('custom.notifications.loyalty_points_expiring.title', [], 'ar'),
            $forArabic->title,
        );
        $this->assertSame(
            __('custom.notifications.loyalty_points_expiring.body', ['points' => 120, 'date' => '2026-10-10'], 'ar'),
            $forArabic->body,
        );
        $this->assertSame(1, preg_match('/\p{Arabic}/u', $forArabic->title));
        $this->assertSame(1, preg_match('/\p{Arabic}/u', $forArabic->body));
        $this->assertStringContainsString('120', $forArabic->body);
        $this->assertStringContainsString('2026-10-10', $forArabic->body);

        $this->assertSame(
            __('custom.notifications.loyalty_points_expiring.title', [], 'fr'),
            $forDefault->title,
        );
        $this->assertNotSame($forArabic->title, $forDefault->title);
        $this->assertStringContainsString('75', $forDefault->body);
        $this->assertStringContainsString('2026-10-10', $forDefault->body);
    }

    public function test_the_payload_carries_only_points_and_the_expiry_instant(): void
    {
        $guest = $this->guestWithDevice();
        $this->grantPoints($guest, 100, now()->addDays(5));

        $this->artisan('loyalty:notify-expiring')->assertSuccessful();

        $this->assertSame(['points', 'expires_at'], array_keys($this->warnings()->sole()->data));
    }

    public function test_a_guest_without_a_device_token_still_gets_the_row_and_the_markers(): void
    {
        $guest = Guest::factory()->create();
        $batch = $this->grantPoints($guest, 100, now()->addDays(5));

        $this->artisan('loyalty:notify-expiring')->assertSuccessful();

        $this->assertCount(1, $this->warnings());
        $this->assertSame([], $this->firebase->pushes);
        $this->assertNotNull($batch->refresh()->expiry_warned_at);
    }

    public function test_a_push_failure_rolls_back_the_row_and_markers_and_the_next_run_retries(): void
    {
        $guest = $this->guestWithDevice();
        $batch = $this->grantPoints($guest, 100, now()->addDays(5));

        $this->app->instance(FirebaseServiceInterface::class, new class extends FakeFirebaseService
        {
            public function sendPush(array $tokens, string $title, string $body, array $data = []): void
            {
                throw new RuntimeException('Simulated FCM outage');
            }
        });

        $this->artisan('loyalty:notify-expiring')
            ->expectsOutputToContain('1 failure(s)')
            ->assertFailed();

        $this->assertCount(0, $this->warnings());
        $this->assertNull($batch->refresh()->expiry_warned_at);

        $this->app->instance(FirebaseServiceInterface::class, $this->firebase);
        $this->artisan('loyalty:notify-expiring')->assertSuccessful();

        $this->assertCount(1, $this->warnings());
        $this->assertCount(1, $this->firebase->pushes);
        $this->assertNotNull($batch->refresh()->expiry_warned_at);
    }

    public function test_one_guests_failure_does_not_stop_the_next_guest(): void
    {
        $bad = $this->guestWithDevice(null, 'bad-token');
        $good = $this->guestWithDevice(null, 'good-token');
        $badBatch = $this->grantPoints($bad, 100, now()->addDays(5));
        $goodBatch = $this->grantPoints($good, 60, now()->addDays(6));

        $this->app->instance(FirebaseServiceInterface::class, new class extends FakeFirebaseService
        {
            public function sendPush(array $tokens, string $title, string $body, array $data = []): void
            {
                if (in_array('bad-token', $tokens, true)) {
                    throw new RuntimeException('Simulated FCM outage');
                }
                parent::sendPush($tokens, $title, $body, $data);
            }
        });

        $this->artisan('loyalty:notify-expiring')->assertFailed();

        $this->assertNull($badBatch->refresh()->expiry_warned_at);
        $this->assertNotNull($goodBatch->refresh()->expiry_warned_at);
        $this->assertSame([$good->id], $this->warnings()->pluck('guest_id')->all());
    }

    public function test_two_guests_in_the_window_get_two_pushes_in_one_run(): void
    {
        $first = $this->guestWithDevice(null, 'tok-1');
        $second = $this->guestWithDevice(null, 'tok-2');
        $this->grantPoints($first, 100, now()->addDays(5));
        $this->grantPoints($second, 60, now()->addDays(6));

        $this->artisan('loyalty:notify-expiring')->expectsOutputToContain('Warned 2 guest(s)')->assertSuccessful();

        $this->assertCount(2, $this->firebase->pushes);
        $this->assertCount(2, $this->warnings());
    }

    public function test_the_warning_window_is_read_at_run_time(): void
    {
        $guest = $this->guestWithDevice();
        $this->grantPoints($guest, 100, now()->addDays(5));
        $this->grantPoints($guest, 50, now()->addDays(20));

        $this->configureLoyalty(['expiry_warning_days' => 3]);
        $this->artisan('loyalty:notify-expiring')->assertSuccessful();
        $this->assertSame([], $this->firebase->pushes);
        $this->assertCount(0, $this->warnings());

        $this->configureLoyalty(['expiry_warning_days' => 30]);
        $this->artisan('loyalty:notify-expiring')->assertSuccessful();
        $this->assertCount(1, $this->firebase->pushes);
        $this->assertSame(150, $this->warnings()->sole()->data['points']);
    }

    public function test_the_warning_is_scheduled_daily_at_nine_in_the_hotel_timezone_without_overlap(): void
    {
        $events = collect(app(Schedule::class)->events())
            ->filter(fn ($event) => str_contains((string) $event->command, 'loyalty:notify-expiring'))
            ->values();

        $this->assertCount(1, $events);
        $this->assertSame('0 9 * * *', $events[0]->expression);
        $this->assertSame(config('hotel.timezone'), (string) $events[0]->timezone);
        $this->assertTrue($events[0]->withoutOverlapping);
    }

    // ------------------------------------------------------------------ LOY-23: deleted accounts

    public function test_a_deleted_account_is_never_warned_and_an_active_guest_still_is(): void
    {
        // Residue (G-10): a deleted account that still holds a live batch can only
        // come from data written before the deletion forfeit shipped.
        $deleted = Guest::factory()->deleted()->create();
        DeviceToken::factory()->create(['guest_id' => $deleted->id, 'token' => 'tok-deleted']);
        $deletedBatch = $this->grantPoints($deleted, 100, now()->addDays(5));

        $active = $this->guestWithDevice(null, 'tok-active');
        $activeBatch = $this->grantPoints($active, 60, now()->addDays(5));

        $this->artisan('loyalty:notify-expiring')
            ->expectsOutputToContain('Warned 1 guest(s)')
            ->assertSuccessful();

        $this->assertSame([$active->id], $this->warnings()->pluck('guest_id')->all());
        $this->assertSame(0, GuestNotification::query()->where('guest_id', $deleted->id)->count());
        $this->assertCount(1, $this->firebase->pushes);
        $this->assertSame(['tok-active'], $this->firebase->pushes[0]['tokens']);
        $this->assertNull($deletedBatch->refresh()->expiry_warned_at);
        $this->assertNotNull($activeBatch->refresh()->expiry_warned_at);
    }

    public function test_a_run_whose_only_batch_is_on_a_deleted_account_notifies_nobody(): void
    {
        $deleted = Guest::factory()->deleted()->create();
        DeviceToken::factory()->create(['guest_id' => $deleted->id, 'token' => 'tok-deleted']);
        $batch = $this->grantPoints($deleted, 100, now()->addDays(5));

        $result = app(NotifyExpiringLoyaltyPointsAction::class)->handle();

        $this->assertSame(['guests_notified' => 0, 'failures' => 0], $result['data']);
        $this->assertCount(0, $this->warnings());
        $this->assertSame([], $this->firebase->pushes);
        $this->assertNull($batch->refresh()->expiry_warned_at);

        $this->artisan('loyalty:notify-expiring')
            ->expectsOutputToContain('Warned 0 guest(s)')
            ->assertSuccessful();
    }
}
