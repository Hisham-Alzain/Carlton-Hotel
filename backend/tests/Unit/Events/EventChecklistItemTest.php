<?php

namespace Tests\Unit\Events;

use App\Enums\Department;
use App\Enums\EventChecklistItem;
use App\Enums\EventDepositStatus;
use Tests\TestCase;

/**
 * D-03 / D-04: the five-item template, its owners, the derived deposit item
 * and labels in every locale.
 */
class EventChecklistItemTest extends TestCase
{
    public function test_cases_are_the_dashboard_ids_in_order(): void
    {
        $this->assertSame(['contract', 'deposit', 'guarantee', 'beo', 'av'], EventChecklistItem::values());
    }

    public function test_only_deposit_is_derived(): void
    {
        foreach (EventChecklistItem::cases() as $item) {
            $this->assertSame($item === EventChecklistItem::DEPOSIT, $item->isDerived(), $item->value);
        }
    }

    public function test_owner_departments(): void
    {
        $this->assertSame(Department::SALES, EventChecklistItem::CONTRACT->ownerDepartment());
        $this->assertSame(Department::EVENTS, EventChecklistItem::DEPOSIT->ownerDepartment());
        $this->assertSame(Department::SALES, EventChecklistItem::GUARANTEE->ownerDepartment());
        $this->assertSame(Department::EVENTS, EventChecklistItem::BEO->ownerDepartment());
        $this->assertSame(Department::MAINTENANCE, EventChecklistItem::AV->ownerDepartment());
    }

    public function test_labels_exist_in_every_locale(): void
    {
        foreach (['en', 'ar', 'fr', 'tr', 'es'] as $locale) {
            app()->setLocale($locale);

            foreach (EventChecklistItem::cases() as $item) {
                $label = $item->label();
                $this->assertNotSame('', $label, "{$locale}.{$item->value}");
                $this->assertNotSame('custom.event_checklist.' . $item->value, $label, "{$locale}.{$item->value}");
            }
        }

        app()->setLocale('en');
        $this->assertSame('Contract signed', EventChecklistItem::CONTRACT->label());
    }

    public function test_deposit_status_values(): void
    {
        $this->assertSame(['unpaid', 'paid'], EventDepositStatus::values());
    }
}
