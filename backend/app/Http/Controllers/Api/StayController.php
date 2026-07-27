<?php

namespace App\Http\Controllers\Api;

use App\Actions\Service\SetDndAction;
use App\Base\BaseController;
use App\Exceptions\NoActiveReservationException;
use App\Exceptions\NotFoundException;
use App\Http\Requests\Service\SetDndRequest;
use App\Http\Resources\Booking\ActiveStayResource;
use App\Http\Resources\Booking\PastStayResource;
use App\Http\Resources\Booking\UpcomingStayResource;
use App\Http\Resources\Folio\ReceiptResource;
use App\Models\Reservation;
use App\Services\Booking\StayService;
use App\Services\Folio\ReceiptPdfRenderer;
use App\Services\Folio\ReceiptService;
use App\Support\GuestEntitlement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class StayController extends BaseController
{
    public function __construct(
        private readonly StayService        $stays,
        private readonly ReceiptService     $receipts,
        private readonly ReceiptPdfRenderer $pdf,
        private readonly SetDndAction       $setDnd,
    ) {}

    /**
     * Sits behind plain `auth:guests`, not `is_checked_in`: having no active
     * stay is an empty state, not an error, and 403-ing here would force the
     * app to treat "not staying right now" as a failure.
     */
    public function active(Request $request): JsonResponse
    {
        $result = $this->stays->active($request->user('guests'));
        $result['data'] = $result['data'] ? new ActiveStayResource($result['data']) : null;

        return $this->respondFromService($result, request: $request);
    }

    public function upcoming(Request $request): JsonResponse
    {
        $result = $this->stays->upcoming($request->user('guests'));
        $result['data'] = UpcomingStayResource::collection($result['data']);

        return $this->respondFromService($result, request: $request);
    }

    public function past(Request $request): JsonResponse
    {
        return $this->paginatedSuccess(
            $this->stays->past($request->user('guests'))['data'],
            PastStayResource::class,
            $request,
        );
    }

    public function receipt(Reservation $reservation, Request $request): JsonResponse
    {
        $receipt = $this->receipts->forReservation($this->ownedOrFail($reservation, $request));

        if (! $receipt) {
            throw new NotFoundException();
        }

        return $this->success(new ReceiptResource($receipt), 'custom.messages.success', 200, $request);
    }

    /**
     * The one documented exception to the JSON envelope — the body is the PDF.
     * Errors still return the envelope.
     */
    public function receiptPdf(Reservation $reservation, Request $request): Response
    {
        $receipt = $this->receipts->forReservation($this->ownedOrFail($reservation, $request));

        if (! $receipt) {
            throw new NotFoundException();
        }

        $pdf = $this->pdf->render($receipt, $request->getLocale());

        return response($pdf, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="receipt-' . $reservation->booking_code . '.pdf"',
        ]);
    }

    public function setDnd(SetDndRequest $request): JsonResponse
    {
        $reservation = GuestEntitlement::currentReservation($request->user('guests'))
            ?? throw new NoActiveReservationException(__('custom.errors.no_active_reservation'));

        $result = $this->setDnd->handle(
            $reservation,
            $request->boolean('enabled'),
            $request->validated('until'),
        );

        return $this->respondFromService($result, request: $request);
    }

    /** 404 rather than 403 on someone else's stay — never confirm it exists. */
    private function ownedOrFail(Reservation $reservation, Request $request): Reservation
    {
        if ($reservation->guest_id !== $request->user('guests')->id) {
            throw new NotFoundException();
        }

        return $reservation;
    }
}
