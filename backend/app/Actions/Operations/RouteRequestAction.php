<?php

namespace App\Actions\Operations;

use App\Enums\Department;
use App\Models\ServiceRequest;
use App\Models\Ticket;

// Shared department resolver for service_requests and tickets (PLAN.md P10).
// Tickets resolve through Department::forTicketCategory(), the same helper
// CreateTicketAction (Phase 7, D-11) uses at creation time.
class RouteRequestAction
{
    public function handle(ServiceRequest|Ticket $item): array
    {
        $department = $item instanceof ServiceRequest
            ? Department::forServiceType($item->type)
            : Department::forTicketCategory($item->category);

        $item->update(['department' => $department]);

        return ['data' => $item->fresh(), 'code' => 200];
    }
}
