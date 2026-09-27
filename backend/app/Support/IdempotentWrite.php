<?php

namespace App\Support;

use App\Exceptions\IdempotencyConflictException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Replay-safe writes keyed by a client Idempotency-Key (D-08).
 *
 * Callers hold the folio row lock, so the lookup and the insert are
 * serialized; the unique index is the backstop and its violation is re-read as
 * a replay. No TTL. A replay returns the current state, not a byte-identical
 * copy of the first response.
 *
 * The replay lookup runs BEFORE `$write`, so every guard inside `$write`
 * (settled folio, credit floors, overpayment) is skipped for a replay: a
 * retried request that already succeeded answers 200 even if the folio has
 * since settled (D-05/D-08 ordering).
 */
final class IdempotentWrite
{
    /**
     * @param  callable(): ?Model  $find     the stored row carrying $key, or null
     * @param  callable(Model): bool  $matches  whether that row was written by an identical request
     * @param  callable(): Model  $write    the guarded write; runs only when no row carries $key
     * @return array{0: Model, 1: bool} [row, replayed]
     */
    public static function run(?string $key, callable $find, callable $matches, callable $write): array
    {
        if ($key === null) {
            return [$write(), false];
        }

        $existing = $find();

        if ($existing !== null) {
            return [self::replayOrConflict($key, $existing, $matches), true];
        }

        try {
            // A savepoint, so a violated insert leaves the outer transaction usable.
            return [DB::transaction(fn () => $write()), false];
        } catch (UniqueConstraintViolationException $e) {
            $stored = $find();

            if ($stored === null) {
                throw $e;
            }

            return [self::replayOrConflict($key, $stored, $matches), true];
        }
    }

    private static function replayOrConflict(string $key, Model $row, callable $matches): Model
    {
        if (! $matches($row)) {
            throw new IdempotencyConflictException(__('custom.errors.idempotency_conflict'), ['idempotency_key' => $key]);
        }

        return $row;
    }
}
