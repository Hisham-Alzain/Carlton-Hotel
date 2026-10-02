<?php

namespace Tests\Unit\Tickets;

use App\Enums\Department;
use App\Enums\ServiceRequestPriority;
use App\Enums\TicketActionType;
use App\Enums\TicketCategory;
use App\Enums\TicketRecoveryType;
use App\Enums\TicketSource;
use PHPUnit\Framework\TestCase;

/**
 * D-04, D-11, D-12, D-14 and the council A8 meta whitelist.
 */
class TicketEnumsTest extends TestCase
{
    public function test_ticket_source_is_chatbot_and_staff_only(): void
    {
        $this->assertSame(['chatbot', 'staff'], TicketSource::values());
    }

    public function test_action_types_and_meta_whitelist(): void
    {
        $this->assertSame(
            ['created', 'status_change', 'assignment', 'escalation', 'reply', 'recovery'],
            TicketActionType::values(),
        );

        $this->assertSame(['claim'], TicketActionType::ASSIGNMENT->allowedMetaKeys());
        $this->assertSame(['level', 'previous_assignee_uuid'], TicketActionType::ESCALATION->allowedMetaKeys());

        foreach ([TicketActionType::CREATED, TicketActionType::STATUS_CHANGE, TicketActionType::REPLY, TicketActionType::RECOVERY] as $type) {
            $this->assertSame([], $type->allowedMetaKeys(), $type->value);
        }
    }

    public function test_recovery_types(): void
    {
        $this->assertSame(
            ['folio_credit', 'rate_discount', 'courtesy_amenity', 'room_upgrade', 'late_checkout', 'apology', 'other'],
            TicketRecoveryType::values(),
        );
    }

    public function test_priority_scale_round_trips(): void
    {
        $this->assertSame(1, ServiceRequestPriority::LOW->toTicketScale());
        $this->assertSame(2, ServiceRequestPriority::NORMAL->toTicketScale());
        $this->assertSame(3, ServiceRequestPriority::HIGH->toTicketScale());

        foreach (ServiceRequestPriority::cases() as $priority) {
            $this->assertSame($priority, ServiceRequestPriority::fromTicketScale($priority->toTicketScale()));
        }
    }

    public function test_department_for_ticket_category(): void
    {
        $this->assertSame(TicketCategory::MAINTENANCE->department(), Department::forTicketCategory(TicketCategory::MAINTENANCE));
        $this->assertSame(Department::CONCIERGE, Department::forTicketCategory(null));

        foreach (TicketCategory::cases() as $category) {
            $this->assertSame(
                $category->department() ?? Department::CONCIERGE,
                Department::forTicketCategory($category),
                $category->value,
            );
        }
    }
}
