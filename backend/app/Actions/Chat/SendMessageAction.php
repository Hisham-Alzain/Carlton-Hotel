<?php

namespace App\Actions\Chat;

use App\Models\Conversation;
use App\Models\Guest;
use App\Models\Message;
use App\Models\User;
use App\Traits\FileTrait;
use App\Traits\MirrorsToFirestore;
use Illuminate\Support\Facades\DB;

class SendMessageAction
{
    use FileTrait, MirrorsToFirestore;

    // Guest side: reuses the guest's open conversation, or opens a new one.
    public function handleFromGuest(Guest $guest, array $data): array
    {
        return $this->write($this->resolveOpenConversation($guest), $guest, $data);
    }

    // Locks the guest row so two concurrent sends can't each miss the existing
    // open conversation and create two — kept separate from write() so the
    // lock is held only for this fast DB-only lookup, not the Firestore call.
    private function resolveOpenConversation(Guest $guest): Conversation
    {
        return DB::transaction(function () use ($guest) {
            Guest::whereKey($guest->id)->lockForUpdate()->first();

            return Conversation::where('guest_id', $guest->id)
                ->where('status', Conversation::STATUS_OPEN)
                ->first() ?? Conversation::create(['guest_id' => $guest->id]);
        });
    }

    // Staff side: replies on a specific conversation; claims it if unassigned.
    public function handleFromStaff(User $staff, Conversation $conversation, array $data): array
    {
        if (! $conversation->assigned_user_id) {
            $conversation->update(['assigned_user_id' => $staff->id]);
        }

        return $this->write($conversation, $staff, $data);
    }

    // $data: {body?: string, attachment?: UploadedFile}
    private function write(Conversation $conversation, Guest|User $sender, array $data): array
    {
        $attachmentPath = isset($data['attachment'])
            ? $this->storeFile($data['attachment'], 'chat-attachments/' . $conversation->uuid)
            : null;

        $message = DB::transaction(function () use ($conversation, $sender, $data, $attachmentPath) {
            $message = new Message([
                'body'            => $data['body'] ?? null,
                'attachment_path' => $attachmentPath,
            ]);
            $message->conversation()->associate($conversation);
            $message->sender()->associate($sender);
            $message->save();

            $conversation->update(['last_message_at' => $message->created_at]);

            return $message;
        });

        $this->mirrorToFirestore('chats', $message->uuid, [
            'uuid'              => $message->uuid,
            'conversation_uuid' => $conversation->uuid,
            'sender_type'       => $message->sender_type,
            'body'              => $message->body,
            'attachment_path'   => $message->attachment_path,
            'created_at'        => $message->created_at->toIso8601String(),
        ]);

        return ['data' => $message, 'code' => 201];
    }
}
