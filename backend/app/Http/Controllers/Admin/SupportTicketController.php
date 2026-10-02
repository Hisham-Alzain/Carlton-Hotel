<?php

namespace App\Http\Controllers\Admin;

use App\Base\BaseController;
use App\Http\Requests\Tickets\AssignTicketRequest;
use App\Http\Requests\Tickets\CreateTicketRequest;
use App\Http\Requests\Tickets\EscalateTicketRequest;
use App\Http\Requests\Tickets\RecordTicketRecoveryRequest;
use App\Http\Requests\Tickets\ReplyToTicketRequest;
use App\Http\Requests\Tickets\UpdateTicketStatusRequest;
use App\Http\Resources\Tickets\TicketResource;
use App\Models\Ticket;
use App\Services\Tickets\TicketService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The staff support desk (Phase 7, D-10, D-13). On BaseController: every
 * write is a verb over a ticket single writer and answers with the detail
 * shape. `success()` is called directly because `respondFromService()` would
 * swap a 201 message for the generic "created" key. No DELETE (A7).
 */
class SupportTicketController extends BaseController
{
    public function __construct(private readonly TicketService $service) {}

    /** `assignee=me` is resolved here, so the filter never reads auth (D-13). */
    public function index(Request $request): JsonResponse
    {
        $params = $this->indexParams($request);

        if (($params['assignee'] ?? null) === 'me') {
            $params['assignee'] = $request->user('users')->uuid;
        }

        return $this->paginatedSuccess(
            $this->service->index($params, null, $this->perPageParam($request))['data'],
            TicketResource::class,
            $request,
        );
    }

    public function show(Ticket $ticket, Request $request): JsonResponse
    {
        return $this->success(
            new TicketResource($this->service->show($ticket)['data']),
            'custom.messages.success',
            200,
            $request,
        );
    }

    public function store(CreateTicketRequest $request): JsonResponse
    {
        $result = $this->service->store($request->validated(), $request->user('users'));

        return $this->success(new TicketResource($result['data']), 'custom.messages.ticket_created', 201, $request);
    }

    public function updateStatus(Ticket $ticket, UpdateTicketStatusRequest $request): JsonResponse
    {
        $data   = $request->validated();
        $result = $this->service->updateStatus($ticket, $data['status'], $data['reason'] ?? null, $request->user('users'));

        return $this->success(new TicketResource($result['data']), 'custom.messages.ticket_status_updated', 200, $request);
    }

    public function assign(Ticket $ticket, AssignTicketRequest $request): JsonResponse
    {
        $result = $this->service->assign($ticket, $request->validated('user_uuid'), $request->user('users'));

        return $this->success(new TicketResource($result['data']), 'custom.messages.ticket_assigned', 200, $request);
    }

    public function reply(Ticket $ticket, ReplyToTicketRequest $request): JsonResponse
    {
        $result = $this->service->reply($ticket, $request->validated('body'), $request->user('users'));

        return $this->success(new TicketResource($result['data']), 'custom.messages.ticket_replied', 201, $request);
    }

    public function escalate(Ticket $ticket, EscalateTicketRequest $request): JsonResponse
    {
        $data   = $request->validated();
        $result = $this->service->escalate($ticket, $data['user_uuid'], $data['reason'], $request->user('users'));

        return $this->success(new TicketResource($result['data']), 'custom.messages.ticket_escalated', 200, $request);
    }

    public function recordRecovery(Ticket $ticket, RecordTicketRecoveryRequest $request): JsonResponse
    {
        $result = $this->service->recordRecovery($ticket, $request->validated(), $request->user('users'));

        return $this->success(new TicketResource($result['data']), 'custom.messages.ticket_recovery_recorded', 201, $request);
    }
}
