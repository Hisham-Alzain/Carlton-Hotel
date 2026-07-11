<?php

namespace App\Http\Controllers\Api;

use App\Base\BaseController;
use App\Http\Requests\Events\SubmitInquiryRequest;
use App\Http\Resources\Events\EventInquiryResource;
use App\Services\Events\EventInquiryService;
use Illuminate\Http\JsonResponse;

class EventInquiryController extends BaseController
{
    public function __construct(private readonly EventInquiryService $service) {}

    public function submit(SubmitInquiryRequest $request): JsonResponse
    {
        $guestId = auth('guests')->id();
        $result          = $this->service->submit($request->validated(), $guestId);
        $result['data']  = new EventInquiryResource($result['data']);
        return $this->respondFromService($result, 'custom.messages.inquiry_submitted', $request);
    }
}
