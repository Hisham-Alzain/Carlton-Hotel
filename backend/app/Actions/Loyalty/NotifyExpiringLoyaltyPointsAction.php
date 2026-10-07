<?php

namespace App\Actions\Loyalty;

use App\Enums\GuestAccountStatus;
use App\Enums\LoyaltyBatchStatus;
use App\Enums\NotificationType;
use App\Models\Guest;
use App\Models\LoyaltyEarnBatch;
use App\Services\Notification\NotificationService;
use App\Support\HotelClock;
use App\Support\LoyaltyProgram;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Warns each guest once per batch before their points expire (Phase 10,
 * LOY-10, Q15, Q22).
 *
 * One push per guest per run, aggregating every unwarned batch that expires
 * within `expiry_warning_days` (read at run time, so a later change applies to
 * the next run). The notification row, the push and the `expiry_warned_at`
 * markers share one transaction under the guest lock (M-6): if the push throws,
 * the row and the markers roll back together and the next run retries; a
 * marked batch is never warned again. One guest's failure is contained to that
 * guest (at-least-once delivery, no duplicates thanks to the marker).
 *
 * The payload carries only the point total and the earliest expiry instant.
 *
 * Deleted accounts (9.1) are never warned (LOY-23): their balance is forfeited
 * at deletion (10-16), and this is the guard for any residue or for an account
 * deleted between the guest list and the guest lock.
 */
class NotifyExpiringLoyaltyPointsAction
{
    public function __construct(private readonly NotificationService $notifications) {}

    /**
     * @return array{data: array{guests_notified: int, failures: int}, code: int}
     */
    public function handle(): array
    {
        $now = now();
        $horizon = $now->copy()->addDays(LoyaltyProgram::current()->expiryWarningDays());

        $notified = 0;
        $failures = 0;

        $guestIds = $this->window($now, $horizon)->distinct()->orderBy('guest_id')->pluck('guest_id');

        foreach ($guestIds as $guestId) {
            try {
                if ($this->warn((int) $guestId, $now, $horizon)) {
                    $notified++;
                }
            } catch (Throwable $e) {
                $failures++;
                logger()->error('Loyalty expiry warning failed', ['guest_id' => $guestId, 'exception' => $e->getMessage()]);
            }
        }

        return ['data' => ['guests_notified' => $notified, 'failures' => $failures], 'code' => 200];
    }

    /** Unwarned, still-spendable batches of live accounts that expire after `$now` and on or before `$horizon`. */
    private function window(CarbonInterface $now, CarbonInterface $horizon): Builder
    {
        return LoyaltyEarnBatch::query()
            ->whereDoesntHave('guest', fn (Builder $guest) => $guest->where('account_status', GuestAccountStatus::DELETED->value))
            ->where('status', LoyaltyBatchStatus::ACTIVE->value)
            ->whereNull('expiry_warned_at')
            ->where('points_remaining', '>', 0)
            ->where('expires_at', '>', $now)
            ->where('expires_at', '<=', $horizon);
    }

    /** @return bool whether a warning was sent (false when nothing was left to warn about) */
    private function warn(int $guestId, CarbonInterface $now, CarbonInterface $horizon): bool
    {
        return DB::transaction(function () use ($guestId, $now, $horizon): bool {
            $guest = Guest::query()->whereKey($guestId)->lockForUpdate()->first();
            if ($guest === null || $guest->isDeleted()) {
                return false;
            }

            $batches = $this->window($now, $horizon)
                ->where('guest_id', $guestId)
                ->orderBy('expires_at')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            if ($batches->isEmpty()) {
                return false;
            }

            $points = (int) $batches->sum('points_remaining');
            $earliest = $batches->first()->expires_at;
            $locale = $guest->preferred_locale ?: config('app.locale');

            $this->notifications->pushToGuest(
                $guest,
                NotificationType::LOYALTY_POINTS_EXPIRING,
                __('custom.notifications.loyalty_points_expiring.title', [], $locale),
                __('custom.notifications.loyalty_points_expiring.body', [
                    'points' => $points,
                    'date' => $earliest->copy()->setTimezone(HotelClock::timezone())->format('Y-m-d'),
                ], $locale),
                ['points' => $points, 'expires_at' => $earliest->toIso8601String()],
            );

            foreach ($batches as $batch) {
                $batch->expiry_warned_at = $now;
                $batch->save();
            }

            return true;
        });
    }
}
