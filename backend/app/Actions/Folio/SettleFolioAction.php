<?php

namespace App\Actions\Folio;

use App\Actions\Payment\RecordCashPaymentAction;
use App\Exceptions\ReservationStateException;
use App\Models\Folio;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SettleFolioAction
{
    public function __construct(private readonly RecordCashPaymentAction $recordCashPayment) {}

    public function handle(Folio $folio, string $method, float $amount, User $recorder, ?string $notes = null): array
    {
        return DB::transaction(function () use ($folio, $method, $amount, $recorder, $notes) {
            $locked = Folio::where('id', $folio->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === Folio::STATUS_SETTLED) {
                throw new ReservationStateException(__('custom.errors.reservation_state'));
            }

            $this->recordCashPayment->handle($locked, $method, $amount, $recorder, $notes);

            $locked->update(['status' => Folio::STATUS_SETTLED, 'settled_at' => now()]);

            return ['data' => $locked->fresh()->load(['items', 'payments']), 'code' => 200];
        });
    }
}
