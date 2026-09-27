<?php

namespace Tests\Feature\Folio;

use App\Actions\Folio\GenerateFolioAction;
use App\Enums\FolioStatus;
use App\Models\Folio;
use App\Models\Guest;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\ReservationRoom;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Spatie\Activitylog\Models\Activity;
use Tests\Concerns\RecordsRowLocks;
use Tests\TestCase;

/**
 * Phase 5 FOLIO-04 (D-03, D-08, D-13, D-14):
 *  - POST /api/cms/folios/{folio}/payments (Idempotency-Key required, auto-settle);
 *  - POST /api/cms/folios/{folio}/settle (payment-free close when nothing is due).
 */
class FolioPaymentTest extends TestCase
{
    use RefreshDatabase, RecordsRowLocks;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function staffToken(string ...$permissions): string
    {
        $user = User::factory()->create();
        $user->givePermissionTo($permissions);
        return $user->createToken('t')->plainTextToken;
    }

    private function presetToken(string $role, ?User $user = null): string
    {
        $user ??= User::factory()->create();
        $user->assignRole($role);
        return $user->createToken('t')->plainTextToken;
    }

    private function pay(Folio $folio, array $body, ?string $key = 'K-1', ?string $token = null): TestResponse
    {
        $this->app['auth']->forgetGuards();
        $headers = ['Accept-Language' => 'en'];
        if ($key !== null) {
            $headers['Idempotency-Key'] = $key;
        }

        return $this->withToken($token ?? $this->presetToken('reception'))
            ->postJson("/api/cms/folios/{$folio->uuid}/payments", $body, $headers);
    }

    private function settle(Folio $folio, array $body = [], ?string $token = null): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token ?? $this->presetToken('reception'))
            ->postJson("/api/cms/folios/{$folio->uuid}/settle", $body, ['Accept-Language' => 'en']);
    }

    private function generatedStay(string $total = '300.00', string $state = 'checkedIn'): array
    {
        $reservation = Reservation::factory()->{$state}()->create(['total_usd' => $total]);
        $folio       = app(GenerateFolioAction::class)->handle($reservation)['data'];

        return [$reservation, $folio];
    }

    private function deposit(Reservation $reservation, string $amount): void
    {
        Payment::factory()->create(['payable_type' => Reservation::class, 'payable_id' => $reservation->id, 'amount_usd' => $amount]);
    }

    private function cash(string $amount, array $extra = []): array
    {
        return array_merge(['method' => 'cash', 'amount_usd' => $amount], $extra);
    }

    // ── Payments (05-05) ────────────────────────────────────────────────

    public function test_cash_payment_is_recorded_against_the_folio(): void
    {
        [, $folio] = $this->generatedStay('300.00');
        $desk      = User::factory()->create();

        $this->pay($folio, $this->cash('100.00', ['note' => 'Partial']), 'K-1', $this->presetToken('reception', $desk))
            ->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Folio payment recorded.')
            ->assertJsonPath('data.status', 'open')
            ->assertJsonPath('data.paid_usd', '100.00')
            ->assertJsonPath('data.balance_due_usd', '200.00')
            ->assertJsonCount(1, 'data.payments')
            ->assertJsonPath('data.payments.0.method', 'cash')
            ->assertJsonPath('data.payments.0.amount_usd', '100.00')
            ->assertJsonPath('data.payments.0.note', 'Partial');

        $this->assertDatabaseHas('payments', [
            'payable_type' => Folio::class, 'payable_id' => $folio->id, 'method' => 'cash', 'amount_usd' => '100.00',
            'recorded_by' => $desk->id, 'idempotency_key' => 'K-1', 'status' => 'completed',
        ]);
    }

    public function test_on_arrival_method_is_accepted(): void
    {
        [, $folio] = $this->generatedStay('300.00');

        $this->pay($folio, ['method' => 'on_arrival', 'amount_usd' => '50.00'])->assertStatus(201)->assertJsonPath('data.paid_usd', '50.00');
    }

    public function test_reservation_deposits_count_toward_the_balance(): void
    {
        [$reservation, $folio] = $this->generatedStay('300.00');
        $this->deposit($reservation, '50.00');

        $this->pay($folio, $this->cash('250.01'))
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'folio_overpayment')
            ->assertJsonPath('context.balance_due_usd', '250.00')
            ->assertJsonPath('context.amount_usd', '250.01');

        $this->pay($folio, $this->cash('250.00'), 'K-2')
            ->assertStatus(201)
            ->assertJsonPath('data.status', 'settled')
            ->assertJsonPath('data.paid_usd', '300.00')
            ->assertJsonPath('data.balance_due_usd', '0.00');
    }

    public function test_exact_balance_payment_auto_settles_and_is_logged(): void
    {
        [, $folio] = $this->generatedStay('300.00');
        $desk      = User::factory()->create();

        $response = $this->pay($folio, $this->cash('300.00'), 'K-1', $this->presetToken('reception', $desk))
            ->assertStatus(201)
            ->assertJsonPath('data.status', 'settled')
            ->assertJsonPath('data.balance_due_usd', '0.00');
        $this->assertNotNull($response->json('data.settled_at'));
        $this->assertSame(FolioStatus::SETTLED, $folio->fresh()->status);

        $log = Activity::where('description', 'folio.auto_settled')->sole();
        $this->assertSame($folio->id, (int) $log->subject_id);
        $this->assertSame($desk->id, (int) $log->causer_id);
        $this->assertSame($folio->uuid, $log->properties['folio_uuid']);
        $this->assertSame('300.00', $log->properties['paid_usd']);
    }

    public function test_partial_payment_does_not_settle_or_log(): void
    {
        [, $folio] = $this->generatedStay('300.00');

        $this->pay($folio, $this->cash('299.99'))->assertStatus(201)->assertJsonPath('data.status', 'open');

        $this->assertSame(0, Activity::where('description', 'folio.auto_settled')->count());
    }

    public function test_one_cent_boundary_has_no_drift(): void
    {
        [, $folio] = $this->generatedStay('10.00');

        $this->pay($folio, $this->cash('9.99'), 'K-1')
            ->assertStatus(201)->assertJsonPath('data.status', 'open')->assertJsonPath('data.balance_due_usd', '0.01');
        $this->pay($folio, $this->cash('0.01'), 'K-2')
            ->assertStatus(201)->assertJsonPath('data.status', 'settled')->assertJsonPath('data.balance_due_usd', '0.00');
    }

    public function test_zero_balance_folio_refuses_any_payment(): void
    {
        [$reservation, $folio] = $this->generatedStay('100.00');
        $this->deposit($reservation, '100.00');

        $this->pay($folio, $this->cash('0.01'))
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'folio_overpayment')
            ->assertJsonPath('context.balance_due_usd', '0.00');

        $this->assertSame(0, Payment::where('payable_type', Folio::class)->count());
    }

    public function test_settled_folio_refuses_a_payment(): void
    {
        [, $folio] = $this->generatedStay('100.00');
        $folio->update(['status' => FolioStatus::SETTLED, 'settled_at' => now()]);

        $this->pay($folio, $this->cash('10.00'))
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'folio_settled')
            ->assertJsonPath('context.folio_uuid', $folio->uuid);
    }

    public function test_identical_retry_records_once_even_after_auto_settle(): void
    {
        [, $folio] = $this->generatedStay('100.00');
        $desk      = User::factory()->create();
        $token     = $this->presetToken('reception', $desk);

        $this->pay($folio, $this->cash('100.00'), 'K-1', $token)->assertStatus(201)->assertJsonPath('data.status', 'settled');
        $this->pay($folio, $this->cash('100.00'), 'K-1', $token)
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'settled')
            ->assertJsonCount(1, 'data.payments');

        $this->assertSame(1, Payment::where('idempotency_key', 'K-1')->count());
        $this->assertSame(1, Activity::where('description', 'folio.auto_settled')->count());
    }

    public function test_same_key_with_a_different_request_is_409(): void
    {
        [, $folio] = $this->generatedStay('300.00');
        $desk      = User::factory()->create();
        $token     = $this->presetToken('reception', $desk);

        $this->pay($folio, $this->cash('100.00'), 'K-1', $token)->assertStatus(201);

        $this->pay($folio, $this->cash('100.01'), 'K-1', $token)
            ->assertStatus(409)->assertJsonPath('error_code', 'idempotency_conflict')->assertJsonPath('context.idempotency_key', 'K-1');
        $this->pay($folio, ['method' => 'on_arrival', 'amount_usd' => '100.00'], 'K-1', $token)->assertStatus(409);
        $this->pay($folio, $this->cash('100.00', ['note' => 'x']), 'K-1', $token)->assertStatus(409);
        // Another desk re-using the key is a conflict too (recorded_by is part of the payload).
        $this->pay($folio, $this->cash('100.00'), 'K-1', $this->presetToken('reception'))->assertStatus(409);

        $this->assertSame(1, Payment::where('payable_type', Folio::class)->count());
    }

    public function test_idempotency_key_is_required(): void
    {
        [, $folio] = $this->generatedStay('300.00');

        foreach ([null, '', '   '] as $key) {
            $this->pay($folio, $this->cash('10.00', ['idempotency_key' => 'BODY']), $key)
                ->assertStatus(422)
                ->assertJsonPath('error_code', 'validation_failed')
                ->assertJsonPath('errors.idempotency_key.0', 'An Idempotency-Key header is required for this request.');
        }
        $this->pay($folio, $this->cash('10.00'), str_repeat('k', 65))
            ->assertStatus(422)->assertJsonValidationErrors(['idempotency_key']);

        $this->assertSame(0, Payment::count());
    }

    public function test_payment_validation(): void
    {
        [, $folio] = $this->generatedStay('300.00');
        $cases = [
            'amount_usd' => [['amount_usd' => 0], ['amount_usd' => '10.555'], ['amount_usd' => 100000], ['amount_usd' => null]],
            'method'     => [['method' => 'card'], ['method' => null]],
            'note'       => [['note' => str_repeat('n', 1001)]],
        ];

        foreach ($cases as $field => $overrides) {
            foreach ($overrides as $override) {
                $this->pay($folio, array_merge($this->cash('10.00'), $override))
                    ->assertStatus(422)
                    ->assertJsonPath('error_code', 'validation_failed')
                    ->assertJsonValidationErrors([$field]);
            }
        }

        $this->assertSame(0, Payment::count());
    }

    public function test_forced_checkout_open_folio_still_accepts_and_settles(): void
    {
        [, $folio] = $this->generatedStay('120.00', 'checkedOut');

        $this->pay($folio, $this->cash('120.00'))->assertStatus(201)->assertJsonPath('data.status', 'settled');
    }

    public function test_payment_locks_the_folio_row(): void
    {
        [, $folio] = $this->generatedStay('300.00');
        $token     = $this->presetToken('reception');

        $this->assertLocksRow('folios', fn () => $this->pay($folio, $this->cash('10.00'), 'K-1', $token)->assertStatus(201));
    }

    public function test_payment_gates(): void
    {
        [, $folio] = $this->generatedStay('300.00');

        $this->app['auth']->forgetGuards();
        $this->postJson("/api/cms/folios/{$folio->uuid}/payments", $this->cash('10.00'), ['Idempotency-Key' => 'K'])
            ->assertStatus(401)->assertJsonPath('error_code', 'unauthorized');
        $this->pay($folio, $this->cash('10.00'), 'K', Guest::factory()->create()->createToken('t')->plainTextToken)->assertStatus(401);

        $this->pay($folio, $this->cash('10.00'), 'K', $this->presetToken('kitchen'))->assertStatus(403)->assertJsonPath('error_code', 'forbidden');
        $this->pay($folio, $this->cash('10.00'), 'K', $this->presetToken('housekeeping'))->assertStatus(403);
        $this->pay($folio, $this->cash('10.00'), 'K', $this->staffToken('folios.view', 'folios.post'))->assertStatus(403);
        $this->assertSame(0, Payment::count());

        $this->pay($folio, $this->cash('10.00'), 'K', $this->staffToken('folios.settle'))->assertStatus(201);

        $this->app['auth']->forgetGuards();
        $this->withToken($this->presetToken('reception'))
            ->postJson('/api/cms/folios/'.Str::uuid().'/payments', $this->cash('10.00'), ['Idempotency-Key' => 'K'])
            ->assertStatus(404)->assertJsonPath('error_code', 'not_found');
    }

    // ── Settle close path (05-06) ───────────────────────────────────────

    public function test_settle_with_nothing_due_closes_without_a_payment(): void
    {
        [$reservation, $folio] = $this->generatedStay('300.00');
        $this->deposit($reservation, '300.00');
        $desk = User::factory()->create();

        $this->settle($folio, [], $this->presetToken('reception', $desk))
            ->assertOk()
            ->assertJsonPath('message', 'Folio settled; nothing was due, so no payment was recorded.')
            ->assertJsonPath('data.status', 'settled')
            ->assertJsonPath('data.balance_due_usd', '0.00');

        $this->assertSame(1, Payment::count(), 'only the deposit exists');
        $log = Activity::where('description', 'folio.settled_no_payment')->sole();
        $this->assertSame($desk->id, (int) $log->causer_id);
        $this->assertSame($folio->uuid, $log->properties['folio_uuid']);
        $this->assertSame('0.00', $log->properties['balance_due_usd']);
        $this->assertSame('300.00', $log->properties['paid_usd']);
    }

    public function test_settle_closes_a_negative_balance_and_ignores_a_sent_amount(): void
    {
        [$reservation, $folio] = $this->generatedStay('100.00');
        $this->deposit($reservation, '120.00');

        $this->settle($folio, ['method' => 'cash', 'amount_usd' => '50.00'])
            ->assertOk()
            ->assertJsonPath('data.status', 'settled')
            ->assertJsonPath('data.balance_due_usd', '-20.00');

        $this->assertSame(1, Payment::count());
        $this->assertSame('-20.00', Activity::where('description', 'folio.settled_no_payment')->sole()->properties['balance_due_usd']);
    }

    public function test_settle_with_money_due_records_the_payment(): void
    {
        [, $folio] = $this->generatedStay('300.00');

        $this->settle($folio, ['method' => 'cash', 'amount_usd' => '300.00'])
            ->assertOk()
            ->assertJsonPath('message', 'Folio settled.')
            ->assertJsonPath('data.status', 'settled')
            ->assertJsonPath('data.paid_usd', '300.00');

        $this->assertDatabaseHas('payments', ['payable_type' => Folio::class, 'payable_id' => $folio->id, 'amount_usd' => '300.00']);
        $this->assertSame(0, Activity::where('description', 'folio.settled_no_payment')->count());
    }

    public function test_settle_without_an_amount_while_money_is_due_is_422(): void
    {
        [, $folio] = $this->generatedStay('300.00');

        $this->settle($folio, [])
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'validation_failed')
            ->assertJsonValidationErrors(['amount_usd']);

        $this->assertSame(FolioStatus::OPEN, $folio->fresh()->status);
        $this->assertSame(0, Payment::count());
    }

    public function test_settle_body_validation(): void
    {
        [, $folio] = $this->generatedStay('300.00');

        $this->settle($folio, ['method' => 'cash', 'amount_usd' => '10.555'])->assertStatus(422)->assertJsonValidationErrors(['amount_usd']);
        $this->settle($folio, ['method' => 'cash', 'amount_usd' => 0])->assertStatus(422)->assertJsonValidationErrors(['amount_usd']);
        $this->settle($folio, ['amount_usd' => '10.00'])
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'validation_failed')
            ->assertJsonPath('errors.method.0', 'The method field is required when amount usd is present.');
        $this->settle($folio, ['method' => 'card', 'amount_usd' => '10.00'])->assertStatus(422)->assertJsonValidationErrors(['method']);

        $this->assertSame(FolioStatus::OPEN, $folio->fresh()->status);
    }

    public function test_settling_an_already_settled_folio_is_folio_settled(): void
    {
        [$reservation, $folio] = $this->generatedStay('100.00');
        $this->deposit($reservation, '100.00');
        $this->settle($folio)->assertOk();

        $this->settle($folio)
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'folio_settled')
            ->assertJsonPath('context.folio_uuid', $folio->uuid);
    }

    public function test_settle_locks_the_folio_row(): void
    {
        [$reservation, $folio] = $this->generatedStay('100.00');
        $this->deposit($reservation, '100.00');
        $token = $this->presetToken('reception');

        $this->assertLocksRow('folios', fn () => $this->settle($folio, [], $token)->assertOk());
    }

    // ── End to end with check-out ───────────────────────────────────────

    /** A checked-in stay leaving today (2027-03-12), one room, total $total. */
    private function departingStay(string $total): Reservation
    {
        config(['hotel.timezone' => 'Asia/Damascus']);
        $this->travelTo(Carbon::parse('2027-03-12 10:00:00'));
        $type        = RoomType::factory()->create();
        $room        = Room::factory()->create(['room_type_id' => $type->id, 'status' => 'available']);
        $reservation = Reservation::factory()->checkedIn()->create([
            'check_in' => '2027-03-10', 'check_out' => '2027-03-12', 'checked_in_at' => '2027-03-10 12:00:00', 'total_usd' => $total,
        ]);
        ReservationRoom::factory()->create([
            'reservation_id' => $reservation->id, 'room_type_id' => $type->id, 'room_id' => $room->id, 'price_usd' => $total,
        ]);

        return $reservation;
    }

    private function checkOut(Reservation $reservation, string $token): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token)->postJson("/api/cms/reservations/{$reservation->uuid}/check-out", [], ['Accept-Language' => 'en']);
    }

    public function test_prepaid_stay_settles_with_an_empty_body_and_checks_out(): void
    {
        $reservation = $this->departingStay('300.00');
        $token       = $this->presetToken('reception');

        $this->app['auth']->forgetGuards();
        $this->withToken($token)
            ->postJson("/api/cms/reservations/{$reservation->uuid}/settle", ['method' => 'cash', 'amount_usd' => 300])
            ->assertOk();
        $this->app['auth']->forgetGuards();
        $folioUuid = $this->withToken($token)->postJson("/api/cms/folios/{$reservation->uuid}/generate")->assertOk()->json('data.uuid');
        $folio     = Folio::where('uuid', $folioUuid)->sole();

        $this->settle($folio, [], $token)->assertOk()->assertJsonPath('data.status', 'settled');

        $this->checkOut($reservation, $token)
            ->assertOk()
            ->assertJsonPath('data.status', 'checked_out')
            ->assertJsonPath('data.folio.status', 'settled');
    }

    public function test_auto_settled_folio_passes_the_check_out_gate(): void
    {
        $reservation = $this->departingStay('300.00');
        $token       = $this->presetToken('reception');
        $folio       = app(GenerateFolioAction::class)->handle($reservation)['data'];

        $this->checkOut($reservation, $token)->assertStatus(422)->assertJsonPath('error_code', 'folio_unsettled');

        $this->pay($folio, $this->cash('300.00'), 'K-1', $token)->assertStatus(201)->assertJsonPath('data.status', 'settled');

        $this->checkOut($reservation, $token)->assertOk()->assertJsonPath('data.status', 'checked_out');
    }
}
