<?php

namespace Tests\Feature\Chat;

use App\Contracts\FirebaseServiceInterface;
use App\Models\Conversation;
use App\Models\Guest;
use App\Models\Message;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakeFirebaseService;
use Tests\TestCase;

/**
 * @group p9
 */
class ChatTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function fakeFirebase(): FakeFirebaseService
    {
        $fake = new FakeFirebaseService();
        $this->app->instance(FirebaseServiceInterface::class, $fake);
        return $fake;
    }

    private function staffToken(string ...$permissions): string
    {
        $user = User::factory()->create();
        $user->givePermissionTo($permissions);
        return $user->createToken('t')->plainTextToken;
    }

    public function test_guest_with_no_reservation_can_start_a_chat(): void
    {
        $this->fakeFirebase();
        $guest = Guest::factory()->create();

        $this->actingAs($guest, 'guests')
            ->postJson('/api/conversations', ['body' => 'Hello, is my room ready?'])
            ->assertCreated()
            ->assertJsonPath('data.sender_type', 'guest')
            ->assertJsonPath('data.body', 'Hello, is my room ready?');

        $this->assertDatabaseCount('conversations', 1);
        $this->assertDatabaseHas('conversations', ['guest_id' => $guest->id, 'status' => Conversation::STATUS_OPEN]);
    }

    public function test_guest_sending_a_message_mirrors_to_firestore(): void
    {
        $fake = $this->fakeFirebase();
        $guest = Guest::factory()->create();

        $this->actingAs($guest, 'guests')
            ->postJson('/api/conversations', ['body' => 'Hello'])
            ->assertCreated();

        $this->assertCount(1, $fake->mirrors);
        $this->assertEquals('chats', $fake->mirrors[0]['collection']);
        $this->assertEquals('Hello', $fake->mirrors[0]['data']['body']);
    }

    public function test_second_message_from_same_guest_reuses_the_open_conversation(): void
    {
        $this->fakeFirebase();
        $guest = Guest::factory()->create();

        $this->actingAs($guest, 'guests')->postJson('/api/conversations', ['body' => 'First'])->assertCreated();
        $this->actingAs($guest, 'guests')->postJson('/api/conversations', ['body' => 'Second'])->assertCreated();

        $this->assertDatabaseCount('conversations', 1);
        $this->assertEquals(2, Message::count());
    }

    public function test_guest_can_view_own_conversation_history_in_order(): void
    {
        $this->fakeFirebase();
        $guest = Guest::factory()->create();

        $this->actingAs($guest, 'guests')->postJson('/api/conversations', ['body' => 'First'])->assertCreated();
        $conversation = Conversation::where('guest_id', $guest->id)->first();
        $this->actingAs($guest, 'guests')->postJson('/api/conversations', ['body' => 'Second'])->assertCreated();

        $this->actingAs($guest, 'guests')
            ->getJson("/api/conversations/{$conversation->uuid}/messages")
            ->assertOk()
            ->assertJsonCount(2, 'data.items')
            ->assertJsonPath('data.items.0.body', 'First')
            ->assertJsonPath('data.items.1.body', 'Second');
    }

    public function test_guest_cannot_view_another_guests_conversation(): void
    {
        $this->fakeFirebase();
        $owner = Guest::factory()->create();
        $intruder = Guest::factory()->create();

        $this->actingAs($owner, 'guests')->postJson('/api/conversations', ['body' => 'Private'])->assertCreated();
        $conversation = Conversation::where('guest_id', $owner->id)->first();

        $this->actingAs($intruder, 'guests')
            ->getJson("/api/conversations/{$conversation->uuid}/messages")
            ->assertStatus(404);
    }

    public function test_message_requires_body_or_attachment(): void
    {
        $this->fakeFirebase();
        $guest = Guest::factory()->create();

        $this->actingAs($guest, 'guests')
            ->postJson('/api/conversations', [])
            ->assertStatus(422);
    }

    public function test_unauthenticated_guest_cannot_send_a_message(): void
    {
        $this->fakeFirebase();
        $this->postJson('/api/conversations', ['body' => 'Hi'])->assertStatus(401);
    }

    public function test_staff_with_permission_can_reply_and_claims_the_conversation(): void
    {
        $this->fakeFirebase();
        $guest = Guest::factory()->create();
        $this->actingAs($guest, 'guests')->postJson('/api/conversations', ['body' => 'Need help'])->assertCreated();
        $conversation = Conversation::where('guest_id', $guest->id)->first();

        $this->withToken($this->staffToken('tickets.respond'))
            ->postJson("/api/cms/conversations/{$conversation->uuid}/messages", ['body' => 'On it!'])
            ->assertCreated()
            ->assertJsonPath('data.sender_type', 'staff');

        $this->assertEquals(2, $conversation->fresh()->messages()->count());
        $this->assertNotNull($conversation->fresh()->assigned_user_id);
    }

    public function test_staff_without_permission_cannot_reply(): void
    {
        $this->fakeFirebase();
        $guest = Guest::factory()->create();
        $this->actingAs($guest, 'guests')->postJson('/api/conversations', ['body' => 'Need help'])->assertCreated();
        $conversation = Conversation::where('guest_id', $guest->id)->first();

        $this->withToken($this->staffToken())
            ->postJson("/api/cms/conversations/{$conversation->uuid}/messages", ['body' => 'On it!'])
            ->assertStatus(403);
    }

    public function test_staff_can_list_and_view_conversation_history(): void
    {
        $this->fakeFirebase();
        $guest = Guest::factory()->create();
        $this->actingAs($guest, 'guests')->postJson('/api/conversations', ['body' => 'Need help'])->assertCreated();
        $conversation = Conversation::where('guest_id', $guest->id)->first();

        $this->withToken($this->staffToken('tickets.view'))
            ->getJson('/api/cms/conversations')
            ->assertOk()
            ->assertJsonCount(1, 'data.items');

        $this->withToken($this->staffToken('tickets.view'))
            ->getJson("/api/cms/conversations/{$conversation->uuid}/messages")
            ->assertOk()
            ->assertJsonCount(1, 'data.items');
    }
}
