<?php

namespace App\Http\Resources\Events;

use App\Enums\EventChecklistItem;
use App\Enums\EventDepositStatus;
use App\Models\User;
use App\Support\FolioLedger;
use Illuminate\Http\Request;

/**
 * Event inquiry detail (show and every staff PATCH), Phase 8 D-29.
 *
 * Extends the list shape (PR-7) with the merged checklist (enum template ×
 * stored rows, in enum order; `deposit` derived from the deposit state, D-04),
 * the `deposit{}` object (amount from the payments ledger, D-05) and the
 * assignee / guest / event space objects. Reads only the relations in
 * `EventInquiryService::DETAIL_RELATIONS`.
 */
class EventInquiryDetailResource extends EventInquiryResource
{
    public function toArray(Request $request): array
    {
        return parent::toArray($request) + [
            'checklist'     => $this->checklist(),
            'deposit'       => $this->deposit(),
            'assigned_user' => $this->whenLoaded('assignedUser', fn () => $this->userRef($this->assignedUser)),
            'guest'         => $this->whenLoaded('guest', fn () => $this->guest ? [
                'uuid' => $this->guest->uuid,
                'name' => $this->guest->name ?: (trim("{$this->guest->first_name} {$this->guest->last_name}") ?: null),
            ] : null),
            'event_space'   => $this->whenLoaded('eventSpace', fn () => $this->eventSpace ? [
                'uuid' => $this->eventSpace->uuid,
                'name' => $this->eventSpace->getTranslation('name', app()->getLocale()),
            ] : null),
        ];
    }

    private function checklist(): array
    {
        $rows    = $this->relationLoaded('checklistItems') ? $this->checklistItems->keyBy(fn ($row) => $row->item->value) : collect();
        $payment = $this->paidDeposit();

        return array_map(function (EventChecklistItem $item) use ($rows, $payment) {
            if ($item->isDerived()) {
                $completedAt = $payment ? $this->deposit_paid_at : null;
                $completedBy = $payment?->recorder;
            } else {
                $row         = $rows->get($item->value);
                $completedAt = $row?->completed_at;
                $completedBy = $completedAt ? $row->completedBy : null;
            }

            return [
                'item'             => $item->value,
                'label'            => $item->label(),
                'owner_department' => $item->ownerDepartment()->value,
                'derived'          => $item->isDerived(),
                'done'             => $completedAt !== null,
                'completed_at'     => $completedAt?->toIso8601String(),
                'completed_by'     => $this->userRef($completedBy),
            ];
        }, EventChecklistItem::cases());
    }

    private function deposit(): array
    {
        $payment = $this->paidDeposit();

        return [
            'status'       => $this->depositStatus()->value,
            'amount_usd'   => $payment ? FolioLedger::normalize((string) $payment->amount_usd) : null,
            'method'       => $payment?->method,
            'paid_at'      => $payment ? $this->deposit_paid_at?->toIso8601String() : null,
            'received_by'  => $this->userRef($payment?->recorder),
            'payment_uuid' => $payment?->uuid,
        ];
    }

    /** The deposit payment, only when the inquiry is marked paid (single source of truth, D-04). */
    private function paidDeposit(): mixed
    {
        if ($this->depositStatus() !== EventDepositStatus::PAID || ! $this->relationLoaded('depositPayment')) {
            return null;
        }

        return $this->depositPayment;
    }

    private function userRef(?User $user): ?array
    {
        return $user ? ['uuid' => $user->uuid, 'name' => $user->name] : null;
    }
}
