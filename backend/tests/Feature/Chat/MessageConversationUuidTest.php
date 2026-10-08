<?php

namespace Tests\Feature\Chat;

use App\Contracts\FirebaseServiceInterface;
use App\Models\Conversation;
use App\Models\Guest;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\FakeFirebaseService;
use Tests\TestCase;

/**
 * Every message payload names its conversation (IT6-04).
 *
 * @group p9
 */
class MessageConversationUuidTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->app->instance(FirebaseServiceInterface::class, new FakeFirebaseService);
    }

    private function staffToken(string ...$permissions): string
    {
        $user = User::factory()->create();
        $user->givePermissionTo($permissions);

        return $user->createToken('t')->plainTextToken;
    }

    private function send(Guest $guest, string $body): string
    {
        return $this->actingAs($guest, 'guests')
            ->postJson('/api/conversations', ['body' => $body])
            ->assertCreated()
            ->json('data.conversation_uuid');
    }

    public function test_guest_post_returns_the_conversation_uuid_including_on_reuse(): void
    {
        $guest = Guest::factory()->create();

        $first = $this->send($guest, 'First');
        $conversation = Conversation::where('guest_id', $guest->id)->firstOrFail();

        $this->assertSame($conversation->uuid, $first);
        $this->assertSame($conversation->uuid, $this->send($guest, 'Second'));
    }

    public function test_guest_history_items_carry_the_conversation_uuid(): void
    {
        $guest = Guest::factory()->create();
        $this->send($guest, 'First');
        $this->send($guest, 'Second');
        $conversation = Conversation::where('guest_id', $guest->id)->firstOrFail();

        $items = $this->actingAs($guest, 'guests')
            ->getJson("/api/conversations/{$conversation->uuid}/messages")
            ->assertOk()
            ->assertJsonCount(2, 'data.items')
            ->json('data.items');

        foreach ($items as $item) {
            $this->assertSame($conversation->uuid, $item['conversation_uuid']);
        }
    }

    public function test_staff_post_and_history_carry_the_conversation_uuid(): void
    {
        $guest = Guest::factory()->create();
        $this->send($guest, 'Need help');
        $conversation = Conversation::where('guest_id', $guest->id)->firstOrFail();

        $this->withToken($this->staffToken('tickets.respond'))
            ->postJson("/api/cms/conversations/{$conversation->uuid}/messages", ['body' => 'On it'])
            ->assertCreated()
            ->assertJsonPath('data.conversation_uuid', $conversation->uuid);

        $items = $this->withToken($this->staffToken('tickets.view'))
            ->getJson("/api/cms/conversations/{$conversation->uuid}/messages")
            ->assertOk()
            ->assertJsonCount(2, 'data.items')
            ->json('data.items');

        foreach ($items as $item) {
            $this->assertSame($conversation->uuid, $item['conversation_uuid']);
        }
    }

    public function test_history_query_count_does_not_grow_with_message_count(): void
    {
        $guest = Guest::factory()->create();
        $this->send($guest, 'One');
        $conversation = Conversation::where('guest_id', $guest->id)->firstOrFail();

        $count = function () use ($guest, $conversation): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->actingAs($guest, 'guests')
                ->getJson("/api/conversations/{$conversation->uuid}/messages")
                ->assertOk();
            $queries = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $queries;
        };

        $withOne = $count();
        $this->send($guest, 'Two');
        $this->send($guest, 'Three');
        $withThree = $count();

        $this->assertSame($withOne, $withThree);
    }

    public function test_other_guests_conversation_is_404_and_no_token_is_401(): void
    {
        $owner = Guest::factory()->create();
        $this->send($owner, 'Private');
        $conversation = Conversation::where('guest_id', $owner->id)->firstOrFail();

        $this->actingAs(Guest::factory()->create(), 'guests')
            ->getJson("/api/conversations/{$conversation->uuid}/messages")
            ->assertStatus(404);

        $this->app['auth']->forgetGuards();
        $this->getJson("/api/conversations/{$conversation->uuid}/messages")->assertStatus(401);
    }
}
