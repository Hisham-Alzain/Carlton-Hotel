<?php

namespace Tests\Feature\Review;

use App\Models\DiningVenue;
use App\Models\Guest;
use App\Models\Reservation;
use App\Models\Review;
use App\Models\RoomType;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function editorToken(): string
    {
        $user = User::factory()->create();
        $user->givePermissionTo('cms.edit');
        return $user->createToken('t')->plainTextToken;
    }

    // ── Submitting ────────────────────────────────────────────────────────

    public function test_guest_can_review_a_room_type_and_the_aggregate_updates(): void
    {
        $guest    = Guest::factory()->create();
        $roomType = RoomType::factory()->create();

        $this->actingAs($guest, 'guests')
            ->postJson("/api/reviews/room_type/{$roomType->uuid}", ['rating' => 4, 'comment' => 'Lovely room'])
            ->assertCreated()
            ->assertJsonPath('data.rating', 4)
            ->assertJsonPath('data.comment', 'Lovely room');

        $this->assertSame('4.0', (string) $roomType->fresh()->rating_avg);
        $this->assertSame(1, $roomType->fresh()->rating_count);
    }

    public function test_guest_can_review_a_dining_venue(): void
    {
        $guest = Guest::factory()->create();
        $venue = DiningVenue::factory()->create();

        $this->actingAs($guest, 'guests')
            ->postJson("/api/reviews/dining_venue/{$venue->uuid}", ['rating' => 5])
            ->assertCreated();

        $this->assertSame(1, $venue->fresh()->rating_count);
    }

    public function test_resubmitting_updates_in_place_instead_of_duplicating(): void
    {
        $guest    = Guest::factory()->create();
        $roomType = RoomType::factory()->create();

        $this->actingAs($guest, 'guests')
            ->postJson("/api/reviews/room_type/{$roomType->uuid}", ['rating' => 2])
            ->assertCreated();

        $this->actingAs($guest, 'guests')
            ->postJson("/api/reviews/room_type/{$roomType->uuid}", ['rating' => 5])
            ->assertOk()
            ->assertJsonPath('data.rating', 5);

        $this->assertDatabaseCount('reviews', 1);
        $this->assertSame('5.0', (string) $roomType->fresh()->rating_avg);
        $this->assertSame(1, $roomType->fresh()->rating_count);
    }

    public function test_average_is_computed_across_guests(): void
    {
        $roomType = RoomType::factory()->create();

        foreach ([5, 4, 3] as $rating) {
            $this->actingAs(Guest::factory()->create(), 'guests')
                ->postJson("/api/reviews/room_type/{$roomType->uuid}", ['rating' => $rating])
                ->assertCreated();
        }

        $this->assertSame('4.0', (string) $roomType->fresh()->rating_avg);
        $this->assertSame(3, $roomType->fresh()->rating_count);
    }

    public function test_verified_stay_is_derived_from_reservation_history(): void
    {
        $guest    = Guest::factory()->create();
        $roomType = RoomType::factory()->create();
        Reservation::factory()->checkedOut()->create(['guest_id' => $guest->id]);

        $this->actingAs($guest, 'guests')
            ->postJson("/api/reviews/room_type/{$roomType->uuid}", ['rating' => 5])
            ->assertCreated()
            ->assertJsonPath('data.is_verified_stay', true);
    }

    public function test_guest_without_a_stay_is_not_marked_verified(): void
    {
        $guest    = Guest::factory()->create();
        $roomType = RoomType::factory()->create();

        $this->actingAs($guest, 'guests')
            ->postJson("/api/reviews/room_type/{$roomType->uuid}", ['rating' => 5])
            ->assertCreated()
            ->assertJsonPath('data.is_verified_stay', false);
    }

    // ── Auth + validation ─────────────────────────────────────────────────

    public function test_unauthenticated_cannot_submit(): void
    {
        $roomType = RoomType::factory()->create();
        $this->postJson("/api/reviews/room_type/{$roomType->uuid}", ['rating' => 5])->assertStatus(401);
    }

    public function test_rating_must_be_between_one_and_five(): void
    {
        $guest    = Guest::factory()->create();
        $roomType = RoomType::factory()->create();

        $this->actingAs($guest, 'guests')
            ->postJson("/api/reviews/room_type/{$roomType->uuid}", ['rating' => 6])
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'validation_failed');
    }

    public function test_unknown_reviewable_type_returns_404(): void
    {
        $guest    = Guest::factory()->create();
        $roomType = RoomType::factory()->create();

        $this->actingAs($guest, 'guests')
            ->postJson("/api/reviews/facility/{$roomType->uuid}", ['rating' => 5])
            ->assertNotFound();
    }

    public function test_unknown_uuid_returns_404(): void
    {
        $this->getJson("/api/public/reviews/room_type/".fake()->uuid())->assertNotFound();
    }

    // ── Public listing ────────────────────────────────────────────────────

    public function test_public_list_returns_only_published_reviews(): void
    {
        $roomType = RoomType::factory()->create();
        Review::factory()->count(2)->create([
            'reviewable_type' => RoomType::class,
            'reviewable_id'   => $roomType->id,
        ]);
        Review::factory()->unpublished()->create([
            'reviewable_type' => RoomType::class,
            'reviewable_id'   => $roomType->id,
        ]);

        $res = $this->getJson("/api/public/reviews/room_type/{$roomType->uuid}")->assertOk();
        $this->assertCount(2, $res->json('data.items'));
    }

    public function test_public_list_exposes_author_name_but_no_contact_details(): void
    {
        $roomType = RoomType::factory()->create();
        Review::factory()->create([
            'reviewable_type' => RoomType::class,
            'reviewable_id'   => $roomType->id,
        ]);

        $res = $this->getJson("/api/public/reviews/room_type/{$roomType->uuid}")->assertOk();

        $author = $res->json('data.items.0.author');
        $this->assertSame(['first_name', 'last_name'], array_keys($author));
    }

    public function test_room_type_resource_exposes_the_rating(): void
    {
        $guest    = Guest::factory()->create();
        $roomType = RoomType::factory()->create();

        $this->actingAs($guest, 'guests')
            ->postJson("/api/reviews/room_type/{$roomType->uuid}", ['rating' => 3])
            ->assertCreated();

        $this->getJson("/api/public/room-types/{$roomType->uuid}")
            ->assertOk()
            ->assertJsonPath('data.rating', '3.0')
            ->assertJsonPath('data.rating_count', 1);
    }

    // ── Moderation ────────────────────────────────────────────────────────

    public function test_admin_can_unpublish_a_review_and_the_aggregate_drops_it(): void
    {
        $guest    = Guest::factory()->create();
        $roomType = RoomType::factory()->create();

        $res = $this->actingAs($guest, 'guests')
            ->postJson("/api/reviews/room_type/{$roomType->uuid}", ['rating' => 5])
            ->assertCreated();

        $this->withToken($this->editorToken())
            ->patchJson("/api/cms/reviews/{$res->json('data.uuid')}/publish", ['is_published' => false])
            ->assertOk()
            ->assertJsonPath('data.is_published', false);

        $this->assertNull($roomType->fresh()->rating_avg);
        $this->assertSame(0, $roomType->fresh()->rating_count);
    }

    public function test_admin_can_filter_reviews_by_published_state(): void
    {
        $roomType = RoomType::factory()->create();
        Review::factory()->count(2)->create([
            'reviewable_type' => RoomType::class,
            'reviewable_id'   => $roomType->id,
        ]);
        Review::factory()->unpublished()->create([
            'reviewable_type' => RoomType::class,
            'reviewable_id'   => $roomType->id,
        ]);

        $token = $this->editorToken();

        $this->withToken($token)->getJson('/api/cms/reviews')
            ->assertOk()
            ->assertJsonCount(3, 'data.items');

        $this->withToken($token)->getJson('/api/cms/reviews?is_published=0')
            ->assertOk()
            ->assertJsonCount(1, 'data.items');
    }

    public function test_staff_without_cms_edit_cannot_moderate(): void
    {
        $token  = User::factory()->create()->createToken('t')->plainTextToken;
        $review = Review::factory()->create();

        $this->withToken($token)
            ->patchJson("/api/cms/reviews/{$review->uuid}/publish", ['is_published' => false])
            ->assertStatus(403);
    }
}
