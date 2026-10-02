<?php

namespace App\Services\Tickets;

use App\Actions\Tickets\AssignTicketAction;
use App\Actions\Tickets\CreateTicketAction;
use App\Actions\Tickets\EscalateTicketAction;
use App\Actions\Tickets\RecordTicketRecoveryAction;
use App\Actions\Tickets\ReplyToTicketAction;
use App\Actions\Tickets\UpdateTicketStatusAction;
use App\Base\BaseFilter;
use App\Base\BaseService;
use App\Enums\Department;
use App\Enums\ServiceRequestPriority;
use App\Enums\TicketCategory;
use App\Enums\TicketRecoveryType;
use App\Enums\TicketStatus;
use App\Filters\TicketFilter;
use App\Models\Conversation;
use App\Models\FolioItem;
use App\Models\Guest;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\Ticket;
use App\Models\TicketAction;
use App\Models\TicketRecovery;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * The staff support desk (Phase 7, TICKET-01..07). Reads are budgeted
 * (PR-8: show ≤ 6 queries); every write delegates to a ticket single writer
 * in `App\Actions\Tickets`, then re-reads through show() so every write
 * answers with the same shape.
 */
class TicketService extends BaseService
{
    protected string $model = Ticket::class;

    protected ?string $filter = TicketFilter::class;

    /** Newest timeline rows returned by show() (PR-3). */
    public const ACTIONS_LIMIT = 200;

    public function __construct(
        private readonly CreateTicketAction $createTicket,
        private readonly UpdateTicketStatusAction $updateTicketStatus,
        private readonly AssignTicketAction $assignTicket,
        private readonly ReplyToTicketAction $replyToTicket,
        private readonly EscalateTicketAction $escalateTicket,
        private readonly RecordTicketRecoveryAction $recordRecovery,
    ) {}

    /**
     * The ticket list (D-13), every status included. At most six queries per
     * page (PR-8): count, page (conversation uuid and totals as subselects),
     * guest, reservation, room, one batched users query. No actions on rows.
     * Default order created_at desc, id desc (TicketFilter::applySort()).
     */
    public function index(array $params = [], ?BaseFilter $filter = null, ?int $perPage = null): array
    {
        $query = $this->detailQuery();

        ($filter ?? $this->makeFilter($params))?->apply($query);

        $page = $query->paginate($this->resolvePerPage($perPage));

        $this->preloadUsers($page->getCollection());

        return ['data' => $page, 'code' => 200];
    }

    /**
     * @param  array{subject: string, description?: ?string, category: string, priority?: ?string,
     *               department?: ?string, guest_uuid?: ?string, reservation_uuid?: ?string, room_uuid?: ?string}  $data
     */
    public function store(array $data, ?User $actor = null): array
    {
        $result = $this->createTicket->handle([
            'subject'     => $data['subject'],
            'description' => $data['description'] ?? null,
            'category'    => TicketCategory::from($data['category']),
            'priority'    => isset($data['priority']) ? ServiceRequestPriority::from($data['priority']) : null,
            'department'  => isset($data['department']) ? Department::from($data['department']) : null,
            'guest'       => isset($data['guest_uuid']) ? Guest::where('uuid', $data['guest_uuid'])->firstOrFail() : null,
            'reservation' => isset($data['reservation_uuid']) ? Reservation::where('uuid', $data['reservation_uuid'])->firstOrFail() : null,
            'room'        => isset($data['room_uuid']) ? Room::where('uuid', $data['room_uuid'])->firstOrFail() : null,
        ], $actor);

        return ['data' => $this->show($result['data'])['data'], 'code' => 201];
    }

    /** D-06: moves the ticket through its single status writer, answers with the detail shape. */
    public function updateStatus(Ticket $ticket, string $status, ?string $reason, User $actor): array
    {
        $this->updateTicketStatus->handle($ticket, TicketStatus::from($status), $reason, $actor);

        return $this->show($ticket);
    }

    /** D-08: assigns through the single assignee writer, answers with the detail shape. */
    public function assign(Ticket $ticket, string $userUuid, User $actor): array
    {
        $this->assignTicket->handle($ticket, User::where('uuid', $userUuid)->firstOrFail(), $actor);

        return $this->show($ticket);
    }

    /** D-16: an internal reply; answers 201 with the detail shape. */
    public function reply(Ticket $ticket, string $body, User $actor): array
    {
        $this->replyToTicket->handle($ticket, $body, $actor);

        return ['data' => $this->show($ticket)['data'], 'code' => 201];
    }

    /** D-18: escalation to a named colleague; the level is server-derived. */
    public function escalate(Ticket $ticket, string $userUuid, string $reason, User $actor): array
    {
        $this->escalateTicket->handle($ticket, User::where('uuid', $userUuid)->firstOrFail(), $reason, $actor);

        return $this->show($ticket);
    }

    /**
     * D-15: records a service recovery (a folio credit is only linked, never
     * posted); answers 201 with the detail shape.
     *
     * @param  array{type: string, description: string, amount_usd?: string|int|float|null, folio_item_uuid?: ?string}  $validated
     */
    public function recordRecovery(Ticket $ticket, array $validated, User $actor): array
    {
        $amount = $validated['amount_usd'] ?? null;

        $this->recordRecovery->handle($ticket, [
            'type'        => TicketRecoveryType::from($validated['type']),
            'description' => $validated['description'],
            'amount_usd'  => $amount === null ? null : (string) $amount,
            'folio_item'  => isset($validated['folio_item_uuid'])
                ? FolioItem::where('uuid', $validated['folio_item_uuid'])->firstOrFail()
                : null,
        ], $actor);

        return ['data' => $this->show($ticket)['data'], 'code' => 201];
    }

    /**
     * The ticket detail in at most six queries (PR-8): (1) the ticket with the
     * conversation uuid and both recovery totals as subselects, (2-4) guest,
     * reservation, room, (5) the newest 201 timeline rows joined to their
     * recovery and folio item, (6) one batched users query.
     */
    public function show(Model $ticket): array
    {
        /** @var Ticket $loaded */
        $loaded = $this->detailQuery()->whereKey($ticket->getKey())->firstOrFail();

        $this->loadActions($loaded);
        $this->preloadUsers(new Collection([$loaded]));

        return ['data' => $loaded, 'code' => 200];
    }

    /** Ticket row + conversation uuid + recovery totals, with the link relations. */
    protected function detailQuery(): Builder
    {
        return Ticket::query()
            ->select('tickets.*')
            ->addSelect(['conversation_uuid' => Conversation::select('uuid')->whereColumn('conversations.id', 'tickets.conversation_id')])
            ->withSum(['recoveries as folio_credit_total_usd' => fn (Builder $q) => $q->where('ticket_recoveries.type', TicketRecoveryType::FOLIO_CREDIT->value)], 'amount_usd')
            ->withSum('recoveries as recorded_value_usd', 'amount_usd')
            ->with([
                'guest:id,uuid,name',
                'reservation:id,uuid,booking_code',
                'room' => fn ($q) => $q->select('rooms.id', 'rooms.uuid', 'rooms.number'),
            ]);
    }

    /**
     * Newest ACTIONS_LIMIT rows returned ascending, each carrying its recovery
     * (built from the joined columns — no per-row query) (PR-3).
     */
    private function loadActions(Ticket $ticket): void
    {
        $rows = TicketAction::query()
            ->select('ticket_actions.*')
            ->addSelect([
                'ticket_recoveries.id as r_id',
                'ticket_recoveries.uuid as r_uuid',
                'ticket_recoveries.type as r_type',
                'ticket_recoveries.amount_usd as r_amount_usd',
                'ticket_recoveries.description as r_description',
                'ticket_recoveries.folio_item_id as r_folio_item_id',
                'ticket_recoveries.created_at as r_created_at',
                'folio_items.uuid as r_folio_item_uuid',
            ])
            ->leftJoin('ticket_recoveries', 'ticket_recoveries.ticket_action_id', '=', 'ticket_actions.id')
            ->leftJoin('folio_items', 'folio_items.id', '=', 'ticket_recoveries.folio_item_id')
            ->where('ticket_actions.ticket_id', $ticket->getKey())
            ->orderByDesc('ticket_actions.created_at')
            ->orderByDesc('ticket_actions.id')
            ->limit(self::ACTIONS_LIMIT + 1)
            ->get();

        $ticket->actionsTruncated = $rows->count() > self::ACTIONS_LIMIT;

        $actions = $rows->take(self::ACTIONS_LIMIT)->reverse()->values()->each(function (TicketAction $action): void {
            $raw      = $action->getAttributes();
            $recovery = null;

            if ($raw['r_id'] !== null) {
                $recovery = (new TicketRecovery())->newFromBuilder([
                    'id'               => $raw['r_id'],
                    'uuid'             => $raw['r_uuid'],
                    'ticket_action_id' => $raw['id'],
                    'type'             => $raw['r_type'],
                    'amount_usd'       => $raw['r_amount_usd'],
                    'description'      => $raw['r_description'],
                    'folio_item_id'    => $raw['r_folio_item_id'],
                    'created_at'       => $raw['r_created_at'],
                    'folio_item_uuid'  => $raw['r_folio_item_uuid'],
                ]);
            }

            $action->setRawAttributes(array_filter(
                $raw,
                static fn (string $key) => ! str_starts_with($key, 'r_'),
                ARRAY_FILTER_USE_KEY,
            ), true);
            $action->setRelation('recovery', $recovery);
        });

        $ticket->setRelation('actions', new Collection($actions->all()));
    }

    /**
     * One users query for every person the payload names: the ticket's
     * assignee and creator and each loaded action's actor and target.
     *
     * @param  Collection<int, Ticket>  $tickets
     */
    protected function preloadUsers(Collection $tickets): void
    {
        $ids = [];

        foreach ($tickets as $ticket) {
            $ids[] = $ticket->assigned_user_id;
            $ids[] = $ticket->created_by;

            if ($ticket->relationLoaded('actions')) {
                foreach ($ticket->actions as $action) {
                    $ids[] = $action->user_id;
                    $ids[] = $action->target_user_id;
                }
            }
        }

        $ids   = array_values(array_unique(array_filter($ids)));
        $users = $ids === [] ? collect() : User::query()->whereIn('id', $ids)->get(['id', 'uuid', 'name'])->keyBy('id');

        foreach ($tickets as $ticket) {
            $ticket->setRelation('assignedUser', $users->get($ticket->assigned_user_id));
            $ticket->setRelation('createdBy', $users->get($ticket->created_by));

            if ($ticket->relationLoaded('actions')) {
                foreach ($ticket->actions as $action) {
                    $action->setRelation('user', $users->get($action->user_id));
                    $action->setRelation('targetUser', $users->get($action->target_user_id));
                }
            }
        }
    }
}
