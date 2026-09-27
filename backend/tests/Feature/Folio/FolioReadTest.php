<?php

namespace Tests\Feature\Folio;

use App\Actions\Folio\GenerateFolioAction;
use App\Enums\FolioStatus;
use App\Http\Resources\Folio\FolioResource;
use App\Models\Folio;
use App\Models\FolioItem;
use App\Models\Guest;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\User;
use App\Services\Folio\FolioService;
use App\Services\Folio\ReceiptService;
use Carbon\Carbon;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Phase 5 FOLIO-01 (D-01, D-02, D-03): GET /api/cms/reservations/{reservation}/folio.
 */
class FolioReadTest extends TestCase
{
    use RefreshDatabase;

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

    private function presetToken(string $role): string
    {
        $user = User::factory()->create();
        $user->assignRole($role);
        return $user->createToken('t')->plainTextToken;
    }

    private function read(Reservation $reservation, ?string $token = null)
    {
        $this->app['auth']->forgetGuards();
        $request = $token === null ? $this : $this->withToken($token);

        return $request->withHeaders(['Accept-Language' => 'en'])
            ->getJson("/api/cms/reservations/{$reservation->uuid}/folio");
    }

    private function generatedStay(string $total = '300.00'): array
    {
        $reservation = Reservation::factory()->checkedIn()->create(['total_usd' => $total]);
        $folio       = app(GenerateFolioAction::class)->handle($reservation)['data'];

        return [$reservation, $folio];
    }

    private function pay(Folio|Reservation $payable, string $amount, ?Carbon $at = null): Payment
    {
        $payment = Payment::factory()->create([
            'payable_type' => $payable::class,
            'payable_id'   => $payable->id,
            'amount_usd'   => $amount,
            'method'       => 'cash',
        ]);
        if ($at !== null) {
            $payment->forceFill(['created_at' => $at])->save();
        }
        return $payment;
    }

    public function test_reception_reads_the_full_folio_shape(): void
    {
        [$reservation, $folio] = $this->generatedStay('300.00');
        $poster = User::factory()->create(['name' => 'Desk Agent']);
        $room   = $folio->items->first();
        $minibar = FolioItem::factory()->manual()->create(['folio_id' => $folio->id, 'posted_by' => $poster->id, 'quantity' => 2, 'unit_price_usd' => '12.50', 'amount_usd' => '25.00']);
        FolioItem::factory()->credit()->create([
            'folio_id' => $folio->id, 'posted_by' => $poster->id, 'amount_usd' => '-5.00', 'unit_price_usd' => '5.00',
            'reason' => 'Late room', 'reverses_item_id' => $room->id,
        ]);
        $folio->recalculateTotals();
        $this->pay($reservation, '100.00');
        $this->pay($folio, '20.00');

        $response = $this->read($reservation, $this->presetToken('reception'))->assertOk();

        $response->assertJsonPath('success', true)
            ->assertJsonPath('data.uuid', $folio->uuid)
            ->assertJsonPath('data.reservation_uuid', $reservation->uuid)
            ->assertJsonPath('data.status', 'open')
            ->assertJsonPath('data.total_usd', '320.00')
            ->assertJsonPath('data.subtotal_usd', '320.00')
            ->assertJsonPath('data.paid_usd', '120.00')
            ->assertJsonPath('data.balance_due_usd', '200.00')
            ->assertJsonPath('data.open_disputes_count', 0)
            ->assertJsonCount(3, 'data.items')
            ->assertJsonCount(2, 'data.payments')
            ->assertJsonStructure(['success', 'message', 'data' => [
                'uuid', 'reservation_uuid', 'status', 'subtotal_usd', 'total_usd', 'approved_by_guest_at', 'settled_at',
                'items' => [['uuid', 'description', 'amount_usd', 'source_type', 'quantity', 'unit_price_usd', 'posted_by', 'posted_at', 'reason', 'reverses_item_uuid', 'dispute']],
                'payments' => [['uuid', 'method', 'amount_usd', 'status', 'note', 'created_at']],
                'paid_usd', 'balance_due_usd', 'open_disputes_count',
            ], 'request_id']);

        // Generated row: no poster, no unit price, no posted_at.
        $response->assertJsonPath('data.items.0.source_type', 'reservation')
            ->assertJsonPath('data.items.0.unit_price_usd', null)
            ->assertJsonPath('data.items.0.posted_by', null)
            ->assertJsonPath('data.items.0.posted_at', null)
            ->assertJsonPath('data.items.0.dispute', null);
        // Manual row.
        $response->assertJsonPath('data.items.1.uuid', $minibar->uuid)
            ->assertJsonPath('data.items.1.quantity', 2)
            ->assertJsonPath('data.items.1.unit_price_usd', '12.50')
            ->assertJsonPath('data.items.1.posted_by.uuid', $poster->uuid)
            ->assertJsonPath('data.items.1.posted_by.name', 'Desk Agent')
            ->assertJsonPath('data.items.1.reverses_item_uuid', null);
        $this->assertNotNull($response->json('data.items.1.posted_at'));
        // Credit row.
        $response->assertJsonPath('data.items.2.amount_usd', '-5.00')
            ->assertJsonPath('data.items.2.reason', 'Late room')
            ->assertJsonPath('data.items.2.reverses_item_uuid', $room->uuid);

        // Never a numeric id anywhere.
        $this->assertArrayNotHasKey('id', $response->json('data'));
        $this->assertArrayNotHasKey('id', $response->json('data.items.0'));
        $this->assertArrayNotHasKey('posted_by', array_filter(['posted_by' => $response->json('data.items.1.posted_by.id')]));
        // Payments omit recorded_by (approved deviation: not loaded on the read).
        $this->assertArrayNotHasKey('recorded_by', $response->json('data.payments.0'));
    }

    public function test_empty_folio_reads_all_zero(): void
    {
        $reservation = Reservation::factory()->confirmed()->create();
        Folio::factory()->create(['reservation_id' => $reservation->id]);

        $this->read($reservation, $this->presetToken('reception'))
            ->assertOk()
            ->assertJsonPath('data.items', [])
            ->assertJsonPath('data.payments', [])
            ->assertJsonPath('data.total_usd', '0.00')
            ->assertJsonPath('data.paid_usd', '0.00')
            ->assertJsonPath('data.balance_due_usd', '0.00')
            ->assertJsonPath('data.open_disputes_count', 0);
    }

    public function test_items_order_by_id_and_payments_by_created_at_then_id(): void
    {
        [$reservation, $folio] = $this->generatedStay();
        $a = FolioItem::factory()->manual()->create(['folio_id' => $folio->id]);
        $b = FolioItem::factory()->manual()->create(['folio_id' => $folio->id]);

        $t      = Carbon::parse('2027-01-01 10:00:00');
        $later  = $this->pay($folio, '1.00', $t->copy()->addHour());
        $first  = $this->pay($reservation, '2.00', $t);
        $second = $this->pay($folio, '3.00', $t);

        $response = $this->read($reservation, $this->presetToken('reception'))->assertOk();

        $this->assertSame(
            [$folio->items->first()->uuid, $a->uuid, $b->uuid],
            array_column($response->json('data.items'), 'uuid'),
        );
        $this->assertSame(
            [$first->uuid, $second->uuid, $later->uuid],
            array_column($response->json('data.payments'), 'uuid'),
        );
    }

    public function test_settled_folio_with_a_later_reservation_payment_shows_a_negative_balance(): void
    {
        [$reservation, $folio] = $this->generatedStay('100.00');
        $this->pay($folio, '100.00');
        $folio->update(['status' => FolioStatus::SETTLED, 'settled_at' => now()]);
        $this->pay($reservation, '15.00');

        $this->read($reservation, $this->presetToken('reception'))
            ->assertOk()
            ->assertJsonPath('data.status', 'settled')
            ->assertJsonPath('data.paid_usd', '115.00')
            ->assertJsonPath('data.balance_due_usd', '-15.00');
    }

    public function test_reservation_without_folio_is_404_folio_missing_and_nothing_is_created(): void
    {
        $reservation = Reservation::factory()->confirmed()->create();

        $this->read($reservation, $this->presetToken('reception'))
            ->assertStatus(404)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error_code', 'folio_missing')
            ->assertJsonPath('message', 'This reservation has no folio yet. Generate it first.')
            ->assertJsonPath('context.reservation_uuid', $reservation->uuid)
            ->assertJsonPath('context.reservation_status', 'confirmed');

        $this->assertSame(0, Folio::count());
    }

    public function test_unknown_reservation_uuid_is_404_not_found(): void
    {
        $this->app['auth']->forgetGuards();
        $this->withToken($this->presetToken('reception'))
            ->getJson('/api/cms/reservations/'.Str::uuid().'/folio')
            ->assertStatus(404)
            ->assertJsonPath('error_code', 'not_found');
    }

    public function test_read_is_pure_and_never_regenerates(): void
    {
        [$reservation, $folio] = $this->generatedStay('300.00');
        $reservation->update(['total_usd' => '999.00']);
        $itemsBefore = FolioItem::count();

        $this->read($reservation, $this->presetToken('reception'))
            ->assertOk()
            ->assertJsonPath('data.total_usd', '300.00');

        $this->assertSame($itemsBefore, FolioItem::count());
        $this->assertSame('300.00', $folio->fresh()->total_usd);
    }

    public function test_gates(): void
    {
        [$reservation] = $this->generatedStay();

        $this->read($reservation)->assertStatus(401)->assertJsonPath('error_code', 'unauthorized');

        $guest = Guest::factory()->create();
        $this->read($reservation, $guest->createToken('t')->plainTextToken)->assertStatus(401)->assertJsonPath('error_code', 'unauthorized');

        foreach (['housekeeping', 'kitchen', 'concierge'] as $preset) {
            $this->read($reservation, $this->presetToken($preset))->assertStatus(403)->assertJsonPath('error_code', 'forbidden');
        }

        $this->read($reservation, $this->staffToken('folios.view'))->assertOk();
        $this->read($reservation, $this->presetToken('reception'))->assertOk();
    }

    /** @return int queries run by the service read */
    private function serviceQueries(Reservation $reservation): int
    {
        $service     = app(FolioService::class);
        $reservation = $reservation->fresh();
        DB::flushQueryLog();
        DB::enableQueryLog();
        $service->adminShow($reservation);
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }

    public function test_read_is_bounded_to_six_queries_whatever_the_folio_size(): void
    {
        [$small, $smallFolio] = $this->generatedStay();
        [$large, $largeFolio] = $this->generatedStay();

        // Same relation graph on both, one posted/credited/disputed line vs eight.
        foreach ([[$small, $smallFolio, 1], [$large, $largeFolio, 8]] as [$reservation, $folio, $n]) {
            foreach (range(1, $n) as $i) {
                $item = FolioItem::factory()->manual()->create(['folio_id' => $folio->id]);
                FolioItem::factory()->credit()->create(['folio_id' => $folio->id, 'reverses_item_id' => $item->id, 'amount_usd' => '-1.00', 'unit_price_usd' => '1.00']);
                \App\Models\FolioItemDispute::factory()->create(['folio_item_id' => $item->id, 'guest_id' => $reservation->guest_id]);
                $this->pay($folio, '1.00');
                $this->pay($reservation, '1.00');
            }
        }

        $smallCount = $this->serviceQueries($small);
        $largeCount = $this->serviceQueries($large);

        $this->assertLessThanOrEqual(6, $smallCount);
        $this->assertSame($smallCount, $largeCount);
    }

    public function test_resource_resolution_runs_no_query(): void
    {
        [$reservation, $folio] = $this->generatedStay();
        $item = FolioItem::factory()->manual()->create(['folio_id' => $folio->id]);
        FolioItem::factory()->credit()->create(['folio_id' => $folio->id, 'reverses_item_id' => $item->id, 'amount_usd' => '-1.00', 'unit_price_usd' => '1.00']);
        \App\Models\FolioItemDispute::factory()->create(['folio_item_id' => $item->id, 'guest_id' => $reservation->guest_id]);
        $this->pay($folio, '1.00');

        $loaded = app(FolioService::class)->adminShow($reservation->fresh())['data'];
        $this->assertTrue($loaded->relationLoaded('ledgerPayments'));

        DB::flushQueryLog();
        DB::enableQueryLog();
        (new FolioResource($loaded))->resolve(Request::create('/'));
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertSame([], $queries);
    }

    public function test_receipt_and_folio_balances_agree(): void
    {
        [$reservation, $folio] = $this->generatedStay('100.00');
        FolioItem::factory()->manual()->create(['folio_id' => $folio->id, 'amount_usd' => '0.10', 'unit_price_usd' => '0.10']);
        $folio->recalculateTotals();
        $this->pay($reservation, '33.33');
        $this->pay($folio, '33.34');

        $folioBalance = $this->read($reservation, $this->presetToken('reception'))->assertOk()->json('data.balance_due_usd');
        $receipt      = app(ReceiptService::class)->forReservation($reservation->fresh());

        $this->assertSame('33.43', $folioBalance);
        $this->assertSame($folioBalance, $receipt['balance_due_usd']);
    }
}
