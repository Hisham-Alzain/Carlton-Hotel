<?php

namespace Tests\Feature\Notification;

use App\Contracts\FirebaseServiceInterface;
use App\Models\DeviceToken;
use App\Models\Guest;
use App\Models\GuestNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakeFirebaseService;
use Tests\TestCase;

/**
 * @group p9
 */
class DeviceTokenTest extends TestCase
{
    use RefreshDatabase;

    private function fakeFirebase(): FakeFirebaseService
    {
        $fake = new FakeFirebaseService();
        $this->app->instance(FirebaseServiceInterface::class, $fake);
        return $fake;
    }

    public function test_guest_can_register_a_device_token(): void
    {
        $this->fakeFirebase();
        $guest = Guest::factory()->create();

        $this->actingAs($guest, 'guests')
            ->postJson('/api/device-tokens', ['token' => 'tok-1', 'platform' => 'android'])
            ->assertCreated()
            ->assertJsonPath('data.platform', 'android');

        $this->assertDatabaseHas('device_tokens', ['token' => 'tok-1', 'guest_id' => $guest->id]);
    }

    public function test_registering_the_same_token_again_reassigns_it_instead_of_duplicating(): void
    {
        $this->fakeFirebase();
        $guestA = Guest::factory()->create();
        $guestB = Guest::factory()->create();

        DeviceToken::factory()->create(['guest_id' => $guestA->id, 'token' => 'shared-tok']);

        $this->actingAs($guestB, 'guests')
            ->postJson('/api/device-tokens', ['token' => 'shared-tok', 'platform' => 'ios'])
            ->assertCreated();

        $this->assertEquals(1, DeviceToken::where('token', 'shared-tok')->count());
        $this->assertEquals($guestB->id, DeviceToken::where('token', 'shared-tok')->first()->guest_id);
    }

    public function test_inheriting_a_previously_owned_token_still_fires_welcome_for_the_new_owner(): void
    {
        $fake = $this->fakeFirebase();
        $guestA = Guest::factory()->create();
        $guestB = Guest::factory()->create();

        DeviceToken::factory()->create(['guest_id' => $guestA->id, 'token' => 'shared-tok']);

        // shared-tok already exists globally (owned by guestA), but this is
        // guestB's own first-ever device — welcome must still fire for guestB.
        $this->actingAs($guestB, 'guests')
            ->postJson('/api/device-tokens', ['token' => 'shared-tok', 'platform' => 'ios'])
            ->assertCreated();

        $this->assertDatabaseHas('guest_notifications', ['guest_id' => $guestB->id, 'type' => GuestNotification::TYPE_WELCOME]);
        $this->assertCount(1, $fake->pushes);
    }

    public function test_first_ever_device_token_fires_welcome_notification(): void
    {
        $fake = $this->fakeFirebase();
        $guest = Guest::factory()->create();

        $this->actingAs($guest, 'guests')
            ->postJson('/api/device-tokens', ['token' => 'first-tok', 'platform' => 'android'])
            ->assertCreated();

        $this->assertDatabaseHas('guest_notifications', ['guest_id' => $guest->id, 'type' => GuestNotification::TYPE_WELCOME]);
        $this->assertCount(1, $fake->pushes);
        $this->assertEquals(['first-tok'], $fake->pushes[0]['tokens']);
    }

    public function test_second_device_token_for_same_guest_does_not_refire_welcome(): void
    {
        $fake = $this->fakeFirebase();
        $guest = Guest::factory()->create();

        $this->actingAs($guest, 'guests')->postJson('/api/device-tokens', ['token' => 'tok-a', 'platform' => 'android'])->assertCreated();
        $this->actingAs($guest, 'guests')->postJson('/api/device-tokens', ['token' => 'tok-b', 'platform' => 'ios'])->assertCreated();

        $this->assertEquals(1, GuestNotification::where('guest_id', $guest->id)->where('type', GuestNotification::TYPE_WELCOME)->count());
        $this->assertCount(1, $fake->pushes);
    }

    public function test_validation_requires_platform_in_allowed_list(): void
    {
        $this->fakeFirebase();
        $guest = Guest::factory()->create();

        $this->actingAs($guest, 'guests')
            ->postJson('/api/device-tokens', ['token' => 'tok-x', 'platform' => 'desktop'])
            ->assertStatus(422);
    }

    public function test_unauthenticated_guest_cannot_register_token(): void
    {
        $this->fakeFirebase();
        $this->postJson('/api/device-tokens', ['token' => 'tok-x', 'platform' => 'android'])
            ->assertStatus(401);
    }
}
