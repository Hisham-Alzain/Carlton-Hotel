<?php

namespace App\Actions\Service;

use App\Actions\Booking\IssueDigitalKeyAction;
use App\Actions\Booking\RevokeDigitalKeyAction;
use App\Enums\CheckInApprovalStatus;
use App\Enums\DigitalKeyRevocationReason;
use App\Enums\ReservationStatus;
use App\Events\CheckInApproved;
use App\Exceptions\NotFoundException;
use App\Models\CheckInApproval;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ApproveCheckInAction
{
    public function __construct(
        private readonly IssueDigitalKeyAction $issueKey,
        private readonly RevokeDigitalKeyAction $revokeKey,
    ) {}

    public function handle(Reservation $reservation, string $status, User $approver, ?string $notes = null): array
    {
        return DB::transaction(function () use ($reservation, $status, $approver, $notes) {
            $approval = CheckInApproval::where('reservation_id', $reservation->id)->first();

            if (! $approval) {
                throw new NotFoundException(__('custom.errors.not_found'));
            }

            $previous = $approval->status;

            $approval->update([
                'status'      => $status,
                'approved_by' => $approver->id,
                'notes'       => $notes,
            ]);

            // Phase 4 (D-11, D-14): the decision drives the digital key, inside
            // this transaction. Approving a stay that holds its room issues a key
            // (kept if one is already active); rejecting revokes it. The event —
            // and so the "key ready" push — fires only on a real transition into
            // approved, after commit, and never carries the code.
            if ($status === CheckInApprovalStatus::APPROVED->value && $this->holdsRoom($reservation)) {
                $locked = $this->issueKey->handle($reservation)['data'];

                // Re-checked on the locked row: the push text is only true when a key exists.
                if ($previous !== CheckInApprovalStatus::APPROVED && $locked->hasActiveDigitalKey()) {
                    CheckInApproved::dispatch($reservation, $approval);
                }
            } elseif ($status === CheckInApprovalStatus::REJECTED->value) {
                $this->revokeKey->handle($reservation, DigitalKeyRevocationReason::REJECTED->value);
            }

            return ['data' => $approval->fresh()->load('approver'), 'code' => 200];
        });
    }

    private function holdsRoom(Reservation $reservation): bool
    {
        return in_array($reservation->status, [ReservationStatus::CONFIRMED, ReservationStatus::CHECKED_IN], true);
    }
}
