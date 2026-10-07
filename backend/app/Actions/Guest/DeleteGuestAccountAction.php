<?php

namespace App\Actions\Guest;

use App\Actions\Loyalty\ForfeitLoyaltyBalanceAction;
use App\Enums\ConversationStatus;
use App\Enums\FolioStatus;
use App\Enums\GuestAccountStatus;
use App\Enums\MessageSender;
use App\Enums\ReservationStatus;
use App\Enums\ServiceBookingStatus;
use App\Exceptions\GuestAccountDeletionBlockedException;
use App\Models\Conversation;
use App\Models\Guest;
use App\Models\GuestDocument;
use App\Models\Message;
use App\Models\OtpCode;
use App\Models\Reservation;
use App\Models\ServiceBooking;
use App\Support\GuestEntitlement;
use App\Support\HotelClock;
use App\Traits\FileTrait;
use App\Traits\MirrorsToFirestore;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Spatie\Activitylog\Models\Activity;
use Throwable;

/**
 * Guest-initiated account deletion (Phase 9.1, D-05..D-09).
 *
 * Never a hard delete: the guests row anchors reservations, folios, payments,
 * tickets and reviews, and several FKs cascade (D-05). The row is anonymized
 * in place and flagged `account_status = deleted`.
 *
 * One transaction; the guest row is locked first (lock order: guests, then
 * everything else), so two racing requests erase once (D-09). Guards run under
 * that lock and collect every reason (D-07). The scrub runs with automatic
 * model logging off, so it writes no old PII; afterwards the guest's audit
 * rows are redacted and one `guest.account_deleted` entry is written (D-08).
 * File deletion and the Firestore re-mirror run after commit and are
 * best-effort (R-4).
 *
 * Phase 10 gap (LOY-23): the same transaction forfeits the loyalty balance as
 * `expire` entries and closes every active voucher, via
 * ForfeitLoyaltyBalanceAction; the audit entry carries the counts only. The
 * already-deleted early return keeps a repeat call a no-op.
 */
class DeleteGuestAccountAction
{
    use FileTrait, MirrorsToFirestore;

    public function __construct(private readonly ForfeitLoyaltyBalanceAction $forfeitLoyalty) {}

    /** Guest columns cleared on erasure (D-08). */
    private const PII_COLUMNS = [
        'name', 'first_name', 'last_name', 'phone', 'phone_country', 'phone_verified_at',
        'email', 'email_verified_at', 'bed_type', 'pillow_type', 'floor_preference',
        'preferences_other', 'preferences_updated_at',
    ];

    /** Keys removed from the guest's audit rows (D-08, R-3). */
    private const GUEST_LOG_KEYS = [
        'name', 'first_name', 'last_name', 'phone', 'phone_country', 'email',
        'bed_type', 'floor_preference', 'preferences_updated_at',
    ];

    private const MAX_BOOKING_CODES = 10;

    public function handle(Guest $guest): array
    {
        $files = [];
        $redacted = [];
        $loyalty = ['forfeited_points' => 0, 'expired_batches' => 0, 'closed_vouchers' => 0];

        DB::transaction(function () use ($guest, &$files, &$redacted, &$loyalty): void {
            $locked = Guest::whereKey($guest->id)->lockForUpdate()->first();

            if ($locked === null || $locked->isDeleted()) {
                return;
            }

            $this->assertDeletable($locked);

            $identifiers = array_values(array_filter([$locked->phone, $locked->email]));
            $reservations = $locked->reservations()->pluck('id')->all();

            activity()->withoutLogging(function () use ($locked, $identifiers, &$files, &$redacted, &$loyalty): void {
                // First, after assertDeletable (a blocked deletion forfeits nothing): the batch and voucher
                // locks follow the guest lock before any other write (guest -> batches -> vouchers), and
                // model logging is off, so the voucher changes add no activity row (D-08: one entry).
                $loyalty = $this->forfeitLoyalty->handle($locked)['data'];
                $this->scrubIdentity($locked);
                $this->revokeAccess($locked);
                $this->purgePersonal($locked, $identifiers);
                Reservation::where('guest_id', $locked->id)->update(['phone' => null]);
                $files = $this->pruneDocuments($locked);
                $redacted = $this->redactChat($locked, $files);
            });

            $this->redactActivityLog(Guest::class, [$locked->id], self::GUEST_LOG_KEYS);
            $this->redactActivityLog(Reservation::class, $reservations, ['phone']);
            $this->redactActivityLog(Message::class, array_column($redacted, 'id'), ['body', 'attachment_path']);

            activity()
                ->performedOn($locked)
                ->causedBy($locked)
                ->event('account_deleted')
                ->withProperties([
                    'retained' => [
                        'reservations' => count($reservations),
                        'documents' => GuestDocument::where('guest_id', $locked->id)->count(),
                    ],
                    'loyalty' => $loyalty,
                ])
                ->log('guest.account_deleted');

            DB::afterCommit(fn () => $this->afterCommit($files, $redacted));
        });

        return ['data' => null, 'code' => 200];
    }

    /** D-07: every blocking reason, in fixed order, with the offending booking codes. */
    private function assertDeletable(Guest $guest): void
    {
        $today = HotelClock::today();

        $live = GuestEntitlement::constrainLive(Reservation::where('guest_id', $guest->id), $today)
            ->reorder()->orderBy('booking_code')->limit(self::MAX_BOOKING_CODES)->pluck('booking_code');

        $openFolio = Reservation::where('guest_id', $guest->id)
            ->whereHas('folio', fn ($q) => $q->where('status', FolioStatus::OPEN))
            ->orderBy('booking_code')->limit(self::MAX_BOOKING_CODES)->pluck('booking_code');

        $upcoming = ServiceBooking::where('guest_id', $guest->id)
            ->whereIn('status', [ServiceBookingStatus::PENDING, ServiceBookingStatus::CONFIRMED])
            ->where('scheduled_at', '>=', now())
            ->exists();

        $reasons = array_keys(array_filter([
            'active_reservation' => $live->isNotEmpty(),
            'open_folio' => $openFolio->isNotEmpty(),
            'upcoming_service_booking' => $upcoming,
        ]));

        if ($reasons === []) {
            return;
        }

        $codes = $live->merge($openFolio)->unique()->sort()->values()->take(self::MAX_BOOKING_CODES)->all();

        throw new GuestAccountDeletionBlockedException(__('custom.errors.guest_account_deletion_blocked'), [
            'reasons' => $reasons,
            'booking_codes' => $codes,
        ]);
    }

    private function scrubIdentity(Guest $guest): void
    {
        $guest->forceFill(array_fill_keys(self::PII_COLUMNS, null) + [
            'account_status' => GuestAccountStatus::DELETED,
            'account_deleted_at' => now(),
        ])->save();
    }

    /** Every device signed out, no push to a deleted account (StaffService::deactivate pattern). */
    private function revokeAccess(Guest $guest): void
    {
        $guest->tokens()->delete();
        $guest->deviceTokens()->delete();
    }

    /** @param list<string> $identifiers the former phone/email (raw OTP identifiers are PII) */
    private function purgePersonal(Guest $guest, array $identifiers): void
    {
        $guest->guestNotifications()->delete();
        $guest->notes()->delete();

        if ($identifiers !== []) {
            OtpCode::whereIn('identifier', $identifiers)->delete();
        }
    }

    /**
     * ID scans on stays that reached checked_out are the legal guest register
     * and stay; the rest go (rows now, files after commit).
     *
     * @return list<string> file paths to delete after commit
     */
    private function pruneDocuments(Guest $guest): array
    {
        $documents = GuestDocument::where('guest_id', $guest->id)
            ->whereDoesntHave('reservation', fn ($q) => $q->where('status', ReservationStatus::CHECKED_OUT))
            ->get(['id', 'file_path']);

        GuestDocument::whereKey($documents->pluck('id'))->delete();

        return $documents->pluck('file_path')->filter()->values()->all();
    }

    /**
     * Conversations close; guest-authored messages lose body and attachment;
     * staff messages keep the thread shape.
     *
     * @param  list<string>  $files  attachment paths are appended for after-commit deletion
     * @return list<array{id: int, uuid: string, conversation_uuid: string, created_at: string}>
     */
    private function redactChat(Guest $guest, array &$files): array
    {
        $conversations = Conversation::where('guest_id', $guest->id)->get(['id', 'uuid']);
        if ($conversations->isEmpty()) {
            return [];
        }

        Conversation::whereKey($conversations->pluck('id'))->update(['status' => ConversationStatus::CLOSED->value]);

        $messages = Message::where('sender_type', $guest->getMorphClass())
            ->where('sender_id', $guest->id)
            ->get(['id', 'uuid', 'conversation_id', 'attachment_path', 'created_at']);

        Message::whereKey($messages->pluck('id'))->update(['body' => null, 'attachment_path' => null]);

        $uuids = $conversations->pluck('uuid', 'id');
        array_push($files, ...$messages->pluck('attachment_path')->filter()->values()->all());

        return $messages->map(fn (Message $m) => [
            'id' => $m->id,
            'uuid' => $m->uuid,
            'conversation_uuid' => $uuids[$m->conversation_id] ?? null,
            'created_at' => $m->created_at?->toIso8601String(),
        ])->all();
    }

    /**
     * Remove PII keys from audit rows of the given subjects, in `attribute_changes`
     * (where activitylog v5 keeps attributes/old) and `properties`. A query update,
     * so no new activity is generated. PHP-side, because SQLite and MySQL JSON differ.
     *
     * @param  list<int>  $subjectIds
     * @param  list<string>  $keys
     */
    private function redactActivityLog(string $subjectType, array $subjectIds, array $keys): void
    {
        if ($subjectIds === []) {
            return;
        }

        Activity::where('subject_type', $subjectType)
            ->whereIn('subject_id', $subjectIds)
            ->chunkById(200, function ($rows) use ($keys): void {
                foreach ($rows as $row) {
                    $changes = [];
                    foreach (['attribute_changes', 'properties'] as $column) {
                        $value = $row->{$column}?->toArray();
                        if (is_array($value)) {
                            foreach (['attributes', 'old'] as $section) {
                                if (isset($value[$section]) && is_array($value[$section])) {
                                    $value[$section] = array_diff_key($value[$section], array_flip($keys));
                                }
                            }
                            $changes[$column] = json_encode($value);
                        }
                    }
                    if ($changes !== []) {
                        Activity::whereKey($row->id)->toBase()->update($changes);
                    }
                }
            });
    }

    /**
     * @param  list<string>  $files
     * @param  list<array{uuid: string, conversation_uuid: ?string, created_at: ?string}>  $redacted
     */
    private function afterCommit(array $files, array $redacted): void
    {
        foreach ($files as $path) {
            // The erasure has committed; a storage failure leaves an orphan file (R-4), never a 500.
            try {
                $this->deleteFile($path);
            } catch (Throwable $e) {
                Log::warning('Guest erasure: file delete failed', ['path' => $path, 'exception' => $e->getMessage()]);
            }
        }

        foreach ($redacted as $message) {
            $this->mirrorToFirestore('chats', $message['uuid'], [
                'uuid' => $message['uuid'],
                'conversation_uuid' => $message['conversation_uuid'],
                'sender_type' => MessageSender::GUEST,
                'body' => null,
                'attachment_path' => null,
                'created_at' => $message['created_at'],
                'redacted' => true,
            ]);
        }
    }
}
