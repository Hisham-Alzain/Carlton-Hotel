<?php

namespace App\Models;

use App\Traits\HasUuid;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Conversation extends Model
{
    use HasFactory, HasUuid, LogsActivity;

    const STATUS_OPEN   = 'open';
    const STATUS_CLOSED = 'closed';

    protected $fillable = ['guest_id', 'assigned_user_id', 'status', 'last_message_at'];

    protected function casts(): array
    {
        return ['last_message_at' => 'datetime'];
    }

    public function guest(): BelongsTo         { return $this->belongsTo(Guest::class); }
    public function assignedUser(): BelongsTo  { return $this->belongsTo(User::class, 'assigned_user_id'); }
    public function messages(): HasMany        { return $this->hasMany(Message::class); }
}
