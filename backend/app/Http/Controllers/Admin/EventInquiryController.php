<?php

namespace App\Http\Controllers\Admin;

use App\Base\BaseController;
use App\Http\Requests\Events\AssignInquiryRequest;
use App\Http\Requests\Events\UpdateInquiryStatusRequest;
use App\Http\Resources\Events\EventInquiryResource;
use App\Models\EventInquiry;
use App\Models\User;
use App\Services\Events\EventInquiryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EventInquiryController extends BaseController
{
    public function __construct(private readonly EventInquiryService $service) {}

    public function index(Request $request): JsonResponse
    {
        return $this->paginatedSuccess(
            $this->service->adminIndex()['data'],
            EventInquiryResource::class,
            $request
        );
    }

    public function show(EventInquiry $inquiry, Request $request): JsonResponse
    {
        $result         = $this->service->show($inquiry);
        $result['data'] = new EventInquiryResource($result['data']);
        return $this->respondFromService($result, request: $request);
    }

    public function updateStatus(UpdateInquiryStatusRequest $request, EventInquiry $inquiry): JsonResponse
    {
        $result         = $this->service->updateStatus($inquiry, $request->validated('status'));
        $result['data'] = new EventInquiryResource($result['data']);
        return $this->respondFromService($result, 'custom.messages.inquiry_updated', $request);
    }

    public function assign(AssignInquiryRequest $request, EventInquiry $inquiry): JsonResponse
    {
        $user           = User::where('uuid', $request->validated('user_uuid'))->firstOrFail();
        $result         = $this->service->assign($inquiry, $user);
        $result['data'] = new EventInquiryResource($result['data']);
        return $this->respondFromService($result, 'custom.messages.inquiry_assigned', $request);
    }
}
