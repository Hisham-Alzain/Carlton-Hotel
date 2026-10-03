<?php

namespace Tests\Unit\Events;

use App\Actions\Events\RecordEventDepositAction;
use App\Enums\EventDepositStatus;
use App\Exceptions\EventDepositAlreadyRecordedException;
use App\Models\EventInquiry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * D-05 risk mitigation: `deposit_status = paid` ⇔ exactly one completed
 * payment, across first write, replay and a refused second deposit.
 */
class RecordEventDepositActionTest extends TestCase
{
    use RefreshDatabase;

    private function assertInvariant(EventInquiry $inquiry): void
    {
        $fresh     = $inquiry->fresh();
        $completed = $fresh->payments()->where('status', 'completed')->count();

        $this->assertSame($fresh->deposit_status === EventDepositStatus::PAID, $completed === 1);
        $this->assertLessThanOrEqual(1, $completed);
    }

    public function test_status_and_ledger_agree_through_a_sequence(): void
    {
        $action  = app(RecordEventDepositAction::class);
        $cashier = User::factory()->create();
        $inquiry = EventInquiry::factory()->quoted()->create();

        $this->assertInvariant($inquiry);

        $first = $action->handle($inquiry, $cashier, ['amount_usd' => '400', 'method' => 'cash'], 'k-1');
        $this->assertSame(200, $first['code']);
        $this->assertInstanceOf(EventInquiry::class, $first['data']);
        $this->assertSame(EventDepositStatus::PAID, $first['data']->deposit_status);
        $this->assertInvariant($inquiry);

        $replay = $action->handle($inquiry, $cashier, ['amount_usd' => '400.00', 'method' => 'cash'], 'k-1');
        $this->assertSame(200, $replay['code']);
        $this->assertInvariant($inquiry);

        try {
            $action->handle($inquiry, $cashier, ['amount_usd' => '100'], 'k-2');
            $this->fail('A second deposit was accepted.');
        } catch (EventDepositAlreadyRecordedException) {
            // expected
        }
        $this->assertInvariant($inquiry);
        $this->assertSame(1, $inquiry->payments()->count());
    }
}
