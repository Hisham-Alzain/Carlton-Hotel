<?php

namespace App\Http\Resources\Folio;

use App\Base\BaseResource;
use App\Enums\FolioItemSource;
use Illuminate\Http\Request;

class FolioItemResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        $posted = in_array($this->source_type, [FolioItemSource::MANUAL->value, FolioItemSource::CREDIT->value], true);

        return [
            'uuid'               => $this->uuid,
            'description'        => $this->description,
            'amount_usd'         => $this->amount_usd,
            'source_type'        => $this->source_type,
            'quantity'           => $this->quantity,
            // Null on generated rows (D-04).
            'unit_price_usd'     => $this->unit_price_usd,
            'posted_by'          => $this->whenLoaded('postedBy', fn () => [
                'uuid' => $this->postedBy->uuid,
                'name' => $this->postedBy->name,
            ]),
            // A generated row's timestamps only mean "last built", so only posted rows carry one.
            'posted_at'          => $posted ? $this->created_at?->toIso8601String() : null,
            'reason'             => $this->reason,
            'reverses_item_uuid' => $this->whenLoaded('reversesItem', fn () => $this->reversesItem->uuid),
            // The latest dispute, or null (D-02). Guest-visible, resolution note included (FA-5.07-3).
            'dispute'            => $this->whenLoaded('latestDispute', fn () => [
                'uuid'            => $this->latestDispute->uuid,
                'status'          => $this->latestDispute->status->value,
                'reason'          => $this->latestDispute->reason,
                'raised_by'       => $this->latestDispute->raisedBy(),
                'raised_at'       => $this->latestDispute->created_at?->toIso8601String(),
                'resolved_at'     => $this->latestDispute->resolved_at?->toIso8601String(),
                'resolution_note' => $this->latestDispute->resolution_note,
            ]),
        ];
    }
}
