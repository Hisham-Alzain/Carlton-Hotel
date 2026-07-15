<?php

namespace App\Http\Controllers\Api;

use App\Base\BaseController;
use App\Http\Requests\Chat\SendMessageRequest;
use App\Http\Resources\Chat\ConversationResource;
use App\Http\Resources\Chat\MessageResource;
use App\Models\Conversation;
use App\Services\Chat\ChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConversationController extends BaseController
{
    public function __construct(private readonly ChatService $service) {}

    public function index(Request $request): JsonResponse
    {
        return $this->paginatedSuccess(
            $this->service->myConversations($request->user('guests'))['data'],
            ConversationResource::class,
            $request
        );
    }

    public function messages(Conversation $conversation, Request $request): JsonResponse
    {
        return $this->paginatedSuccess(
            $this->service->myConversationHistory($request->user('guests'), $conversation)['data'],
            MessageResource::class,
            $request
        );
    }

    public function send(SendMessageRequest $request): JsonResponse
    {
        $result = $this->service->sendAsGuest($request->user('guests'), $request->validated());
        $result['data'] = new MessageResource($result['data']);
        return $this->respondFromService($result, request: $request);
    }
}
