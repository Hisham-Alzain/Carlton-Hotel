<?php

namespace Tests\Unit\NightAudit;

use App\Enums\NightAuditBlockerStatus;
use App\Enums\NightAuditCheckStatus;
use App\Enums\NightAuditCheckType;
use App\Enums\NightAuditStatus;
use PHPUnit\Framework\TestCase;

/**
 * Phase 9 (D-06, D-07, D-10): the check/blocker split is encoded in the enums.
 */
class NightAuditEnumsTest extends TestCase
{
    public function test_check_types_are_in_fixed_order(): void
    {
        $this->assertSame([
            'unsettled_departures',
            'unassigned_arrivals',
            'dirty_rooms',
            'open_high_priority_tickets',
            'open_folio_disputes',
        ], array_map(fn (NightAuditCheckType $t) => $t->value, NightAuditCheckType::cases()));
    }

    public function test_only_date_scoped_categories_are_blocking(): void
    {
        $blocking = array_values(array_map(
            fn (NightAuditCheckType $t) => $t->value,
            array_filter(NightAuditCheckType::cases(), fn (NightAuditCheckType $t) => $t->isBlocking()),
        ));

        $this->assertSame(['unsettled_departures', 'unassigned_arrivals'], $blocking);
    }

    public function test_check_status_terminal_truth_table(): void
    {
        $this->assertTrue(NightAuditCheckStatus::PASSED->isTerminal());
        $this->assertTrue(NightAuditCheckStatus::RESOLVED->isTerminal());
        $this->assertTrue(NightAuditCheckStatus::OVERRIDDEN->isTerminal());
        $this->assertFalse(NightAuditCheckStatus::PENDING->isTerminal());
        $this->assertCount(4, NightAuditCheckStatus::cases());
    }

    public function test_audit_and_blocker_status_values(): void
    {
        $this->assertSame(['open', 'closed'], array_map(fn ($c) => $c->value, NightAuditStatus::cases()));
        $this->assertSame(['open', 'resolved'], array_map(fn ($c) => $c->value, NightAuditBlockerStatus::cases()));
    }
}
