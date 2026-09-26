<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An internal staff note on a guest (Phase 4, D-06).
 *
 * Deliberately NOT activity-logged: notes are PII-adjacent free text, and
 * copying them into activity_log would duplicate that text into an audit table
 * with its own retention. Notes are append-only; there is no edit or delete path.
 */
class GuestNote extends Model
{
    use HasFactory, HasUuid;

    protected $fillable = ['guest_id', 'user_id', 'body'];

    public function guest(): BelongsTo
    {
        return $this->belongsTo(Guest::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
