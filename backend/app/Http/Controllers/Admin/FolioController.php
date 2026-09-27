<?php

namespace App\Http\Controllers\Admin;

use App\Base\BaseController;
use App\Http\Requests\Folio\PostFolioItemRequest;
use App\Http\Requests\Folio\RecordFolioPaymentRequest;
use App\Http\Requests\Folio\SettleFolioRequest;
use App\Http\Requests\Folio\StaffFolioDisputeRequest;
use App\Http\Resources\Folio\FolioItemResource;
use App\Http\Resources\Folio\FolioResource;
use App\Models\Folio;
use App\Models\FolioItem;
use App\Models\Reservation;
use App\Services\Folio\FolioService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FolioController extends BaseController
{
    public function __construct(private readonly FolioService $service) {}

    /** Phase 5 (D-01): staff folio read; 404 folio_missing when none, never generates. */
    public function showForReservation(Request $request, Reservation $reservation): JsonResponse
    {
        $result = $this->service->adminShow($reservation);
        $result['data'] = new FolioResource($result['data']);
        return $this->respondFromService($result, request: $request);
    }

    public function generate(Reservation $reservation, Request $request): JsonResponse
    {
        $result = $this->service->adminGenerate($reservation);
        $result['data'] = new FolioResource($result['data']);
        return $this->respondFromService($result, 'custom.messages.folio_generated', $request);
    }

    public function settle(SettleFolioRequest $request, Folio $folio): JsonResponse
    {
        $result = $this->service->adminSettle($folio, $request->validated(), $request->user());

        // Phase 5 (D-14): a folio with nothing due closes without a payment row.
        $messageKey = $result['payment_recorded'] ? 'custom.messages.folio_settled' : 'custom.messages.folio_settled_no_payment';

        return $this->success(new FolioResource($result['data']), $messageKey, $result['code'], $request);
    }

    /**
     * Phase 5 (D-05): append-only line item. 201 with a specific message, so
     * success() is called directly (respondFromService would replace a 201's
     * message with the generic "Created successfully.").
     */
    public function postItem(PostFolioItemRequest $request, Folio $folio): JsonResponse
    {
        $result = $this->service->adminPostItem($folio, $request->validated(), $request->user());

        return $this->success(new FolioResource($result['data']), 'custom.messages.folio_item_posted', $result['code'], $request);
    }

    /** Phase 5 (D-13): Idempotency-Key required; auto-settles at zero balance. */
    public function recordPayment(RecordFolioPaymentRequest $request, Folio $folio): JsonResponse
    {
        $result = $this->service->adminRecordPayment($folio, $request->validated(), $request->user());

        return $this->success(new FolioResource($result['data']), 'custom.messages.folio_payment_recorded', $result['code'], $request);
    }

    /** Phase 5 (D-11): raise / resolve / reject a dispute on an item of this folio. */
    public function dispute(StaffFolioDisputeRequest $request, Folio $folio, FolioItem $item): JsonResponse
    {
        $data   = $request->validated();
        $result = $this->service->adminDispute($folio, $item, $data, $request->user());

        $messageKey = match ($data['action']) {
            'raise'   => 'custom.messages.folio_dispute_raised',
            'resolve' => 'custom.messages.folio_dispute_resolved',
            'reject'  => 'custom.messages.folio_dispute_rejected',
        };

        return $this->success(new FolioItemResource($result['data']), $messageKey, $result['code'], $request);
    }
}
