<?php

namespace Tests\Feature\Folio;

use App\Actions\Folio\GenerateFolioAction;
use App\Enums\FolioStatus;
use App\Enums\ServiceRequestStatus;
use App\Models\Folio;
use App\Models\FolioItem;
use App\Models\Guest;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\ServiceItem;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Support\FolioLedger;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\RecordsRowLocks;
use Tests\TestCase;

/**
 * Phase 5 FOLIO-02 (D-04, D-05, D-07, D-08): POST /api/cms/folios/{folio}/line-items.
 * Append-only: charges and credits, Idempotency-Key replay, two credit floors.
 */
class FolioLineItemTest extends TestCase
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

    private function postItem(Folio $folio, array $body, ?string $token = null, array $headers = []): TestResponse
    {
        $this->app['auth']->forgetGuards();
        $token ??= $this->presetToken('reception');

        return $this->withToken($token)
            ->postJson("/api/cms/folios/{$folio->uuid}/line-items", $body, $headers + ['Accept-Language' => 'en']);
    }

    private function generatedStay(string $total = '300.00', string $state = 'checkedIn'): array
    {
        $reservation = Reservation::factory()->{$state}()->create(['total_usd' => $total]);
        $folio       = app(GenerateFolioAction::class)->handle($reservation)['data'];

        return [$reservation, $folio];
    }

    private function charge(array $overrides = []): array
    {
        return array_merge(['description' => 'Minibar', 'quantity' => 1, 'unit_price_usd' => '25.00'], $overrides);
    }

    private function credit(array $overrides = []): array
    {
        return array_merge(['kind' => 'credit', 'description' => 'Adjustment', 'unit_price_usd' => '10.00', 'reason' => 'Guest complaint'], $overrides);
    }

    private function deposit(Reservation $reservation, string $amount): void
    {
        Payment::factory()->create(['payable_type' => Reservation::class, 'payable_id' => $reservation->id, 'amount_usd' => $amount]);
    }

    private function assertTotalsMatchRows(Folio $folio): void
    {
        $fresh = $folio->fresh();
        $sum   = FolioLedger::sum(FolioItem::where('folio_id', $folio->id)->pluck('amount_usd'));
        $this->assertSame($sum, $fresh->total_usd, 'total_usd equals the bcmath sum of stored rows (D-07)');
        $this->assertSame($sum, $fresh->subtotal_usd);
    }

    // ── Charges (05-03) ─────────────────────────────────────────────────

    public function test_reception_posts_a_charge(): void
    {
        [, $folio] = $this->generatedStay('300.00');
        $poster    = User::factory()->create();
        $token     = $this->presetToken('reception', $poster);

        $response = $this->postItem($folio, $this->charge(['quantity' => 2, 'unit_price_usd' => '12.50']), $token)
            ->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Folio line item posted.')
            ->assertJsonPath('data.uuid', $folio->uuid)
            ->assertJsonPath('data.total_usd', '325.00')
            ->assertJsonPath('data.balance_due_usd', '325.00')
            ->assertJsonCount(2, 'data.items')
            ->assertJsonPath('data.items.1.source_type', 'manual')
            ->assertJsonPath('data.items.1.quantity', 2)
            ->assertJsonPath('data.items.1.unit_price_usd', '12.50')
            ->assertJsonPath('data.items.1.amount_usd', '25.00')
            ->assertJsonPath('data.items.1.posted_by.uuid', $poster->uuid);
        $this->assertNotNull($response->json('data.items.1.posted_at'));

        $this->assertDatabaseHas('folio_items', [
            'folio_id' => $folio->id, 'source_type' => 'manual', 'source_id' => null, 'posted_by' => $poster->id,
            'amount_usd' => '25.00', 'idempotency_key' => null,
        ]);
        $this->assertTotalsMatchRows($folio);
    }

    public function test_kind_and_quantity_default_and_price_is_normalized(): void
    {
        [, $folio] = $this->generatedStay('0.00');

        $this->postItem($folio, ['description' => 'Laundry', 'unit_price_usd' => 10.5])
            ->assertStatus(201)
            ->assertJsonPath('data.items.1.source_type', 'manual')
            ->assertJsonPath('data.items.1.quantity', 1)
            ->assertJsonPath('data.items.1.unit_price_usd', '10.50')
            ->assertJsonPath('data.items.1.amount_usd', '10.50')
            ->assertJsonPath('data.total_usd', '10.50');
    }

    public function test_charge_validation(): void
    {
        [, $folio] = $this->generatedStay();
        $cases = [
            'unit_price_usd' => [['unit_price_usd' => 0], ['unit_price_usd' => '1.234'], ['unit_price_usd' => 100000], ['unit_price_usd' => 'abc']],
            'quantity'       => [['quantity' => 0], ['quantity' => 1000], ['quantity' => 'two']],
            'description'    => [['description' => str_repeat('x', 256)], ['description' => '']],
            'kind'           => [['kind' => 'refund']],
        ];

        foreach ($cases as $field => $bodies) {
            foreach ($bodies as $override) {
                $this->postItem($folio, $this->charge($override))
                    ->assertStatus(422)
                    ->assertJsonPath('error_code', 'validation_failed')
                    ->assertJsonValidationErrors([$field]);
            }
        }

        $this->postItem($folio, $this->charge(['unit_price_usd' => '1.234']))
            ->assertJsonPath('errors.unit_price_usd.0', 'The unit price usd must have 0-2 decimal places.');

        $this->assertSame(1, FolioItem::count(), 'no invalid request wrote a row');
    }

    public function test_decimal_message_is_localized(): void
    {
        [, $folio] = $this->generatedStay();

        $this->postItem($folio, $this->charge(['unit_price_usd' => '1.234']), null, ['Accept-Language' => 'ar'])
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'validation_failed');
        $ar = $this->postItem($folio, $this->charge(['unit_price_usd' => '1.234']), null, ['Accept-Language' => 'ar'])
            ->json('errors.unit_price_usd.0');

        $this->assertNotSame('The unit price usd must have 0-2 decimal places.', $ar);
        $this->assertStringNotContainsString('validation.decimal', $ar);
    }

    public function test_settled_folio_refuses_a_charge_without_writing(): void
    {
        [, $folio] = $this->generatedStay();
        $folio->update(['status' => FolioStatus::SETTLED, 'settled_at' => now()]);

        $this->postItem($folio, $this->charge())
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'folio_settled')
            ->assertJsonPath('context.folio_uuid', $folio->uuid);

        $this->assertSame(1, FolioItem::count());
    }

    public function test_checked_out_stay_with_an_open_folio_still_accepts_a_charge(): void
    {
        [$reservation, $folio] = $this->generatedStay('300.00');
        $reservation->update(['status' => 'checked_out']);

        $this->postItem($folio, $this->charge())->assertStatus(201)->assertJsonPath('data.total_usd', '325.00');
    }

    // ── Idempotency (D-08) ──────────────────────────────────────────────

    public function test_identical_retry_with_the_same_key_replays_200(): void
    {
        [, $folio] = $this->generatedStay('300.00');

        $this->postItem($folio, $this->charge(), null, ['Idempotency-Key' => 'K-1'])->assertStatus(201);
        $this->postItem($folio, $this->charge(), null, ['Idempotency-Key' => 'K-1'])
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.total_usd', '325.00')
            ->assertJsonCount(2, 'data.items');

        $this->assertSame(1, FolioItem::where('idempotency_key', 'K-1')->count());
    }

    public function test_same_key_with_a_different_payload_is_409(): void
    {
        [, $folio] = $this->generatedStay();

        $this->postItem($folio, $this->charge(), null, ['Idempotency-Key' => 'K-1'])->assertStatus(201);
        $this->postItem($folio, $this->charge(['unit_price_usd' => '26.00']), null, ['Idempotency-Key' => 'K-1'])
            ->assertStatus(409)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error_code', 'idempotency_conflict')
            ->assertJsonPath('context.idempotency_key', 'K-1');
        $this->postItem($folio, $this->charge(['description' => 'Other']), null, ['Idempotency-Key' => 'K-1'])
            ->assertStatus(409);

        $this->assertSame(2, FolioItem::count());
    }

    public function test_same_key_on_another_folio_is_independent(): void
    {
        [, $a] = $this->generatedStay();
        [, $b] = $this->generatedStay();

        $this->postItem($a, $this->charge(), null, ['Idempotency-Key' => 'K-1'])->assertStatus(201);
        $this->postItem($b, $this->charge(), null, ['Idempotency-Key' => 'K-1'])->assertStatus(201);
    }

    public function test_posts_without_a_key_create_two_rows(): void
    {
        [, $folio] = $this->generatedStay('300.00');

        $this->postItem($folio, $this->charge())->assertStatus(201);
        $this->postItem($folio, $this->charge())->assertStatus(201)->assertJsonPath('data.total_usd', '350.00');

        $this->assertSame(2, FolioItem::where('source_type', 'manual')->count());
    }

    public function test_blank_key_is_treated_as_absent_and_long_key_is_refused(): void
    {
        [, $folio] = $this->generatedStay();

        $this->postItem($folio, $this->charge(), null, ['Idempotency-Key' => '   '])->assertStatus(201);
        $this->postItem($folio, $this->charge(), null, ['Idempotency-Key' => str_repeat('k', 65)])
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'validation_failed')
            ->assertJsonValidationErrors(['idempotency_key']);
        $this->postItem($folio, $this->charge(), null, ['Idempotency-Key' => str_repeat('k', 64)])->assertStatus(201);
    }

    public function test_body_idempotency_key_is_ignored_without_the_header(): void
    {
        [, $folio] = $this->generatedStay();

        $this->postItem($folio, $this->charge(['idempotency_key' => 'BODY']))->assertStatus(201);
        $this->postItem($folio, $this->charge(['idempotency_key' => 'BODY']))->assertStatus(201);

        $this->assertSame(2, FolioItem::where('source_type', 'manual')->whereNull('idempotency_key')->count());
    }

    public function test_replay_is_answered_before_the_settled_guard(): void
    {
        [, $folio] = $this->generatedStay();

        $this->postItem($folio, $this->charge(), null, ['Idempotency-Key' => 'K-1'])->assertStatus(201);
        $folio->update(['status' => FolioStatus::SETTLED, 'settled_at' => now()]);

        $this->postItem($folio, $this->charge(), null, ['Idempotency-Key' => 'K-1'])
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'settled');
        $this->postItem($folio, $this->charge(), null, ['Idempotency-Key' => 'K-2'])
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'folio_settled');
    }

    // ── Ledger behaviour ────────────────────────────────────────────────

    public function test_posted_rows_survive_regeneration_and_the_guest_read(): void
    {
        $guest       = Guest::factory()->create();
        $reservation = Reservation::factory()->checkedIn()->create(['guest_id' => $guest->id, 'total_usd' => '300.00']);
        $folio       = app(GenerateFolioAction::class)->handle($reservation)['data'];
        $uuid        = $this->postItem($folio, $this->charge())->assertStatus(201)->json('data.items.1.uuid');

        $this->app['auth']->forgetGuards();
        $this->withToken($this->staffToken('folios.view'))
            ->postJson("/api/cms/folios/{$reservation->uuid}/generate")
            ->assertOk()
            ->assertJsonPath('data.total_usd', '325.00')
            ->assertJsonPath('data.items.1.uuid', $uuid);

        $this->app['auth']->forgetGuards();
        $this->withToken($guest->createToken('t')->plainTextToken)
            ->getJson('/api/folio')
            ->assertOk()
            ->assertJsonPath('data.total_usd', '325.00')
            ->assertJsonPath('data.items.1.uuid', $uuid);
    }

    public function test_totals_always_equal_the_bcmath_sum_of_stored_rows(): void
    {
        [, $folio] = $this->generatedStay('0.00');

        foreach (['0.10', '0.10', '0.10', '19.99', '0.01'] as $price) {
            $this->postItem($folio, $this->charge(['unit_price_usd' => $price]))->assertStatus(201);
            $this->assertTotalsMatchRows($folio);
        }
        $this->postItem($folio, $this->charge(['quantity' => 3, 'unit_price_usd' => '0.33']))->assertStatus(201);

        $this->assertSame('21.29', $folio->fresh()->total_usd);
        $this->assertTotalsMatchRows($folio);
    }

    public function test_posting_locks_the_folio_row(): void
    {
        [, $folio] = $this->generatedStay();
        $token     = $this->presetToken('reception');

        $this->assertLocksRow('folios', fn () => $this->postItem($folio, $this->charge(), $token)->assertStatus(201));
        $this->assertLocksRow('folios', fn () => $this->postItem($folio, $this->credit(), $token)->assertStatus(201));
    }

    // ── Gates and routes ────────────────────────────────────────────────

    public function test_gates(): void
    {
        [, $folio] = $this->generatedStay();

        $this->app['auth']->forgetGuards();
        $this->postJson("/api/cms/folios/{$folio->uuid}/line-items", $this->charge())
            ->assertStatus(401)->assertJsonPath('error_code', 'unauthorized');

        $guest = Guest::factory()->create();
        $this->postItem($folio, $this->charge(), $guest->createToken('t')->plainTextToken)->assertStatus(401);

        foreach (['kitchen', 'housekeeping', 'concierge', 'events'] as $preset) {
            $this->postItem($folio, $this->charge(), $this->presetToken($preset))
                ->assertStatus(403)->assertJsonPath('error_code', 'forbidden');
        }
        $this->postItem($folio, $this->charge(), $this->staffToken('folios.view', 'folios.settle'))->assertStatus(403);

        $this->assertSame(0, FolioItem::where('source_type', 'manual')->count());

        $this->postItem($folio, $this->charge(), $this->staffToken('folios.post'))->assertStatus(201);

        $this->app['auth']->forgetGuards();
        $this->withToken($this->presetToken('reception'))
            ->postJson('/api/cms/folios/'.Str::uuid().'/line-items', $this->charge())
            ->assertStatus(404)->assertJsonPath('error_code', 'not_found');
    }

    public function test_router_exposes_no_edit_or_delete_route_for_items_or_payments(): void
    {
        $methods = [];
        foreach (Route::getRoutes() as $route) {
            $uri = $route->uri();
            if (preg_match('#folios/\{folio\}/(line-items|payments)#', $uri) || preg_match('#folio-items|folio/items/\{item\}$#', $uri)) {
                foreach ($route->methods() as $method) {
                    $methods[$uri][] = $method;
                }
            }
        }

        $this->assertSame(['POST'], $methods['api/cms/folios/{folio}/line-items']);
        $this->assertSame(['POST'], $methods['api/cms/folios/{folio}/payments']);
        $this->assertSame(['PATCH'], $methods['api/cms/folios/{folio}/line-items/{item}/dispute']);
        foreach ($methods as $uri => $verbs) {
            $this->assertEmpty(array_intersect($verbs, ['PUT', 'DELETE']), "{$uri} must not be editable or deletable");
            if (! str_ends_with($uri, '/dispute')) {
                $this->assertNotContains('PATCH', $verbs, "{$uri} must not be editable");
            }
        }
    }

    // ── Credits (05-04) ─────────────────────────────────────────────────

    public function test_credit_reverses_a_charge(): void
    {
        [, $folio] = $this->generatedStay('300.00');
        $chargeUuid = $this->postItem($folio, $this->charge())->json('data.items.1.uuid');
        $poster     = User::factory()->create();

        $this->postItem($folio, $this->credit(['reverses_item_uuid' => $chargeUuid]), $this->presetToken('reception', $poster))
            ->assertStatus(201)
            ->assertJsonPath('data.total_usd', '315.00')
            ->assertJsonPath('data.items.2.source_type', 'credit')
            ->assertJsonPath('data.items.2.amount_usd', '-10.00')
            ->assertJsonPath('data.items.2.unit_price_usd', '10.00')
            ->assertJsonPath('data.items.2.reason', 'Guest complaint')
            ->assertJsonPath('data.items.2.reverses_item_uuid', $chargeUuid)
            ->assertJsonPath('data.items.2.posted_by.uuid', $poster->uuid);

        $charge = FolioItem::where('uuid', $chargeUuid)->sole();
        $this->assertDatabaseHas('folio_items', ['source_type' => 'credit', 'reverses_item_id' => $charge->id, 'amount_usd' => '-10.00']);
        $this->assertTotalsMatchRows($folio);
    }

    public function test_credit_requires_a_reason(): void
    {
        [, $folio] = $this->generatedStay();

        $this->postItem($folio, $this->credit(['reason' => null]))
            ->assertStatus(422)->assertJsonPath('error_code', 'validation_failed')->assertJsonValidationErrors(['reason']);
        $this->postItem($folio, $this->credit(['reason' => str_repeat('r', 256)]))
            ->assertStatus(422)->assertJsonValidationErrors(['reason']);
    }

    public function test_reverses_item_uuid_is_credit_only_and_scoped_to_the_folio(): void
    {
        [, $folio] = $this->generatedStay();
        [, $other] = $this->generatedStay();
        $mine    = $folio->items->first()->uuid;
        $foreign = $other->items->first()->uuid;

        $this->postItem($folio, $this->charge(['reverses_item_uuid' => $mine]))
            ->assertStatus(422)->assertJsonValidationErrors(['reverses_item_uuid']);
        $this->postItem($folio, $this->credit(['reverses_item_uuid' => $foreign]))
            ->assertStatus(422)->assertJsonValidationErrors(['reverses_item_uuid']);
        $this->postItem($folio, $this->credit(['reverses_item_uuid' => 'not-a-uuid']))
            ->assertStatus(422)->assertJsonValidationErrors(['reverses_item_uuid']);
        $this->postItem($folio, $this->credit(['reverses_item_uuid' => (string) Str::uuid()]))
            ->assertStatus(422)->assertJsonValidationErrors(['reverses_item_uuid']);

        $this->assertSame(0, FolioItem::where('source_type', 'credit')->count());
    }

    public function test_goodwill_credit_without_a_reversed_item_is_allowed(): void
    {
        [, $folio] = $this->generatedStay('300.00');

        $this->postItem($folio, $this->credit())
            ->assertStatus(201)
            ->assertJsonPath('data.total_usd', '290.00')
            ->assertJsonPath('data.items.1.reverses_item_uuid', null);
    }

    public function test_item_floor_accepts_exact_and_refuses_one_cent_over(): void
    {
        [, $folio] = $this->generatedStay('300.00');
        $chargeUuid = $this->postItem($folio, $this->charge())->json('data.items.1.uuid');

        $this->postItem($folio, $this->credit(['reverses_item_uuid' => $chargeUuid, 'unit_price_usd' => '25.01']))
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'folio_credit_exceeds_item')
            ->assertJsonPath('context.item_uuid', $chargeUuid)
            ->assertJsonPath('context.remaining_usd', '25.00')
            ->assertJsonPath('context.amount_usd', '25.01');

        $this->postItem($folio, $this->credit(['reverses_item_uuid' => $chargeUuid, 'unit_price_usd' => '25.00']))
            ->assertStatus(201)
            ->assertJsonPath('data.total_usd', '300.00');
    }

    public function test_item_floor_is_cumulative_across_credits(): void
    {
        [, $folio] = $this->generatedStay('300.00');
        $chargeUuid = $this->postItem($folio, $this->charge())->json('data.items.1.uuid');

        $this->postItem($folio, $this->credit(['reverses_item_uuid' => $chargeUuid]))->assertStatus(201);
        $this->postItem($folio, $this->credit(['reverses_item_uuid' => $chargeUuid]))->assertStatus(201);
        $this->postItem($folio, $this->credit(['reverses_item_uuid' => $chargeUuid, 'unit_price_usd' => '5.01']))
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'folio_credit_exceeds_item')
            ->assertJsonPath('context.remaining_usd', '5.00');
        $this->postItem($folio, $this->credit(['reverses_item_uuid' => $chargeUuid, 'unit_price_usd' => '5.00']))->assertStatus(201);
        $this->postItem($folio, $this->credit(['reverses_item_uuid' => $chargeUuid, 'unit_price_usd' => '0.01']))
            ->assertStatus(422)
            ->assertJsonPath('context.remaining_usd', '0.00');
    }

    public function test_item_floor_uses_quantity_times_price(): void
    {
        [, $folio] = $this->generatedStay('300.00');
        $chargeUuid = $this->postItem($folio, $this->charge())->json('data.items.1.uuid');

        $this->postItem($folio, $this->credit(['reverses_item_uuid' => $chargeUuid, 'quantity' => 3, 'unit_price_usd' => '8.34']))
            ->assertStatus(422)
            ->assertJsonPath('context.amount_usd', '25.02');
    }

    public function test_balance_floor_refuses_a_credit_below_zero_counting_deposits(): void
    {
        [$reservation, $folio] = $this->generatedStay('10.00');
        $this->deposit($reservation, '10.00');

        // Zero balance: even one cent of goodwill is refused.
        $this->postItem($folio, $this->credit(['unit_price_usd' => '0.01']))
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'folio_credit_exceeds_balance')
            ->assertJsonPath('context.balance_due_usd', '0.00')
            ->assertJsonPath('context.amount_usd', '0.01');

        $this->assertSame(0, FolioItem::where('source_type', 'credit')->count());
    }

    public function test_balance_floor_boundary_at_exactly_the_balance(): void
    {
        [, $folio] = $this->generatedStay('10.00');

        $this->postItem($folio, $this->credit(['unit_price_usd' => '10.01']))
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'folio_credit_exceeds_balance')
            ->assertJsonPath('context.balance_due_usd', '10.00');
        $this->postItem($folio, $this->credit(['unit_price_usd' => '10.00']))
            ->assertStatus(201)
            ->assertJsonPath('data.total_usd', '0.00')
            ->assertJsonPath('data.balance_due_usd', '0.00');
    }

    public function test_item_floor_answers_before_the_balance_floor(): void
    {
        [$reservation, $folio] = $this->generatedStay('0.00');
        $chargeUuid = $this->postItem($folio, $this->charge())->json('data.items.1.uuid');
        $this->deposit($reservation, '25.00');

        $this->postItem($folio, $this->credit(['reverses_item_uuid' => $chargeUuid, 'unit_price_usd' => '30.00']))
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'folio_credit_exceeds_item');
    }

    public function test_crediting_a_credit_is_refused(): void
    {
        [, $folio] = $this->generatedStay('300.00');
        $creditUuid = $this->postItem($folio, $this->credit())->json('data.items.1.uuid');

        $this->postItem($folio, $this->credit(['reverses_item_uuid' => $creditUuid, 'unit_price_usd' => '0.01']))
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'folio_credit_exceeds_item');
    }

    public function test_credit_replay_compares_the_reversed_item(): void
    {
        [, $folio] = $this->generatedStay('300.00');
        $a = $this->postItem($folio, $this->charge())->json('data.items.1.uuid');
        $b = $this->postItem($folio, $this->charge())->json('data.items.2.uuid');

        $this->postItem($folio, $this->credit(['reverses_item_uuid' => $a]), null, ['Idempotency-Key' => 'C-1'])->assertStatus(201);
        $this->postItem($folio, $this->credit(['reverses_item_uuid' => $a]), null, ['Idempotency-Key' => 'C-1'])->assertStatus(200);
        $this->postItem($folio, $this->credit(['reverses_item_uuid' => $b]), null, ['Idempotency-Key' => 'C-1'])
            ->assertStatus(409)->assertJsonPath('error_code', 'idempotency_conflict');
        $this->postItem($folio, $this->charge(['unit_price_usd' => '10.00', 'description' => 'Adjustment']), null, ['Idempotency-Key' => 'C-1'])
            ->assertStatus(409);

        $this->assertSame(1, FolioItem::where('source_type', 'credit')->count());
    }

    public function test_credit_on_a_settled_folio_is_refused(): void
    {
        [, $folio] = $this->generatedStay('300.00');
        $folio->update(['status' => FolioStatus::SETTLED, 'settled_at' => now()]);

        $this->postItem($folio, $this->credit(['reverses_item_uuid' => $folio->items->first()->uuid]))
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'folio_settled');
    }

    public function test_interleaved_writers_and_regenerations_keep_the_total_invariant(): void
    {
        $reservation = Reservation::factory()->checkedIn()->create(['total_usd' => '200.00']);
        $request     = ServiceRequest::factory()->create([
            'guest_id' => $reservation->guest_id, 'reservation_id' => $reservation->id,
            'service_item_id' => ServiceItem::factory()->priced(15)->create()->id,
        ]);
        $folio = app(GenerateFolioAction::class)->handle($reservation)['data'];
        $alice = $this->presetToken('reception');
        $bob   = $this->presetToken('reception');
        $regen = fn () => app(GenerateFolioAction::class)->handle($reservation->fresh());

        $c1 = $this->postItem($folio, $this->charge(['unit_price_usd' => '33.33']), $alice)->assertStatus(201)->json('data.items.2.uuid');
        $this->assertTotalsMatchRows($folio);
        $this->postItem($folio, $this->charge(['unit_price_usd' => '0.01']), $bob)->assertStatus(201);
        $this->assertTotalsMatchRows($folio);
        $regen();
        $this->assertTotalsMatchRows($folio);
        $this->postItem($folio, $this->credit(['reverses_item_uuid' => $c1, 'unit_price_usd' => '3.33']), $bob)->assertStatus(201);
        $this->assertTotalsMatchRows($folio);
        $reservation->update(['total_usd' => '210.00']);
        $request->update(['status' => ServiceRequestStatus::CANCELLED]);
        $regen();
        $this->assertTotalsMatchRows($folio);
        $this->postItem($folio, $this->credit(['unit_price_usd' => '0.34']), $alice)->assertStatus(201);
        $this->assertTotalsMatchRows($folio);

        // 210 room (repriced) + 33.33 + 0.01 - 3.33 - 0.34; the cancelled request dropped off.
        $this->assertSame('239.67', $folio->fresh()->total_usd);
    }

    public function test_credited_generated_row_survives_source_cancellation(): void
    {
        $reservation = Reservation::factory()->checkedIn()->create(['total_usd' => '100.00']);
        $request     = ServiceRequest::factory()->create([
            'guest_id' => $reservation->guest_id, 'reservation_id' => $reservation->id,
            'service_item_id' => ServiceItem::factory()->priced(15)->create()->id,
        ]);
        $folio  = app(GenerateFolioAction::class)->handle($reservation)['data'];
        $charge = $folio->items->firstWhere('source_type', 'service_request');

        $this->postItem($folio, $this->credit(['reverses_item_uuid' => $charge->uuid, 'unit_price_usd' => '5.00']))->assertStatus(201);
        $request->update(['status' => ServiceRequestStatus::CANCELLED]);
        app(GenerateFolioAction::class)->handle($reservation->fresh());

        $this->assertDatabaseHas('folio_items', ['uuid' => $charge->uuid, 'amount_usd' => '15.00']);
        $this->assertSame('110.00', $folio->fresh()->total_usd);
    }
}
