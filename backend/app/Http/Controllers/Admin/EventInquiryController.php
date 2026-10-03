<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Events\RecordEventDepositAction;
use App\Actions\Events\ToggleEventChecklistItemAction;
use App\Base\BaseController;
use App\Enums\EventChecklistItem;
use App\Http\Requests\Events\AssignInquiryRequest;
use App\Http\Requests\Events\RecordEventDepositRequest;
use App\Http\Requests\Events\UpdateEventChecklistItemRequest;
use App\Http\Requests\Events\UpdateEventStaffNotesRequest;
use App\Http\Requests\Events\UpdateInquiryStatusRequest;
use App\Http\Resources\Events\EventInquiryDetailResource;
use App\Http\Resources\Events\EventInquiryResource;
use App\Models\EventInquiry;
use App\Models\User;
use App\Services\Events\EventInquiryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Staff event-inquiry routes, gated events.* (Phase 8, D-12). Show and every
 * PATCH answer with the same detail shape so the dashboard can replace the
 * inquiry wholesale (D-09).
 */
class EventInquiryController extends BaseController
{
    public function __construct(
        private readonly EventInquiryService $service,
        private readonly ToggleEventChecklistItemAction $toggleChecklistItem,
        private readonly RecordEventDepositAction $recordEventDeposit,
    ) {}

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
        return $this->detail($this->service->show($inquiry), null, $request);
    }

    public function updateStatus(UpdateInquiryStatusRequest $request, EventInquiry $inquiry): JsonResponse
    {
        return $this->detail(
            $this->service->updateStatus($inquiry, $request->validated('status')),
            'custom.messages.inquiry_updated',
            $request,
        );
    }

    public function assign(AssignInquiryRequest $request, EventInquiry $inquiry): JsonResponse
    {
        $user = User::where('uuid', $request->validated('user_uuid'))->firstOrFail();

        return $this->detail($this->service->assign($inquiry, $user), 'custom.messages.inquiry_assigned', $request);
    }

    /** `{item}` is route-constrained to the enum, so an unknown item never reaches here (404). */
    public function updateChecklistItem(UpdateEventChecklistItemRequest $request, EventInquiry $inquiry, string $item): JsonResponse
    {
        $result = $this->toggleChecklistItem->handle(
            $inquiry,
            EventChecklistItem::from($item),
            $request->boolean('done'),
            $request->user('users'),
        );

        return $this->detail($result, 'custom.messages.event_checklist_updated', $request);
    }

    public function updateNotes(UpdateEventStaffNotesRequest $request, EventInquiry $inquiry): JsonResponse
    {
        return $this->detail(
            $this->service->updateStaffNotes($inquiry, $request->validated('staff_notes')),
            'custom.messages.event_notes_updated',
            $request,
        );
    }

    /** Ledger-backed, replay-safe deposit (D-15); `Idempotency-Key` required. */
    public function recordDeposit(RecordEventDepositRequest $request, EventInquiry $inquiry): JsonResponse
    {
        $result = $this->recordEventDeposit->handle(
            $inquiry,
            $request->user('users'),
            $request->safe()->only(['amount_usd', 'method', 'note']),
            $request->validated('idempotency_key'),
        );

        return $this->detail($result, 'custom.messages.event_deposit_recorded', $request);
    }

    private function detail(array $result, ?string $message, Request $request): JsonResponse
    {
        $result['data'] = new EventInquiryDetailResource($result['data']);

        return $this->respondFromService($result, $message ?? 'custom.messages.success', $request);
    }
}
