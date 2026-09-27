<?php

namespace Tests\Unit\Folio;

use App\Actions\Folio\PostFolioItemAction;
use App\Actions\Folio\RaiseFolioDisputeAction;
use App\Actions\Folio\RecordFolioPaymentAction;
use App\Actions\Folio\ResolveFolioDisputeAction;
use App\Enums\FolioDisputeStatus;
use App\Enums\FolioStatus;
use App\Exceptions\FolioCreditExceedsBalanceException;
use App\Exceptions\FolioCreditExceedsItemException;
use App\Exceptions\FolioDisputeStateException;
use App\Exceptions\FolioItemDisputeOpenException;
use App\Exceptions\FolioOverpaymentException;
use App\Exceptions\FolioSettledException;
use App\Exceptions\IdempotencyConflictException;
use App\Models\Folio;
use App\Models\FolioItem;
use App\Models\FolioItemDispute;
use App\Models\Guest;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Action-level coverage (test-discipline): the four Phase 5 folio writers
 * return ['data' => ..., 'code' => ...] and fail with domain exceptions that
 * carry their machine-readable context, without the HTTP stack.
 */
class FolioWriterActionsTest extends TestCase
{
    use RefreshDatabase;

    /** An open folio with one generated charge of $amount, totals recalculated. */
    private function folioWithCharge(string $amount = '100.00'): array
    {
        $folio = Folio::factory()->create();
        $item  = FolioItem::factory()->create([
            'folio_id'    => $folio->id,
            'amount_usd'  => $amount,
            'source_type' => 'reservation',
            'source_id'   => $folio->reservation_id,
        ]);
        $folio->recalculateTotals();

        return [$folio->fresh(), $item];
    }

    private function postItem(Folio $folio, array $data, ?User $poster = null): array
    {
        return app(PostFolioItemAction::class)->handle($folio, $poster ?? User::factory()->create(), $data);
    }

    private function pay(Folio $folio, string $amount, string $key, ?User $recorder = null): array
    {
        return app(RecordFolioPaymentAction::class)
            ->handle($folio, $recorder ?? User::factory()->create(), ['method' => 'cash', 'amount_usd' => $amount], $key);
    }

    private function expectDomain(string $class, callable $run): \App\Exceptions\DomainException
    {
        try {
            $run();
        } catch (\App\Exceptions\DomainException $e) {
            $this->assertInstanceOf($class, $e);

            return $e;
        }

        $this->fail("Expected {$class}");
    }

    // ── PostFolioItemAction ────────────────────────────────────────────

    public function test_post_returns_the_folio_with_201_then_200_on_replay(): void
    {
        [$folio] = $this->folioWithCharge('100.00');
        $poster  = User::factory()->create();
        $data    = ['kind' => 'charge', 'description' => 'Minibar', 'quantity' => 3, 'unit_price_usd' => '2.35', 'idempotency_key' => 'K'];

        $first = $this->postItem($folio, $data, $poster);
        $this->assertSame(201, $first['code']);
        $this->assertInstanceOf(Folio::class, $first['data']);
        $this->assertSame('107.05', $first['data']->total_usd);

        // Another poster replaying the same key is a replay, not a conflict (FA-5.03-2).
        $replay = $this->postItem($folio, $data);
        $this->assertSame(200, $replay['code']);
        $this->assertSame('107.05', $replay['data']->total_usd);
        $this->assertSame(1, FolioItem::where('source_type', 'manual')->count());

        $item = FolioItem::where('source_type', 'manual')->sole();
        $this->assertSame('7.05', $item->amount_usd);
        $this->assertSame('2.35', $item->unit_price_usd);
        $this->assertSame($poster->id, $item->posted_by);
    }

    public function test_post_conflict_carries_the_key(): void
    {
        [$folio] = $this->folioWithCharge();
        $this->postItem($folio, ['description' => 'A', 'unit_price_usd' => '1.00', 'idempotency_key' => 'K']);

        $e = $this->expectDomain(IdempotencyConflictException::class, fn () => $this->postItem($folio, ['description' => 'B', 'unit_price_usd' => '1.00', 'idempotency_key' => 'K']));

        $this->assertSame(['idempotency_key' => 'K'], $e->context());
        $this->assertSame(409, $e->statusCode());
    }

    public function test_post_on_a_settled_folio_throws_with_context_and_writes_nothing(): void
    {
        [$folio] = $this->folioWithCharge();
        $folio->update(['status' => FolioStatus::SETTLED, 'settled_at' => now()]);

        $e = $this->expectDomain(FolioSettledException::class, fn () => $this->postItem($folio, ['description' => 'A', 'unit_price_usd' => '1.00']));

        $this->assertSame($folio->uuid, $e->context()['folio_uuid']);
        $this->assertNotNull($e->context()['settled_at']);
        $this->assertSame(1, FolioItem::count());
    }

    public function test_credit_floors_throw_with_context(): void
    {
        [$folio, $charge] = $this->folioWithCharge('50.00');

        $item = $this->expectDomain(FolioCreditExceedsItemException::class, fn () => $this->postItem($folio, [
            'kind' => 'credit', 'description' => 'Refund', 'unit_price_usd' => '50.01', 'reason' => 'x', 'reverses_item_uuid' => $charge->uuid,
        ]));
        $this->assertSame(['item_uuid' => $charge->uuid, 'remaining_usd' => '50.00', 'amount_usd' => '50.01'], $item->context());

        Payment::factory()->create(['payable_type' => Folio::class, 'payable_id' => $folio->id, 'amount_usd' => '45.00', 'status' => 'completed']);

        $balance = $this->expectDomain(FolioCreditExceedsBalanceException::class, fn () => $this->postItem($folio, [
            'kind' => 'credit', 'description' => 'Goodwill', 'unit_price_usd' => '5.01', 'reason' => 'x',
        ]));
        $this->assertSame(['balance_due_usd' => '5.00', 'amount_usd' => '5.01'], $balance->context());

        $ok = $this->postItem($folio, ['kind' => 'credit', 'description' => 'Goodwill', 'unit_price_usd' => '5.00', 'reason' => 'x']);
        $this->assertSame(201, $ok['code']);
        $this->assertSame('45.00', $ok['data']->total_usd);
        $this->assertSame('0.00', $ok['data']->balanceDueUsd());
    }

    // ── RecordFolioPaymentAction ───────────────────────────────────────

    public function test_payment_returns_201_auto_settles_and_replays_200(): void
    {
        [$folio] = $this->folioWithCharge('20.00');
        $desk    = User::factory()->create();

        $partial = $this->pay($folio, '19.99', 'A', $desk);
        $this->assertSame(201, $partial['code']);
        $this->assertSame(FolioStatus::OPEN, $partial['data']->status);
        $this->assertSame('0.01', $partial['data']->balanceDueUsd());

        $final = $this->pay($folio, '0.01', 'B', $desk);
        $this->assertSame(201, $final['code']);
        $this->assertSame(FolioStatus::SETTLED, $final['data']->status);

        // Replay after auto-settle answers 200 before the settled guard (D-08).
        $replay = $this->pay($folio, '0.01', 'B', $desk);
        $this->assertSame(200, $replay['code']);
        $this->assertSame(2, Payment::where('payable_type', Folio::class)->count());
    }

    public function test_overpayment_and_settled_throw_with_context(): void
    {
        [$folio] = $this->folioWithCharge('20.00');

        $e = $this->expectDomain(FolioOverpaymentException::class, fn () => $this->pay($folio, '20.01', 'A'));
        $this->assertSame(['balance_due_usd' => '20.00', 'amount_usd' => '20.01'], $e->context());
        $this->assertSame(0, Payment::count());

        $folio->update(['status' => FolioStatus::SETTLED, 'settled_at' => now()]);
        $this->expectDomain(FolioSettledException::class, fn () => $this->pay($folio, '1.00', 'B'));
        $this->assertSame(0, Payment::count());
    }

    // ── RaiseFolioDisputeAction / ResolveFolioDisputeAction ─────────────

    public function test_raise_sets_exactly_one_raiser_and_refuses_a_second_open_dispute(): void
    {
        [, $item] = $this->folioWithCharge();
        $guest    = Guest::factory()->create();

        $result = app(RaiseFolioDisputeAction::class)->handle($item, $guest, 'Charged twice');
        $this->assertSame(200, $result['code']);
        $this->assertTrue($result['data']->is($item));

        $dispute = FolioItemDispute::sole();
        $this->assertSame(FolioDisputeStatus::OPEN, $dispute->status);
        $this->assertSame($guest->id, $dispute->guest_id);
        $this->assertNull($dispute->user_id);

        $e = $this->expectDomain(FolioItemDisputeOpenException::class, fn () => app(RaiseFolioDisputeAction::class)->handle($item, User::factory()->create(), 'again'));
        $this->assertSame(['item_uuid' => $item->uuid, 'dispute_uuid' => $dispute->uuid], $e->context());
        $this->assertSame(1, FolioItemDispute::count());
    }

    public function test_resolve_stamps_the_resolver_and_refuses_without_an_open_dispute(): void
    {
        [$folio, $item] = $this->folioWithCharge('40.00');
        $staff = User::factory()->create();

        $none = $this->expectDomain(FolioDisputeStateException::class, fn () => app(ResolveFolioDisputeAction::class)->handle($item, $staff, FolioDisputeStatus::RESOLVED, 'n'));
        $this->assertSame(['item_uuid' => $item->uuid, 'status' => null], $none->context());

        app(RaiseFolioDisputeAction::class)->handle($item, $staff, 'wrong price');
        $result = app(ResolveFolioDisputeAction::class)->handle($item, $staff, FolioDisputeStatus::REJECTED, 'price confirmed');

        $this->assertSame(200, $result['code']);
        $dispute = FolioItemDispute::sole();
        $this->assertSame(FolioDisputeStatus::REJECTED, $dispute->status);
        $this->assertSame($staff->id, $dispute->resolved_by);
        $this->assertNotNull($dispute->resolved_at);
        $this->assertSame('price confirmed', $dispute->resolution_note);

        // Never moves money (D-11).
        $this->assertSame('40.00', $folio->fresh()->total_usd);
        $this->assertSame(1, FolioItem::count());

        $closed = $this->expectDomain(FolioDisputeStateException::class, fn () => app(ResolveFolioDisputeAction::class)->handle($item, $staff, FolioDisputeStatus::RESOLVED, 'n'));
        $this->assertSame('rejected', $closed->context()['status']);
    }

    public function test_resolve_refuses_open_as_an_outcome(): void
    {
        [, $item] = $this->folioWithCharge();

        $this->expectException(\InvalidArgumentException::class);
        app(ResolveFolioDisputeAction::class)->handle($item, User::factory()->create(), FolioDisputeStatus::OPEN, 'n');
    }
}
