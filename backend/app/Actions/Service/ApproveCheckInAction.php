<?php

namespace App\Actions\Service;

use App\Exceptions\NotFoundException;
use App\Models\CheckInApproval;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ApproveCheckInAction
{
    public function handle(Reservation $reservation, string $status, User $approver, ?string $notes = null): array
    {
        return DB::transaction(function () use ($reservation, $status, $approver, $notes) {
            $approval = CheckInApproval::where('reservation_id', $reservation->id)->first();

            if (! $approval) {
                throw new NotFoundException(__('custom.errors.not_found'));
            }

            $approval->update([
                'status'      => $status,
                'approved_by' => $approver->id,
                'notes'       => $notes,
            ]);

            return ['data' => $approval->fresh()->load('approver'), 'code' => 200];
        });
    }
}
