<?php

namespace App\Http\Controllers\Admin;

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
            $this->service->adminIndex()['data'],
            ConversationResource::class,
            $request
        );
    }

    public function messages(Conversation $conversation, Request $request): JsonResponse
    {
        return $this->paginatedSuccess(
            $this->service->adminHistory($conversation)['data'],
            MessageResource::class,
            $request
        );
    }

    public function reply(Conversation $conversation, SendMessageRequest $request): JsonResponse
    {
        $result = $this->service->sendAsStaff($request->user('users'), $conversation, $request->validated());
        $result['data'] = new MessageResource($result['data']);
        return $this->respondFromService($result, request: $request);
    }
}
