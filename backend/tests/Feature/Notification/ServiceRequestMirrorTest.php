<?php

namespace Tests\Feature\Notification;

use App\Contracts\FirebaseServiceInterface;
use App\Models\Guest;
use App\Models\Reservation;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakeFirebaseService;
use Tests\TestCase;

/**
 * @group p9
 */
class ServiceRequestMirrorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_placing_a_service_request_mirrors_it_to_the_firestore_ops_queue(): void
    {
        $fake = new FakeFirebaseService();
        $this->app->instance(FirebaseServiceInterface::class, $fake);

        $guest = Guest::factory()->create();
        Reservation::factory()->checkedIn()->create(['guest_id' => $guest->id]);

        $this->actingAs($guest, 'guests')
            ->postJson('/api/service-requests', ['type' => 'room_service'])
            ->assertCreated();

        $this->assertCount(1, $fake->mirrors);
        $this->assertEquals('ops_queue', $fake->mirrors[0]['collection']);
        $this->assertEquals('kitchen', $fake->mirrors[0]['data']['department']);
    }
}
