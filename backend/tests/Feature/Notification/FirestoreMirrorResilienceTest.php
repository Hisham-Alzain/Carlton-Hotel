<?php

namespace Tests\Feature\Notification;

use App\Contracts\FirebaseServiceInterface;
use App\Models\Guest;
use App\Models\ServiceRequest;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakeFirebaseService;
use Tests\TestCase;

/**
 * @group p10
 */
class FirestoreMirrorResilienceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function throwingFirebase(): void
    {
        $fake = new FakeFirebaseService();
        $fake->throwOnMirror = true;
        $this->app->instance(FirebaseServiceInterface::class, $fake);
    }

    public function test_a_failed_firestore_mirror_does_not_fail_the_chat_send(): void
    {
        $this->throwingFirebase();
        $guest = Guest::factory()->create();

        $this->actingAs($guest, 'guests')
            ->postJson('/api/conversations', ['body' => 'Hello'])
            ->assertCreated();

        $this->assertDatabaseHas('messages', ['body' => 'Hello']);
    }

    public function test_a_failed_firestore_mirror_does_not_fail_assigning_a_request(): void
    {
        $this->throwingFirebase();
        $request = ServiceRequest::factory()->create();
        $assignee = User::factory()->create();
        $staff = User::factory()->create();
        $staff->givePermissionTo('service_requests.assign');

        $this->withToken($staff->createToken('t')->plainTextToken)
            ->patchJson("/api/operations/queue/service-requests/{$request->uuid}/assign", ['user_uuid' => $assignee->uuid])
            ->assertOk();

        $this->assertEquals($assignee->id, $request->fresh()->assigned_user_id);
    }
}
