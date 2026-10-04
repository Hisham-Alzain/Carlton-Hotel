<?php

namespace App\Http\Resources\Currency;

use App\Base\BaseResource;
use App\Models\ExchangeRate;
use Illuminate\Http\Request;

/**
 * The current-rates board (Phase 9.1, D-16) built from
 * ExchangeRateService::board(). Public callers never see `note` or `set_by`.
 * `rate` is a decimal string; `is_stale` when there is no rate or it is older
 * than `stale_after_hours`. Conversion is display-only on the client.
 */
class ExchangeRateBoardResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        $staleBefore = now()->subHours($this->resource['stale_after_hours']);

        return [
            'base' => $this->resource['base'],
            'stale_after_hours' => $this->resource['stale_after_hours'],
            'rates' => array_map(
                fn (array $entry) => $this->entry($entry, $staleBefore),
                $this->resource['rates'],
            ),
        ];
    }

    private function entry(array $entry, \DateTimeInterface $staleBefore): array
    {
        /** @var ExchangeRate|null $row */
        $row = $entry['row'];

        $out = [
            'currency' => $entry['currency'],
            'rate' => $row?->rate,
            'display_decimals' => $entry['display_decimals'],
            'updated_at' => $row?->created_at?->utc()->toIso8601ZuluString(),
            'is_stale' => $row === null || $row->created_at->lt($staleBefore),
        ];

        if ($this->resource['staff']) {
            $out['note'] = $row?->note;
            $out['set_by'] = $row?->relationLoaded('setBy') && $row->setBy
                ? ['uuid' => $row->setBy->uuid, 'name' => $row->setBy->name]
                : null;
        }

        return $out;
    }
}
