<?php

namespace App\Actions\Events;

use App\Actions\Payment\RecordCashPaymentAction;
use App\Enums\EventDepositStatus;
use App\Enums\EventInquiryStatus;
use App\Exceptions\EventDepositAlreadyRecordedException;
use App\Exceptions\InquiryStateException;
use App\Models\EventInquiry;
use App\Models\Payment;
use App\Models\User;
use App\Services\Events\EventInquiryService;
use App\Support\FolioLedger;
use App\Support\IdempotentWrite;
use Illuminate\Support\Facades\DB;

/**
 * The single writer of an event deposit (Phase 8, D-15).
 *
 * A deposit is real money received, so it is a `payments` row (payable =
 * the inquiry, FQCN `payable_type`) written through RecordCashPaymentAction,
 * plus the `deposit_status` / `deposit_paid_at` flip — never a number on the
 * inquiry (D-05). Shape copied from RecordFolioPaymentAction: lock the
 * inquiry, then the Idempotency-Key replay check BEFORE the guards, so a
 * retried request that already succeeded answers 200 even though the
 * inquiry is now paid. Guards (first write only): status `quoted|confirmed`,
 * then not already paid (one deposit, no partials, D-17).
 *
 * No auto-confirm (D-17) and no folio is touched (D-18). EventInquiry must
 * stay out of the morph map: the replay `find` uses getMorphClass() while the
 * payment is written with get_class(), which agree only for unmapped models (PR-8).
 */
class RecordEventDepositAction
{
    private const ALLOWED = [EventInquiryStatus::QUOTED, EventInquiryStatus::CONFIRMED];

    public function __construct(private readonly RecordCashPaymentAction $recordCashPayment) {}

    /** @param array{amount_usd: string|int|float, method?: string, note?: ?string} $data */
    public function handle(EventInquiry $inquiry, User $recorder, array $data, string $key): array
    {
        return DB::transaction(function () use ($inquiry, $recorder, $data, $key) {
            $locked = EventInquiry::whereKey($inquiry->getKey())->lockForUpdate()->firstOrFail();
            $method = (string) ($data['method'] ?? 'cash');
            $amount = FolioLedger::normalize($data['amount_usd']);
            $note   = $data['note'] ?? null;

            IdempotentWrite::run(
                $key,
                fn () => Payment::where('payable_type', $locked->getMorphClass())
                    ->where('payable_id', $locked->id)
                    ->where('idempotency_key', $key)
                    ->first(),
                // recorded_by is part of the payload: the same key from another desk is a conflict.
                fn (Payment $payment) => $payment->method === $method
                    && bccomp(FolioLedger::normalize($payment->amount_usd), $amount, 2) === 0
                    && $payment->note === $note
                    && (int) $payment->recorded_by === (int) $recorder->id,
                fn () => $this->record($locked, $recorder, $method, $amount, $note, $key),
            );

            return ['data' => $locked->load(EventInquiryService::DETAIL_RELATIONS), 'code' => 200];
        }, 3);
    }

    private function record(EventInquiry $locked, User $recorder, string $method, string $amount, ?string $note, string $key): Payment
    {
        if (! in_array($locked->status, self::ALLOWED, true)) {
            throw new InquiryStateException(__('custom.errors.inquiry_state'), [
                'status'  => $locked->status->value,
                'allowed' => array_map(fn (EventInquiryStatus $s) => $s->value, self::ALLOWED),
            ]);
        }

        if ($locked->deposit_status === EventDepositStatus::PAID) {
            throw new EventDepositAlreadyRecordedException(__('custom.errors.event_deposit_already_recorded'), [
                'payment_uuid' => $locked->depositPayment?->uuid,
                'paid_at'      => $locked->deposit_paid_at?->toIso8601String(),
            ]);
        }

        $payment = $this->recordCashPayment->handle($locked, $method, $amount, $recorder, $note, idempotencyKey: $key)['data'];

        $locked->update([
            'deposit_status'  => EventDepositStatus::PAID,
            'deposit_paid_at' => now(),
        ]);

        return $payment;
    }
}
