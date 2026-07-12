<?php

namespace App\Http\Controllers\Api;

use App\Base\BaseController;
use App\Http\Requests\Service\SubmitDocumentsRequest;
use App\Http\Resources\Service\GuestDocumentResource;
use App\Services\Service\PreArrivalService;
use Illuminate\Http\JsonResponse;

class PreArrivalController extends BaseController
{
    public function __construct(private readonly PreArrivalService $service) {}

    public function submitDocuments(SubmitDocumentsRequest $request): JsonResponse
    {
        $result = $this->service->submitDocuments($request->user('guests'), $request->validated('documents'));
        $result['data'] = GuestDocumentResource::collection($result['data']);
        return $this->respondFromService($result, 'custom.messages.document_uploaded', $request);
    }
}
