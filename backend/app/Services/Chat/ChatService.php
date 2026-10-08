<?php

namespace App\Services\Chat;

use App\Actions\Chat\SendMessageAction;
use App\Exceptions\NotFoundException;
use App\Models\Conversation;
use App\Models\Guest;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

class ChatService
{
    public function __construct(private readonly SendMessageAction $action) {}

    public function sendAsGuest(Guest $guest, array $data): array
    {
        return $this->action->handleFromGuest($guest, $data);
    }

    public function sendAsStaff(User $staff, Conversation $conversation, array $data): array
    {
        return $this->action->handleFromStaff($staff, $conversation, $data);
    }

    public function myConversations(Guest $guest): array
    {
        return ['data' => Conversation::where('guest_id', $guest->id)
            ->orderByDesc('last_message_at')
            ->paginate(15), 'code' => 200];
    }

    public function myConversationHistory(Guest $guest, Conversation $conversation): array
    {
        $this->assertOwnedByGuest($guest, $conversation);

        return ['data' => $this->historyPage($conversation), 'code' => 200];
    }

    public function adminIndex(): array
    {
        return ['data' => Conversation::with(['guest', 'assignedUser'])->orderByDesc('last_message_at')->paginate(15), 'code' => 200];
    }

    public function adminHistory(Conversation $conversation): array
    {
        return ['data' => $this->historyPage($conversation), 'code' => 200];
    }

    /**
     * Pages hold messages of one already-loaded conversation, so the inverse
     * relation is set in memory: MessageResource can read conversation_uuid
     * without a lazy load per message.
     */
    private function historyPage(Conversation $conversation): LengthAwarePaginator
    {
        $page = $conversation->messages()->orderBy('created_at')->paginate(30);
        $page->getCollection()->each(fn ($message) => $message->setRelation('conversation', $conversation));

        return $page;
    }

    private function assertOwnedByGuest(Guest $guest, Conversation $conversation): void
    {
        if ($conversation->guest_id !== $guest->id) {
            throw new NotFoundException(__('custom.errors.not_found'));
        }
    }
}
