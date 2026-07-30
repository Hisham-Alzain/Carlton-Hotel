<?php

namespace Tests\Feature\Review;

use App\Models\Review;
use App\Models\RoomType;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * `GET /api/cms/reviews/{review}` — the per-row read every other CMS module has.
 *
 * Reviews shipped with an index and a publish toggle only, so the moderation
 * queue could list a comment and change its state but never open it: no author,
 * no reviewable, no way to link a row to a detail view. The interesting case is
 * the draft — a review is guest UGC, the queue exists to moderate it, and a
 * `show` that 404'd on anything unpublished would hide exactly the rows a
 * moderator opened it for.
 */
class ReviewShowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function tokenWith(string ...$permissions): string
    {
        $user = User::factory()->create();

        foreach ($permissions as $permission) {
            $user->givePermissionTo($permission);
        }

        return $user->createToken('t')->plainTextToken;
    }

    private function reviewOn(RoomType $roomType, bool $published = true): Review
    {
        $factory = $published ? Review::factory() : Review::factory()->unpublished();

        return $factory->create([
            'reviewable_type' => RoomType::class,
            'reviewable_id'   => $roomType->id,
            'rating'          => 4,
            'comment'         => 'The balcony view was the whole holiday.',
        ]);
    }

    public function test_a_cms_reader_can_open_a_published_review(): void
    {
        $review = $this->reviewOn(RoomType::factory()->create());

        $this->withToken($this->tokenWith('cms.view'))
            ->getJson("/api/cms/reviews/{$review->uuid}")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.uuid', $review->uuid)
            ->assertJsonPath('data.rating', 4)
            ->assertJsonPath('data.comment', 'The balcony view was the whole holiday.')
            ->assertJsonPath('data.is_published', true)
            ->assertJsonStructure([
                'success', 'message', 'request_id',
                'data' => ['uuid', 'rating', 'comment', 'is_verified_stay', 'is_published', 'created_at', 'author'],
            ]);
    }

    /**
     * The one that matters. A draft is what moderation is for, so the read must
     * not inherit the public projection's published-only rule.
     */
    public function test_a_cms_reader_can_open_an_unpublished_review(): void
    {
        $review = $this->reviewOn(RoomType::factory()->create(), published: false);

        $this->withToken($this->tokenWith('cms.view'))
            ->getJson("/api/cms/reviews/{$review->uuid}")
            ->assertOk()
            ->assertJsonPath('data.uuid', $review->uuid)
            ->assertJsonPath('data.is_published', false);

        // And it is genuinely invisible to the public route, which is what makes
        // the CMS the only place it can be read.
        $this->getJson('/api/public/reviews/room_type/'.$review->reviewable->uuid)
            ->assertOk()
            ->assertJsonCount(0, 'data.items');
    }

    /**
     * `ReviewResource` guards `author` with `whenLoaded('guest')`, so a `show`
     * that skipped the eager load would answer 200 with nobody attached to the
     * comment — and still expose no contact details.
     */
    public function test_show_names_the_author_without_leaking_contact_details(): void
    {
        $review = $this->reviewOn(RoomType::factory()->create());

        $author = $this->withToken($this->tokenWith('cms.view'))
            ->getJson("/api/cms/reviews/{$review->uuid}")
            ->assertOk()
            ->json('data.author');

        $this->assertSame(['first_name', 'last_name'], array_keys($author));
        $this->assertSame($review->guest->first_name, $author['first_name']);
    }

    /** An editor holds the read half too — `cms.edit` implies `cms.view`. */
    public function test_an_editor_can_open_a_review(): void
    {
        $review = $this->reviewOn(RoomType::factory()->create());

        $this->withToken($this->tokenWith('cms.edit'))
            ->getJson("/api/cms/reviews/{$review->uuid}")
            ->assertOk()
            ->assertJsonPath('data.uuid', $review->uuid);
    }

    public function test_unauthenticated_requests_are_rejected(): void
    {
        $review = $this->reviewOn(RoomType::factory()->create());

        $this->getJson("/api/cms/reviews/{$review->uuid}")
            ->assertStatus(401)
            ->assertJsonPath('error_code', 'unauthorized');
    }

    public function test_staff_without_a_cms_permission_is_forbidden(): void
    {
        $review = $this->reviewOn(RoomType::factory()->create());
        $token  = User::factory()->create()->createToken('t')->plainTextToken;

        $this->withToken($token)
            ->getJson("/api/cms/reviews/{$review->uuid}")
            ->assertStatus(403)
            ->assertJsonPath('error_code', 'forbidden');
    }

    public function test_an_unknown_uuid_is_a_404(): void
    {
        $this->withToken($this->tokenWith('cms.view'))
            ->getJson('/api/cms/reviews/'.Str::uuid())
            ->assertStatus(404)
            ->assertJsonPath('error_code', 'not_found');
    }
}
