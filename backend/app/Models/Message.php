<?php

namespace App\Models;

use App\Enums\MessageSender;
use App\Traits\FileTrait;
use App\Traits\HasUuid;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Message extends Model
{
    use FileTrait, HasFactory, HasUuid, LogsActivity;

    protected $fillable = ['conversation_id', 'sender_type', 'sender_id', 'body', 'attachment_path', 'read_at'];

    protected function casts(): array
    {
        return ['read_at' => 'datetime'];
    }

    public function conversation(): BelongsTo { return $this->belongsTo(Conversation::class); }
    public function sender(): MorphTo          { return $this->morphTo(); }

    public function getAttachmentUrlAttribute(): ?string
    {
        return $this->fileUrl($this->attachment_path);
    }

    // sender_type stores the FQCN (Guest/User are not in the global morph map
    // — see AppServiceProvider). This maps it back to the short API label.
    public function senderLabel(): MessageSender
    {
        return $this->sender_type === Guest::class ? MessageSender::GUEST : MessageSender::STAFF;
    }
}
