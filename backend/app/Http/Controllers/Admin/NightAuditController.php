<?php

namespace App\Http\Controllers\Admin;

use App\Actions\NightAudit\CloseNightAuditAction;
use App\Actions\NightAudit\OpenNightAuditAction;
use App\Actions\NightAudit\ResolveNightAuditBlockerAction;
use App\Actions\NightAudit\ResolveNightAuditCheckAction;
use App\Base\BaseController;
use App\Http\Requests\NightAudit\ResolveNightAuditBlockerRequest;
use App\Http\Requests\NightAudit\ResolveNightAuditCheckRequest;
use App\Http\Requests\NightAudit\ShowNightAuditRequest;
use App\Models\NightAudit;
use App\Models\NightAuditBlocker;
use App\Models\NightAuditCheck;
use App\Http\Resources\NightAudit\NightAuditPayloadResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The night audit (Phase 9, D-01, D-02). Not CRUD: show opens the audit
 * lazily, the attestation and close verbs are actions. Every verb answers
 * with the same `{state, audit}` payload (D-13).
 */
class NightAuditController extends BaseController
{
    /**
     * Only a `night_audit.manage` holder may choose the accounting start date
     * (D-03); super-admin passes via Gate::before.
     */
    public function show(ShowNightAuditRequest $request, OpenNightAuditAction $action): JsonResponse
    {
        $user   = $request->user();
        $result = $action->handle($request->validated('date'), $user->can('night_audit.manage'), $user);

        return $this->payload($result, 'custom.messages.success', $request);
    }

    public function updateCheck(ResolveNightAuditCheckRequest $request, NightAuditCheck $check, ResolveNightAuditCheckAction $action): JsonResponse
    {
        $data   = $request->validated();
        $result = $action->handle($check, $data['status'], $data['note'], $request->user());

        return $this->payload($result, 'custom.messages.night_audit_check_updated', $request);
    }

    public function resolveBlocker(ResolveNightAuditBlockerRequest $request, NightAuditBlocker $blocker, ResolveNightAuditBlockerAction $action): JsonResponse
    {
        $result = $action->handle($blocker, $request->validated('note'), $request->user());

        return $this->payload($result, 'custom.messages.night_audit_blocker_resolved', $request);
    }

    /** No body is read: actor, date and status all come from the server (D-12). */
    public function close(Request $request, NightAudit $audit, CloseNightAuditAction $action): JsonResponse
    {
        return $this->payload($action->handle($audit, $request->user()), 'custom.messages.night_audit_closed', $request);
    }

    private function payload(array $result, string $messageKey, Request $request): JsonResponse
    {
        $result['data'] = new NightAuditPayloadResource($result['data']);

        return $this->respondFromService($result, $messageKey, $request);
    }
}
