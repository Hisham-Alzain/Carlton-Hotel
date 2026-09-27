<?php

namespace App\Actions\Folio;

use App\Enums\FolioItemSource;
use App\Enums\FolioStatus;
use App\Exceptions\FolioCreditExceedsBalanceException;
use App\Exceptions\FolioCreditExceedsItemException;
use App\Exceptions\FolioSettledException;
use App\Models\Folio;
use App\Models\FolioItem;
use App\Models\User;
use App\Support\FolioLedger;
use App\Support\IdempotentWrite;
use Illuminate\Support\Facades\DB;

/**
 * Append-only (D-05): corrections are credit rows, never edits.
 *
 * Lock the folio row only (D-15), check the Idempotency-Key replay first (D-08),
 * then the settled guard; reservation status is not a guard. A credit is
 * bounded twice under the lock, item floor first (D-05):
 *  - item floor: the credit plus the credits already reversing that row may not
 *    exceed the row's amount (`folio_credit_exceeds_item`);
 *  - balance floor: the folio balance after the credit may not go below 0.00
 *    (`folio_credit_exceeds_balance`; reservation deposits count, D-03).
 *
 * Money is bcmath on decimal strings only.
 */
class PostFolioItemAction
{
    public function handle(Folio $folio, User $poster, array $data): array
    {
        return DB::transaction(function () use ($folio, $poster, $data) {
            $locked = Folio::whereKey($folio->id)->lockForUpdate()->firstOrFail();

            $line = [
                'is_credit'     => ($data['kind'] ?? 'charge') === 'credit',
                'description'   => $data['description'],
                'quantity'      => (int) ($data['quantity'] ?? 1),
                'unit_price'    => FolioLedger::normalize($data['unit_price_usd']),
                'reason'        => $data['reason'] ?? null,
                'reverses_uuid' => $data['reverses_item_uuid'] ?? null,
                'key'           => $data['idempotency_key'] ?? null,
            ];
            $line['source_type'] = $line['is_credit'] ? FolioItemSource::CREDIT->value : FolioItemSource::MANUAL->value;
            $line['line_total']  = bcmul((string) $line['quantity'], $line['unit_price'], 2);

            [, $replayed] = IdempotentWrite::run(
                $line['key'],
                fn () => FolioItem::where('folio_id', $locked->id)->where('idempotency_key', $line['key'])->first(),
                fn (FolioItem $row) => $this->matches($row, $line),
                fn () => $this->write($locked, $poster, $line),
            );

            return ['data' => $locked->fresh(), 'code' => $replayed ? 200 : 201];
        });
    }

    /** The full validated payload is compared; the poster is not (FA-5.03-2). */
    private function matches(FolioItem $row, array $line): bool
    {
        return $row->source_type === $line['source_type']
            && $row->description === $line['description']
            && $row->quantity === $line['quantity']
            && bccomp(FolioLedger::normalize($row->unit_price_usd ?? '0'), $line['unit_price'], 2) === 0
            && $row->reason === $line['reason']
            && $row->reversesItem?->uuid === $line['reverses_uuid'];
    }

    private function write(Folio $locked, User $poster, array $line): FolioItem
    {
        if ($locked->status === FolioStatus::SETTLED) {
            throw new FolioSettledException(__('custom.errors.folio_settled'), [
                'folio_uuid' => $locked->uuid,
                'settled_at' => $locked->settled_at?->toIso8601String(),
            ]);
        }

        $reversed = null;

        if ($line['is_credit']) {
            if ($line['reverses_uuid'] !== null) {
                $reversed = FolioItem::where('folio_id', $locked->id)->where('uuid', $line['reverses_uuid'])->firstOrFail();
                $this->assertItemFloor($reversed, $line['line_total']);
            }

            $this->assertBalanceFloor($locked, $line['line_total']);
        }

        $item = $locked->items()->create([
            'description'      => $line['description'],
            'quantity'         => $line['quantity'],
            'unit_price_usd'   => $line['unit_price'],
            'amount_usd'       => $line['is_credit'] ? bcsub('0', $line['line_total'], 2) : $line['line_total'],
            'source_type'      => $line['source_type'],
            'source_id'        => null,
            'source_line'      => 0,
            'posted_by'        => $poster->id,
            'reason'           => $line['reason'],
            'reverses_item_id' => $reversed?->id,
            'idempotency_key'  => $line['key'],
        ]);

        $locked->recalculateTotals();

        return $item;
    }

    /**
     * |credit| + credits already reversing the row <= the row's amount. A credit
     * row has a negative amount, so reversing a credit always fails here.
     */
    private function assertItemFloor(FolioItem $reversed, string $lineTotal): void
    {
        $alreadyCredited = FolioLedger::sum(FolioItem::where('reverses_item_id', $reversed->id)->pluck('amount_usd'));
        $remaining       = FolioLedger::sum([$reversed->amount_usd, $alreadyCredited]);

        if (bccomp($lineTotal, $remaining, 2) === 1) {
            throw new FolioCreditExceedsItemException(__('custom.errors.folio_credit_exceeds_item'), [
                'item_uuid'     => $reversed->uuid,
                'remaining_usd' => $remaining,
                'amount_usd'    => $lineTotal,
            ]);
        }
    }

    /** The folio balance after the credit may not go below 0.00. */
    private function assertBalanceFloor(Folio $locked, string $lineTotal): void
    {
        $balance = $locked->balanceDueUsd();

        if (bccomp(bcsub($balance, $lineTotal, 2), '0', 2) === -1) {
            throw new FolioCreditExceedsBalanceException(__('custom.errors.folio_credit_exceeds_balance'), [
                'balance_due_usd' => $balance,
                'amount_usd'      => $lineTotal,
            ]);
        }
    }
}
