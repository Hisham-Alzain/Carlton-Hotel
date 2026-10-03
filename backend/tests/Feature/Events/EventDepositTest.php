<?php

namespace Tests\Feature\Events;

use App\Actions\Events\RecordEventDepositAction;
use App\Actions\Folio\GenerateFolioAction;
use App\Enums\EventDepositStatus;
use App\Models\EventInquiry;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\RecordsRowLocks;
use Tests\TestCase;

/**
 * EVENT-02 (D-05, D-15..D-18, D-28, PR-8): one ledger-backed, replay-safe deposit.
 */
class EventDepositTest extends TestCase
{
    use RefreshDatabase, RecordsRowLocks;

    private User $cashier;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->cashier = User::factory()->create(['name' => 'Events Cashier']);
        $this->cashier->assignRole('events');
        $this->token = $this->cashier->createToken('t')->plainTextToken;
    }

    private function deposit(EventInquiry $inquiry, array $body, ?string $key = 'dep-1', ?string $token = null): TestResponse
    {
        $this->app['auth']->forgetGuards();
        $headers = $key === null ? [] : ['Idempotency-Key' => $key];

        return $this->withToken($token ?? $this->token)
            ->patchJson("/api/cms/event-inquiries/{$inquiry->uuid}/deposit", $body, $headers);
    }

    public function test_deposit_is_a_payments_row_and_flips_the_status(): void
    {
        $inquiry = EventInquiry::factory()->quoted()->create();

        $response = $this->deposit($inquiry, ['amount_usd' => '500'])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Deposit recorded.')
            ->assertJsonPath('data.deposit.status', 'paid')
            ->assertJsonPath('data.deposit.amount_usd', '500.00')
            ->assertJsonPath('data.deposit.method', 'cash')
            ->assertJsonPath('data.deposit.received_by.uuid', $this->cashier->uuid)
            ->assertJsonPath('data.checklist.1.item', 'deposit')
            ->assertJsonPath('data.checklist.1.done', true)
            ->assertJsonPath('data.status', 'quoted');

        $payment = Payment::sole();
        $response->assertJsonPath('data.deposit.payment_uuid', $payment->uuid);

        // PR-8: the FQCN, never a morph alias.
        $this->assertSame(EventInquiry::class, $payment->payable_type);
        $this->assertSame($inquiry->id, (int) $payment->payable_id);
        $this->assertSame('dep-1', $payment->idempotency_key);
        $this->assertSame('completed', $payment->status);
        $this->assertSame('500.00', (string) $payment->amount_usd);

        $fresh = $inquiry->fresh();
        $this->assertSame(EventDepositStatus::PAID, $fresh->deposit_status);
        $this->assertNotNull($fresh->deposit_paid_at);
        $this->assertDatabaseMissing('event_inquiry_checklist_items', ['item' => 'deposit']);
    }

    public function test_confirmed_inquiry_is_accepted(): void
    {
        $inquiry = EventInquiry::factory()->confirmed()->create();

        $this->deposit($inquiry, ['amount_usd' => '250.50'])->assertOk()->assertJsonPath('data.deposit.amount_usd', '250.50');
    }

    public function test_replay_returns_200_with_one_payment(): void
    {
        $inquiry = EventInquiry::factory()->quoted()->create();

        $first  = $this->deposit($inquiry, ['amount_usd' => '500', 'note' => 'Bank slip 42'])->assertOk();
        $second = $this->deposit($inquiry, ['amount_usd' => '500.00', 'note' => 'Bank slip 42'])->assertOk();

        $this->assertSame($first->json('data.deposit'), $second->json('data.deposit'));
        $this->assertSame(1, Payment::count());
    }

    public function test_same_key_different_amount_is_409(): void
    {
        $inquiry = EventInquiry::factory()->quoted()->create();
        $this->deposit($inquiry, ['amount_usd' => '500'])->assertOk();

        $this->deposit($inquiry, ['amount_usd' => '600'])
            ->assertStatus(409)
            ->assertJsonPath('error_code', 'idempotency_conflict')
            ->assertJsonPath('context.idempotency_key', 'dep-1');

        $this->assertSame(1, Payment::count());
    }

    public function test_same_key_different_note_is_409(): void
    {
        $inquiry = EventInquiry::factory()->quoted()->create();
        $this->deposit($inquiry, ['amount_usd' => '500', 'note' => 'A'])->assertOk();

        $this->deposit($inquiry, ['amount_usd' => '500', 'note' => 'B'])
            ->assertStatus(409)->assertJsonPath('error_code', 'idempotency_conflict');
    }

    public function test_same_key_from_another_desk_is_409(): void
    {
        $inquiry = EventInquiry::factory()->quoted()->create();
        $this->deposit($inquiry, ['amount_usd' => '500'])->assertOk();

        $this->app['auth']->forgetGuards();
        $other = User::factory()->create()->assignRole('events')->createToken('t')->plainTextToken;

        $this->deposit($inquiry, ['amount_usd' => '500'], 'dep-1', $other)
            ->assertStatus(409)->assertJsonPath('error_code', 'idempotency_conflict');
    }

    public static function missingKeys(): array
    {
        return ['absent' => [null], 'blank' => [''], 'whitespace' => ['   ']];
    }

    #[DataProvider('missingKeys')]
    public function test_missing_key_is_422(?string $key): void
    {
        $inquiry = EventInquiry::factory()->quoted()->create();

        $this->deposit($inquiry, ['amount_usd' => '500'], $key)
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'validation_failed')
            ->assertJsonPath('errors.idempotency_key.0', __('custom.errors.idempotency_key_required'));

        $this->assertSame(0, Payment::count());
    }

    public static function badAmounts(): array
    {
        return [
            'zero'        => [['amount_usd' => 0]],
            'three dp'    => [['amount_usd' => '10.555']],
            'too large'   => [['amount_usd' => 100000]],
            'not numeric' => [['amount_usd' => 'abc']],
            'missing'     => [[]],
        ];
    }

    #[DataProvider('badAmounts')]
    public function test_invalid_amount_is_422(array $body): void
    {
        $inquiry = EventInquiry::factory()->quoted()->create();

        $this->deposit($inquiry, $body)
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'validation_failed')
            ->assertJsonValidationErrors(['amount_usd']);
    }

    public function test_method_is_cash_only(): void
    {
        $inquiry = EventInquiry::factory()->quoted()->create();

        $this->deposit($inquiry, ['amount_usd' => '500', 'method' => 'on_arrival'])
            ->assertStatus(422)->assertJsonValidationErrors(['method']);

        $this->deposit($inquiry, ['amount_usd' => '500', 'method' => 'cash'])->assertOk();
    }

    public function test_note_over_1000_is_422(): void
    {
        $inquiry = EventInquiry::factory()->quoted()->create();

        $this->deposit($inquiry, ['amount_usd' => '500', 'note' => str_repeat('n', 1001)])
            ->assertStatus(422)->assertJsonValidationErrors(['note']);
    }

    public function test_second_deposit_with_a_new_key_is_refused(): void
    {
        $inquiry = EventInquiry::factory()->quoted()->create();
        $this->deposit($inquiry, ['amount_usd' => '500'])->assertOk();
        $payment = Payment::sole();

        $response = $this->deposit($inquiry, ['amount_usd' => '300'], 'dep-2')
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'event_deposit_already_recorded')
            ->assertJsonPath('context.payment_uuid', $payment->uuid);

        $this->assertNotNull($response->json('context.paid_at'));
        $this->assertSame(1, Payment::count());
    }

    public static function closedStatuses(): array
    {
        return [['new'], ['in_review'], ['cancelled']];
    }

    #[DataProvider('closedStatuses')]
    public function test_only_quoted_or_confirmed_take_a_deposit(string $status): void
    {
        $inquiry = EventInquiry::factory()->create(['status' => $status]);

        $this->deposit($inquiry, ['amount_usd' => '500'])
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'inquiry_state')
            ->assertJsonPath('context.status', $status)
            ->assertJsonPath('context.allowed', ['quoted', 'confirmed']);

        $this->assertSame(0, Payment::count());
        $this->assertSame(EventDepositStatus::UNPAID, $inquiry->fresh()->deposit_status);
    }

    public function test_deposit_does_not_auto_confirm(): void
    {
        $inquiry = EventInquiry::factory()->quoted()->create();

        $this->deposit($inquiry, ['amount_usd' => '500'])->assertOk();

        $this->assertSame('quoted', $inquiry->fresh()->status->value);
    }

    public function test_an_unrelated_folio_balance_is_unchanged(): void
    {
        $reservation = Reservation::factory()->checkedIn()->create(['total_usd' => '300.00']);
        $folio       = app(GenerateFolioAction::class)->handle($reservation)['data'];
        $before      = $folio->fresh()->balanceDueUsd();

        $inquiry = EventInquiry::factory()->quoted()->create(['guest_id' => $reservation->guest_id]);
        $this->deposit($inquiry, ['amount_usd' => '500'])->assertOk();

        $this->assertSame($before, $folio->fresh()->balanceDueUsd());
    }

    public function test_deposit_locks_the_inquiry_row(): void
    {
        $inquiry = EventInquiry::factory()->quoted()->create();

        $this->assertLocksRow('event_inquiries', fn () => $this->deposit($inquiry, ['amount_usd' => '500'])->assertOk());
    }

    public function test_read_budget(): void
    {
        $inquiry = EventInquiry::factory()->quoted()->create();

        $selects = 0;
        DB::listen(function ($event) use (&$selects): void {
            if (str_starts_with(strtolower(ltrim($event->sql)), 'select')) {
                $selects++;
            }
        });

        app(RecordEventDepositAction::class)->handle($inquiry, $this->cashier, ['amount_usd' => '500'], 'budget-1');

        $this->assertLessThanOrEqual(9, $selects, "deposit PATCH ran {$selects} reads (D-30 cap 9)");
    }

    public function test_events_manage_without_deposit_is_403(): void
    {
        $inquiry = EventInquiry::factory()->quoted()->create();
        $this->app['auth']->forgetGuards();
        $manager = User::factory()->create();
        $manager->givePermissionTo('events.view', 'events.manage');

        $this->deposit($inquiry, ['amount_usd' => '500'], 'dep-1', $manager->createToken('t')->plainTextToken)
            ->assertStatus(403);

        $this->assertSame(0, Payment::count());
    }

    public function test_requires_a_token(): void
    {
        $inquiry = EventInquiry::factory()->quoted()->create();

        $this->patchJson("/api/cms/event-inquiries/{$inquiry->uuid}/deposit", ['amount_usd' => '500'], ['Idempotency-Key' => 'x'])
            ->assertStatus(401);
    }
}
