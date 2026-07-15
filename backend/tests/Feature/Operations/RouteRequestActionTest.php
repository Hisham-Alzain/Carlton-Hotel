<?php

namespace Tests\Feature\Operations;

use App\Actions\Operations\RouteRequestAction;
use App\Models\ServiceRequest;
use App\Models\Ticket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * @group p10
 */
class RouteRequestActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_routes_a_service_request_by_type(): void
    {
        $request = ServiceRequest::factory()->create(['type' => 'housekeeping', 'department' => 'concierge']);

        $result = app(RouteRequestAction::class)->handle($request);

        $this->assertEquals(ServiceRequest::DEPARTMENT_HOUSEKEEPING, $result['data']->department);
    }

    public function test_unmapped_service_request_type_defaults_to_concierge(): void
    {
        $request = ServiceRequest::factory()->create(['type' => 'wake_up_call', 'department' => 'kitchen']);

        $result = app(RouteRequestAction::class)->handle($request);

        $this->assertEquals(ServiceRequest::DEPARTMENT_CONCIERGE, $result['data']->department);
    }

    public function test_routes_a_ticket_by_category(): void
    {
        $ticket = Ticket::factory()->create(['category' => Ticket::CATEGORY_MAINTENANCE, 'department' => Ticket::DEPARTMENT_CONCIERGE]);

        $result = app(RouteRequestAction::class)->handle($ticket);

        $this->assertEquals(Ticket::DEPARTMENT_HOUSEKEEPING, $result['data']->department);
    }

    public function test_unmapped_ticket_category_defaults_to_concierge(): void
    {
        $ticket = Ticket::factory()->create(['category' => Ticket::CATEGORY_INQUIRY, 'department' => Ticket::DEPARTMENT_HOUSEKEEPING]);

        $result = app(RouteRequestAction::class)->handle($ticket);

        $this->assertEquals(Ticket::DEPARTMENT_CONCIERGE, $result['data']->department);
    }
}
