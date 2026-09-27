<?php

namespace Tests\Unit\Support;

use App\Exceptions\IdempotencyConflictException;
use App\Models\Folio;
use App\Models\FolioItem;
use App\Support\IdempotentWrite;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Phase 5 (D-08): replay-safe writes keyed by an Idempotency-Key, with the
 * unique index as the race backstop (a real SQLite unique violation here).
 */
class IdempotentWriteTest extends TestCase
{
    use RefreshDatabase;

    private function writeItem(Folio $folio, ?string $key, string $description = 'Minibar'): FolioItem
    {
        return FolioItem::factory()->manual()->create([
            'folio_id' => $folio->id, 'idempotency_key' => $key, 'description' => $description,
        ]);
    }

    public function test_null_key_always_writes(): void
    {
        $folio = Folio::factory()->create();

        [$a, $replayedA] = IdempotentWrite::run(null, fn () => $this->fail('no lookup without a key'), fn () => true, fn () => $this->writeItem($folio, null));
        [$b, $replayedB] = IdempotentWrite::run(null, fn () => $this->fail('no lookup without a key'), fn () => true, fn () => $this->writeItem($folio, null));

        $this->assertFalse($replayedA);
        $this->assertFalse($replayedB);
        $this->assertNotSame($a->id, $b->id);
        $this->assertSame(2, FolioItem::count());
    }

    public function test_first_use_of_a_key_writes(): void
    {
        $folio = Folio::factory()->create();

        [$row, $replayed] = IdempotentWrite::run('K-1', fn () => null, fn () => true, fn () => $this->writeItem($folio, 'K-1'));

        $this->assertFalse($replayed);
        $this->assertSame('K-1', $row->idempotency_key);
    }

    public function test_matching_key_replays_without_writing(): void
    {
        $folio  = Folio::factory()->create();
        $stored = $this->writeItem($folio, 'K-1');

        [$row, $replayed] = IdempotentWrite::run(
            'K-1',
            fn () => FolioItem::where('idempotency_key', 'K-1')->first(),
            fn (FolioItem $row) => $row->description === 'Minibar',
            fn () => $this->fail('a replay never writes'),
        );

        $this->assertTrue($replayed);
        $this->assertSame($stored->id, $row->id);
        $this->assertSame(1, FolioItem::count());
    }

    public function test_mismatched_payload_on_a_used_key_throws_conflict(): void
    {
        $folio = Folio::factory()->create();
        $this->writeItem($folio, 'K-1');

        try {
            IdempotentWrite::run(
                'K-1',
                fn () => FolioItem::where('idempotency_key', 'K-1')->first(),
                fn () => false,
                fn () => $this->fail('a conflict never writes'),
            );
            $this->fail('expected IdempotencyConflictException');
        } catch (IdempotencyConflictException $e) {
            $this->assertSame('idempotency_conflict', $e->errorCode());
            $this->assertSame(409, $e->statusCode());
            $this->assertSame(['idempotency_key' => 'K-1'], $e->context());
        }

        $this->assertSame(1, FolioItem::count());
    }

    public function test_real_unique_violation_is_reread_as_a_replay_and_the_outer_transaction_survives(): void
    {
        $folio  = Folio::factory()->create();
        $stored = $this->writeItem($folio, 'K-race');
        $calls  = 0;

        // The first lookup misses (the concurrent writer has not committed yet
        // from this request's point of view); the insert then hits the unique index.
        $find = function () use (&$calls) {
            return ++$calls === 1 ? null : FolioItem::where('idempotency_key', 'K-race')->first();
        };

        DB::transaction(function () use ($folio, $find, $stored) {
            [$row, $replayed] = IdempotentWrite::run('K-race', $find, fn () => true, fn () => $this->writeItem($folio, 'K-race'));

            $this->assertTrue($replayed);
            $this->assertSame($stored->id, $row->id);
            // The savepoint rolled back only the failed insert.
            $this->assertSame(1, FolioItem::where('folio_id', $folio->id)->count());
        });

        $this->assertSame(2, $calls);
        $this->assertSame(1, FolioItem::count());
    }

    public function test_real_unique_violation_with_a_mismatched_payload_is_a_conflict(): void
    {
        $folio = Folio::factory()->create();
        $this->writeItem($folio, 'K-race');
        $calls = 0;

        $this->expectException(IdempotencyConflictException::class);

        IdempotentWrite::run(
            'K-race',
            function () use (&$calls) {
                return ++$calls === 1 ? null : FolioItem::where('idempotency_key', 'K-race')->first();
            },
            fn () => false,
            fn () => $this->writeItem($folio, 'K-race'),
        );
    }

    public function test_unique_violation_on_another_index_is_rethrown(): void
    {
        $folio = Folio::factory()->create();
        FolioItem::factory()->create(['folio_id' => $folio->id, 'source_type' => 'reservation', 'source_id' => 7, 'source_line' => 0]);

        $this->expectException(UniqueConstraintViolationException::class);

        IdempotentWrite::run('K-new', fn () => null, fn () => true, fn () => FolioItem::factory()->create([
            'folio_id' => $folio->id, 'source_type' => 'reservation', 'source_id' => 7, 'source_line' => 0, 'idempotency_key' => 'K-new',
        ]));
    }
}
