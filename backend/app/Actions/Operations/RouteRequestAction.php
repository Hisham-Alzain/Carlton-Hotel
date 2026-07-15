<?php

namespace App\Actions\Operations;

use App\Models\ServiceRequest;
use App\Models\Ticket;

// Shared department resolver for service_requests and tickets (PLAN.md P10).
// No HTTP route in P10 — nothing creates a Ticket until P11's CreateTicketAction,
// which will call this at creation time, same as ServiceRequestPlaced already
// resolves its department inline. Unit-tested directly until then.
class RouteRequestAction
{
    public function handle(ServiceRequest|Ticket $item): array
    {
        $department = $item instanceof ServiceRequest
            ? (ServiceRequest::TYPE_DEPARTMENTS[$item->type] ?? ServiceRequest::DEPARTMENT_CONCIERGE)
            : (Ticket::CATEGORY_DEPARTMENTS[$item->category] ?? Ticket::DEPARTMENT_CONCIERGE);

        $item->update(['department' => $department]);

        return ['data' => $item->fresh(), 'code' => 200];
    }
}
