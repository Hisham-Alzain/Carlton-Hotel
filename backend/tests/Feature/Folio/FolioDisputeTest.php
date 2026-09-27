<?php

namespace Tests\Feature\Folio;

use App\Actions\Folio\GenerateFolioAction;
use App\Enums\FolioDisputeStatus;
use App\Enums\FolioStatus;
use App\Models\Folio;
use App\Models\FolioItem;
use App\Models\FolioItemDispute;
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
 * Phase 5 FOLIO-03 (D-09 .. D-12):
 *  - PATCH /api/folio/items/{item}/dispute (guest; ownership → 404);
 *  - PATCH /api/cms/folios/{folio}/line-items/{item}/dispute (staff raise/resolve/reject);
 *  - open_disputes_count flag and has_open_disputes filter; never a check-out gate.
 */
class FolioDisputeTest extends TestCase
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

    private function guestToken(Guest $guest): string
    {
        return $guest->createToken('t')->plainTextToken;
    }

    /** A checked-in guest with a generated folio (one room line). */
    private function guestStay(string $total = '300.00'): array
    {
        $guest       = Guest::factory()->create();
        $reservation = Reservation::factory()->checkedIn()->create(['guest_id' => $guest->id, 'total_usd' => $total]);
        $folio       = app(GenerateFolioAction::class)->handle($reservation)['data'];

        return [$guest, $reservation, $folio, $folio->items->first()];
    }

    private function guestDispute(FolioItem|string $item, array $body, ?string $token): TestResponse
    {
        $this->app['auth']->forgetGuards();
        $uuid    = $item instanceof FolioItem ? $item->uuid : $item;
        $request = $token === null ? $this : $this->withToken($token);

        return $request->patchJson("/api/folio/items/{$uuid}/dispute", $body, ['Accept-Language' => 'en']);
    }

    private function staffDispute(Folio|string $folio, FolioItem|string $item, array $body, ?string $token = null): TestResponse
    {
        $this->app['auth']->forgetGuards();
        $f = $folio instanceof Folio ? $folio->uuid : $folio;
        $i = $item instanceof FolioItem ? $item->uuid : $item;

        return $this->withToken($token ?? $this->presetToken('reception'))
            ->patchJson("/api/cms/folios/{$f}/line-items/{$i}/dispute", $body, ['Accept-Language' => 'en']);
    }

    private function staffRead(Reservation $reservation): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($this->presetToken('reception'))->getJson("/api/cms/reservations/{$reservation->uuid}/folio");
    }

    private function guestRead(Guest $guest): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($this->guestToken($guest))->getJson('/api/folio');
    }

    private function moneySnapshot(Folio $folio): array
    {
        $fresh = $folio->fresh();

        return [$fresh->total_usd, $fresh->subtotal_usd, $fresh->status, FolioItem::count(), Payment::count(), $fresh->balanceDueUsd()];
    }

    // ── Guest (05-07) ───────────────────────────────────────────────────

    public function test_guest_raises_a_dispute_on_their_own_item(): void
    {
        [$guest, , , $item] = $this->guestStay();

        $response = $this->guestDispute($item, ['reason' => 'I never stayed the second night'], $this->guestToken($guest))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Dispute raised.')
            ->assertJsonPath('data.uuid', $item->uuid)
            ->assertJsonPath('data.dispute.status', 'open')
            ->assertJsonPath('data.dispute.reason', 'I never stayed the second night')
            ->assertJsonPath('data.dispute.raised_by', 'guest')
            ->assertJsonPath('data.dispute.resolved_at', null)
            ->assertJsonPath('data.dispute.resolution_note', null);
        $this->assertTrue(Str::isUuid($response->json('data.dispute.uuid')));
        $this->assertArrayNotHasKey('id', $response->json('data.dispute'));

        $this->assertDatabaseHas('folio_item_disputes', [
            'folio_item_id' => $item->id, 'guest_id' => $guest->id, 'user_id' => null, 'status' => 'open',
        ]);
    }

    public function test_dispute_shows_on_guest_and_staff_reads(): void
    {
        [$guest, $reservation, , $item] = $this->guestStay();

        $this->guestRead($guest)->assertOk()->assertJsonPath('data.items.0.dispute', null);
        $this->staffRead($reservation)->assertOk()->assertJsonPath('data.items.0.dispute', null);

        $this->guestDispute($item, ['reason' => 'Wrong rate'], $this->guestToken($guest))->assertOk();

        $this->guestRead($guest)->assertOk()->assertJsonPath('data.items.0.dispute.status', 'open');
        $this->staffRead($reservation)->assertOk()->assertJsonPath('data.items.0.dispute.status', 'open');
    }

    public function test_foreign_item_is_indistinguishable_from_an_unknown_uuid(): void
    {
        [$guest] = $this->guestStay();
        [, , , $foreign] = $this->guestStay();
        $token = $this->guestToken($guest);

        $foreignResponse = $this->guestDispute($foreign, ['reason' => 'x'], $token)
            ->assertStatus(404)->assertJsonPath('error_code', 'not_found');
        $unknownResponse = $this->guestDispute((string) Str::uuid(), ['reason' => 'x'], $token)
            ->assertStatus(404)->assertJsonPath('error_code', 'not_found');

        $strip = fn (array $body) => array_diff_key($body, ['request_id' => true]);
        $this->assertSame($strip($unknownResponse->json()), $strip($foreignResponse->json()));

        // Ownership is checked before validation.
        $this->guestDispute($foreign, [], $token)->assertStatus(404)->assertJsonPath('error_code', 'not_found');

        $this->assertSame(0, FolioItemDispute::count());
    }

    public function test_ownership_follows_the_guest_not_the_current_stay(): void
    {
        [$guest, , , $currentItem] = $this->guestStay();
        // An earlier stay of the same guest, already checked out with its folio.
        $earlier     = Reservation::factory()->checkedOut()->create(['guest_id' => $guest->id, 'total_usd' => '80.00']);
        $earlierItem = app(GenerateFolioAction::class)->handle($earlier)['data']->items->first();

        $this->guestDispute($earlierItem, ['reason' => 'Old charge'], $this->guestToken($guest))->assertOk();
        $this->guestDispute($currentItem, ['reason' => 'New charge'], $this->guestToken($guest))->assertOk();
    }

    public function test_guest_gates(): void
    {
        [$guest, , , $item] = $this->guestStay();

        $this->guestDispute($item, ['reason' => 'x'], null)->assertStatus(401)->assertJsonPath('error_code', 'unauthorized');
        $this->guestDispute($item, ['reason' => 'x'], $this->presetToken('reception'))->assertStatus(401);

        $notIn = Guest::factory()->create();
        Reservation::factory()->confirmed()->create(['guest_id' => $notIn->id]);
        $this->guestDispute($item, ['reason' => 'x'], $this->guestToken($notIn))
            ->assertStatus(403)->assertJsonPath('error_code', 'no_active_reservation');

        $this->assertSame(0, FolioItemDispute::count());
    }

    public function test_second_open_dispute_is_refused(): void
    {
        [$guest, , , $item] = $this->guestStay();
        $token = $this->guestToken($guest);

        $this->guestDispute($item, ['reason' => 'first'], $token)->assertOk();
        $open = FolioItemDispute::sole();

        $this->guestDispute($item, ['reason' => 'second'], $token)
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'folio_item_dispute_open')
            ->assertJsonPath('context.item_uuid', $item->uuid)
            ->assertJsonPath('context.dispute_uuid', $open->uuid);

        $this->assertSame(1, FolioItemDispute::count());
    }

    public function test_re_dispute_after_resolution_latest_wins(): void
    {
        [$guest, , , $item] = $this->guestStay();
        FolioItemDispute::factory()->resolved()->create(['folio_item_id' => $item->id, 'guest_id' => $guest->id]);

        $this->guestDispute($item, ['reason' => 'Still wrong'], $this->guestToken($guest))
            ->assertOk()
            ->assertJsonPath('data.dispute.status', 'open')
            ->assertJsonPath('data.dispute.reason', 'Still wrong');

        $this->assertSame(2, FolioItemDispute::where('folio_item_id', $item->id)->count());
    }

    public function test_dispute_is_allowed_on_a_settled_folio(): void
    {
        [$guest, , $folio, $item] = $this->guestStay();
        $folio->update(['status' => FolioStatus::SETTLED, 'settled_at' => now()]);

        $this->guestDispute($item, ['reason' => 'After the fact'], $this->guestToken($guest))
            ->assertOk()->assertJsonPath('data.dispute.status', 'open');
    }

    public function test_guest_dispute_validation(): void
    {
        [$guest, , , $item] = $this->guestStay();
        $token = $this->guestToken($guest);

        $this->guestDispute($item, [], $token)
            ->assertStatus(422)->assertJsonPath('error_code', 'validation_failed')->assertJsonValidationErrors(['reason']);
        $this->guestDispute($item, ['reason' => str_repeat('r', 501)], $token)
            ->assertStatus(422)->assertJsonValidationErrors(['reason']);
        $this->guestDispute($item, ['reason' => str_repeat('r', 500)], $token)->assertOk();
    }

    public function test_raising_a_dispute_moves_no_money(): void
    {
        [$guest, $reservation, $folio, $item] = $this->guestStay('300.00');
        Payment::factory()->create(['payable_type' => Reservation::class, 'payable_id' => $reservation->id, 'amount_usd' => '50.00']);
        $before = $this->moneySnapshot($folio);

        $this->guestDispute($item, ['reason' => 'x'], $this->guestToken($guest))->assertOk();

        $this->assertEquals($before, $this->moneySnapshot($folio));
    }

    public function test_guest_raise_is_logged_with_the_guest_as_causer(): void
    {
        [$guest, , , $item] = $this->guestStay();

        $this->guestDispute($item, ['reason' => 'x'], $this->guestToken($guest))->assertOk();

        $log = Activity::where('subject_type', FolioItemDispute::class)->where('event', 'created')->sole();
        $this->assertSame(Guest::class, $log->causer_type);
        $this->assertSame($guest->id, (int) $log->causer_id);
    }

    public function test_guest_raise_locks_the_folio_row(): void
    {
        [$guest, , , $item] = $this->guestStay();
        $token = $this->guestToken($guest);

        $this->assertLocksRow('folios', fn () => $this->guestDispute($item, ['reason' => 'x'], $token)->assertOk());
    }

    // ── Staff (05-08) ───────────────────────────────────────────────────

    public function test_staff_raises_a_dispute(): void
    {
        [, , $folio, $item] = $this->guestStay();
        $desk = User::factory()->create();

        $this->staffDispute($folio, $item, ['action' => 'raise', 'reason' => 'Guest called the desk'], $this->presetToken('reception', $desk))
            ->assertOk()
            ->assertJsonPath('message', 'Dispute raised.')
            ->assertJsonPath('data.uuid', $item->uuid)
            ->assertJsonPath('data.dispute.status', 'open')
            ->assertJsonPath('data.dispute.raised_by', 'staff');

        $this->assertDatabaseHas('folio_item_disputes', ['folio_item_id' => $item->id, 'user_id' => $desk->id, 'guest_id' => null]);
    }

    public function test_staff_resolves_and_rejects_open_disputes(): void
    {
        [$guest, , $folio, $item] = $this->guestStay();
        $other = FolioItem::factory()->manual()->create(['folio_id' => $folio->id]);
        FolioItemDispute::factory()->create(['folio_item_id' => $item->id, 'guest_id' => $guest->id]);
        FolioItemDispute::factory()->create(['folio_item_id' => $other->id, 'guest_id' => $guest->id]);
        $desk = User::factory()->create();
        $token = $this->presetToken('reception', $desk);

        $this->staffDispute($folio, $item, ['action' => 'resolve', 'note' => 'Refunded by credit'], $token)
            ->assertOk()
            ->assertJsonPath('message', 'Dispute resolved.')
            ->assertJsonPath('data.dispute.status', 'resolved')
            ->assertJsonPath('data.dispute.resolution_note', 'Refunded by credit');
        $this->staffDispute($folio, $other, ['action' => 'reject', 'note' => 'Charge confirmed'], $token)
            ->assertOk()
            ->assertJsonPath('message', 'Dispute rejected.')
            ->assertJsonPath('data.dispute.status', 'rejected');

        $resolved = FolioItemDispute::where('folio_item_id', $item->id)->sole();
        $this->assertSame(FolioDisputeStatus::RESOLVED, $resolved->status);
        $this->assertSame($desk->id, $resolved->resolved_by);
        $this->assertNotNull($resolved->resolved_at);
        $this->assertSame('Refunded by credit', $resolved->resolution_note);

        $log = Activity::where('subject_type', FolioItemDispute::class)->where('subject_id', $resolved->id)->where('event', 'updated')->sole();
        $this->assertSame($desk->id, (int) $log->causer_id);
    }

    public function test_closing_without_an_open_dispute_is_folio_dispute_state(): void
    {
        [$guest, , $folio, $item] = $this->guestStay();

        $this->staffDispute($folio, $item, ['action' => 'resolve', 'note' => 'n'])
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'folio_dispute_state')
            ->assertJsonPath('context.item_uuid', $item->uuid)
            ->assertJsonPath('context.status', null);

        FolioItemDispute::factory()->resolved()->create(['folio_item_id' => $item->id, 'guest_id' => $guest->id]);
        $this->staffDispute($folio, $item, ['action' => 'reject', 'note' => 'n'])
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'folio_dispute_state')
            ->assertJsonPath('context.status', 'resolved');
    }

    public function test_staff_raise_while_open_is_refused(): void
    {
        [$guest, , $folio, $item] = $this->guestStay();
        FolioItemDispute::factory()->create(['folio_item_id' => $item->id, 'guest_id' => $guest->id]);

        $this->staffDispute($folio, $item, ['action' => 'raise', 'reason' => 'again'])
            ->assertStatus(422)->assertJsonPath('error_code', 'folio_item_dispute_open');
    }

    public function test_staff_body_validation(): void
    {
        [, , $folio, $item] = $this->guestStay();
        $cases = [
            'action' => [[], ['action' => 'close']],
            'reason' => [['action' => 'raise'], ['action' => 'raise', 'reason' => str_repeat('r', 501)]],
            'note'   => [['action' => 'resolve'], ['action' => 'reject'], ['action' => 'resolve', 'note' => str_repeat('n', 1001)]],
        ];

        foreach ($cases as $field => $bodies) {
            foreach ($bodies as $body) {
                $this->staffDispute($folio, $item, $body)
                    ->assertStatus(422)
                    ->assertJsonPath('error_code', 'validation_failed')
                    ->assertJsonValidationErrors([$field]);
            }
        }

        $this->assertSame(0, FolioItemDispute::count());
    }

    public function test_item_of_another_folio_and_unknown_ids_are_404(): void
    {
        [, , $folio] = $this->guestStay();
        [, , , $foreignItem] = $this->guestStay();

        $this->staffDispute($folio, $foreignItem, ['action' => 'raise', 'reason' => 'x'])
            ->assertStatus(404)->assertJsonPath('error_code', 'not_found');
        $this->staffDispute($folio, (string) Str::uuid(), ['action' => 'raise', 'reason' => 'x'])->assertStatus(404);
        $this->staffDispute((string) Str::uuid(), $foreignItem, ['action' => 'raise', 'reason' => 'x'])->assertStatus(404);

        $this->assertSame(0, FolioItemDispute::count());
    }

    public function test_resolution_moves_no_money_and_a_refund_is_a_separate_credit(): void
    {
        [$guest, $reservation, $folio, $item] = $this->guestStay('300.00');
        FolioItemDispute::factory()->create(['folio_item_id' => $item->id, 'guest_id' => $guest->id]);
        $before = $this->moneySnapshot($folio);

        $this->staffDispute($folio, $item, ['action' => 'resolve', 'note' => 'Refunding one night'])->assertOk();
        $this->assertEquals($before, $this->moneySnapshot($folio));

        $this->app['auth']->forgetGuards();
        $this->withToken($this->presetToken('reception'))
            ->postJson("/api/cms/folios/{$folio->uuid}/line-items", [
                'kind' => 'credit', 'description' => 'One night refund', 'unit_price_usd' => '150.00',
                'reason' => 'Dispute resolved', 'reverses_item_uuid' => $item->uuid,
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.total_usd', '150.00');

        // The disputed, credited room line is frozen through regeneration.
        $reservation->update(['total_usd' => '400.00']);
        app(GenerateFolioAction::class)->handle($reservation->fresh());
        $this->assertSame('300.00', $item->fresh()->amount_usd);
    }

    public function test_guest_sees_the_staff_decision_and_can_raise_again(): void
    {
        [$guest, , $folio, $item] = $this->guestStay();
        $this->guestDispute($item, ['reason' => 'Wrong'], $this->guestToken($guest))->assertOk();
        $this->staffDispute($folio, $item, ['action' => 'reject', 'note' => 'Checked with housekeeping'])->assertOk();

        $this->guestRead($guest)
            ->assertOk()
            ->assertJsonPath('data.items.0.dispute.status', 'rejected')
            ->assertJsonPath('data.items.0.dispute.resolution_note', 'Checked with housekeeping');

        $this->guestDispute($item, ['reason' => 'I still disagree'], $this->guestToken($guest))
            ->assertOk()->assertJsonPath('data.dispute.status', 'open');
        $this->assertSame(2, FolioItemDispute::count());
    }

    public function test_staff_gates(): void
    {
        [, , $folio, $item] = $this->guestStay();
        $body = ['action' => 'raise', 'reason' => 'x'];

        $this->app['auth']->forgetGuards();
        $this->patchJson("/api/cms/folios/{$folio->uuid}/line-items/{$item->uuid}/dispute", $body)
            ->assertStatus(401)->assertJsonPath('error_code', 'unauthorized');

        foreach (['kitchen', 'housekeeping', 'concierge', 'events'] as $preset) {
            $this->staffDispute($folio, $item, $body, $this->presetToken($preset))
                ->assertStatus(403)->assertJsonPath('error_code', 'forbidden');
        }
        $this->staffDispute($folio, $item, $body, $this->staffToken('folios.view', 'folios.settle', 'folios.post'))->assertStatus(403);
        $this->assertSame(0, FolioItemDispute::count());

        $this->staffDispute($folio, $item, $body, $this->staffToken('folios.dispute'))->assertOk();
        $this->staffDispute($folio, $item, ['action' => 'resolve', 'note' => 'ok'], $this->presetToken('reception'))->assertOk();
    }

    public function test_staff_raise_and_resolve_lock_the_folio_row(): void
    {
        [, , $folio, $item] = $this->guestStay();
        $token = $this->presetToken('reception');

        $this->assertLocksRow('folios', fn () => $this->staffDispute($folio, $item, ['action' => 'raise', 'reason' => 'x'], $token)->assertOk());
        $this->assertLocksRow('folios', fn () => $this->staffDispute($folio, $item, ['action' => 'resolve', 'note' => 'y'], $token)->assertOk());
    }

    // ── Flags, not a gate (05-09) ───────────────────────────────────────

    public function test_open_disputes_count_lifecycle(): void
    {
        [$guest, $reservation, $folio, $item] = $this->guestStay();

        $this->staffRead($reservation)->assertJsonPath('data.open_disputes_count', 0);
        $this->guestRead($guest)->assertJsonPath('data.open_disputes_count', 0);

        $this->guestDispute($item, ['reason' => 'x'], $this->guestToken($guest))->assertOk();
        $this->staffRead($reservation)->assertJsonPath('data.open_disputes_count', 1);
        $this->guestRead($guest)->assertJsonPath('data.open_disputes_count', 1);

        $this->staffDispute($folio, $item, ['action' => 'resolve', 'note' => 'done'])->assertOk();
        $this->staffRead($reservation)->assertJsonPath('data.open_disputes_count', 0);
        $this->guestRead($guest)->assertJsonPath('data.open_disputes_count', 0);
    }

    /** A checked-in stay leaving today, with a folio in $folioStatus. */
    private function departingStay(string $folioStatus): array
    {
        config(['hotel.timezone' => 'Asia/Damascus']);
        $this->travelTo(Carbon::parse('2027-03-12 10:00:00'));
        $type        = RoomType::factory()->create();
        $room        = Room::factory()->create(['room_type_id' => $type->id, 'status' => 'available']);
        $reservation = Reservation::factory()->checkedIn()->create([
            'check_in' => '2027-03-10', 'check_out' => '2027-03-12', 'checked_in_at' => '2027-03-10 12:00:00', 'total_usd' => '300.00',
        ]);
        ReservationRoom::factory()->create(['reservation_id' => $reservation->id, 'room_type_id' => $type->id, 'room_id' => $room->id, 'price_usd' => 300]);
        $folio = app(GenerateFolioAction::class)->handle($reservation)['data'];
        if ($folioStatus === 'settled') {
            $folio->update(['status' => FolioStatus::SETTLED, 'settled_at' => now()]);
        }
        $item = $folio->items->first();
        FolioItemDispute::factory()->create(['folio_item_id' => $item->id, 'guest_id' => $reservation->guest_id]);

        return [$reservation, $folio, $item];
    }

    private function checkOut(Reservation $reservation): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($this->presetToken('reception'))
            ->postJson("/api/cms/reservations/{$reservation->uuid}/check-out", [], ['Accept-Language' => 'en']);
    }

    public function test_open_dispute_on_a_settled_folio_does_not_block_check_out(): void
    {
        [$reservation] = $this->departingStay('settled');

        $this->checkOut($reservation)
            ->assertOk()
            ->assertJsonPath('data.status', 'checked_out')
            ->assertJsonPath('data.folio.status', 'settled')
            ->assertJsonPath('data.folio.open_disputes_count', 1);

        $this->assertSame(1, FolioItemDispute::open()->count());
    }

    public function test_open_folio_with_an_open_dispute_is_refused_only_as_unsettled(): void
    {
        [$reservation, $folio] = $this->departingStay('open');

        $this->checkOut($reservation)
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'folio_unsettled');

        $this->assertSame(1, FolioItemDispute::open()->count());
        $this->assertSame(FolioStatus::OPEN, $folio->fresh()->status);
    }

    private function index(string $query): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($this->staffToken('reservations.view'))->getJson('/api/cms/reservations?'.$query);
    }

    private function uuids(TestResponse $response): array
    {
        $uuids = collect($response->json('data.items'))->pluck('uuid')->all();
        sort($uuids);
        return $uuids;
    }

    public function test_has_open_disputes_filter(): void
    {
        [, $openDisputed, $openFolio, $openItem] = $this->guestStay();
        FolioItemDispute::factory()->create(['folio_item_id' => $openItem->id, 'guest_id' => $openDisputed->guest_id]);

        [, $settledDisputed, $settledFolio, $settledItem] = $this->guestStay();
        $settledFolio->update(['status' => FolioStatus::SETTLED, 'settled_at' => now()]);
        FolioItemDispute::factory()->create(['folio_item_id' => $settledItem->id, 'guest_id' => $settledDisputed->guest_id]);

        [, $resolvedOnly, , $resolvedItem] = $this->guestStay();
        FolioItemDispute::factory()->resolved()->create(['folio_item_id' => $resolvedItem->id, 'guest_id' => $resolvedOnly->guest_id]);

        $noFolio = Reservation::factory()->confirmed()->create();

        $sorted = function (Reservation ...$reservations) {
            $u = array_map(fn ($r) => $r->uuid, $reservations);
            sort($u);
            return $u;
        };

        $this->assertSame($sorted($openDisputed, $settledDisputed), $this->uuids($this->index('has_open_disputes=1')->assertOk()));
        $this->assertSame($sorted($resolvedOnly, $noFolio), $this->uuids($this->index('has_open_disputes=0')->assertOk()));
        $this->assertCount(4, $this->index('has_open_disputes=')->assertOk()->json('data.items'));
        $this->assertSame($sorted($settledDisputed), $this->uuids($this->index('has_open_disputes=1&folio_status=settled')->assertOk()));
        $this->assertSame($sorted($resolvedOnly), $this->uuids($this->index('has_open_disputes=0&folio_status=open')->assertOk()));

        foreach (['yes', '2', 'true'] as $bad) {
            $this->index('has_open_disputes='.$bad)
                ->assertStatus(422)
                ->assertJsonPath('error_code', 'validation_failed')
                ->assertJsonValidationErrors(['has_open_disputes']);
        }
        $this->index('has_open_disputes[]=1')->assertStatus(422);
    }

    public function test_phase_9_read_hooks(): void
    {
        [$guest, , $disputedFolio, $item] = $this->guestStay();
        [, , $cleanFolio] = $this->guestStay();
        [, , $settledFolio] = $this->guestStay();
        $settledFolio->update(['status' => FolioStatus::SETTLED, 'settled_at' => now()]);
        $second = FolioItem::factory()->manual()->create(['folio_id' => $disputedFolio->id]);
        FolioItemDispute::factory()->create(['folio_item_id' => $item->id, 'guest_id' => $guest->id]);
        FolioItemDispute::factory()->byStaff()->create(['folio_item_id' => $second->id]);
        FolioItemDispute::factory()->rejected()->create(['folio_item_id' => $second->id, 'guest_id' => $guest->id]);

        $this->assertEqualsCanonicalizing([$disputedFolio->id, $cleanFolio->id], Folio::unsettled()->pluck('id')->all());
        $this->assertSame([$disputedFolio->id], Folio::withOpenDisputes()->pluck('id')->all());
        $this->assertSame(2, $disputedFolio->openDisputesCount());
        $this->assertSame(0, $cleanFolio->openDisputesCount());
        $this->assertSame(2, FolioItemDispute::open()->count());
        $this->assertSame(3, $disputedFolio->disputes()->count());
        $this->assertSame(2, $disputedFolio->openDisputes()->count());
    }
}
