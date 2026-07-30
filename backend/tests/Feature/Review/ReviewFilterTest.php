<?php

namespace Tests\Feature\Review;

use App\Models\Review;
use App\Models\RoomType;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * `GET /cms/reviews` must answer query strings the way every other list does.
 *
 * The controller read `is_published` itself:
 *
 *     $request->has('is_published') ? $request->boolean('is_published') : null
 *
 * which gave three wrong answers that `BaseFilter` gets right everywhere else:
 *
 *   1. `?is_published=` (empty) meant **false**. That is the "Status: All" option
 *      of a select submitting an empty value — it returned only the drafts, and a
 *      moderator had no way to tell an empty-filter bug from an empty queue.
 *   2. `?is_published=trve` also meant false, silently. A typo answered with a
 *      plausible list instead of a 422.
 *   3. `per_page` was ignored entirely — 15 rows, whatever was asked for.
 *
 * Point the controller back at `$request->boolean('is_published')` and these go
 * red:
 *
 *   - test_an_empty_is_published_means_no_filter
 *   - test_an_uninterpretable_is_published_is_a_422
 *   - test_per_page_is_honoured
 *   - test_per_page_is_clamped_to_the_service_ceiling
 *   - test_rating_can_be_filtered_and_sorted
 */
class ReviewFilterTest extends TestCase
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

    /** Two published reviews and one draft, all on one room type. */
    private function seedQueue(): RoomType
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

        return $roomType;
    }

    // ── Rule 2: empty means no filter ─────────────────────────────────────

    public function test_an_empty_is_published_means_no_filter(): void
    {
        $this->seedQueue();

        $this->withToken($this->editorToken())->getJson('/api/cms/reviews?is_published=')
            ->assertOk()
            ->assertJsonCount(3, 'data.items');
    }

    public function test_no_is_published_at_all_still_returns_everything(): void
    {
        $this->seedQueue();

        $this->withToken($this->editorToken())->getJson('/api/cms/reviews')
            ->assertOk()
            ->assertJsonCount(3, 'data.items');
    }

    // ── Rule 3: uninterpretable is a 422 ──────────────────────────────────

    public function test_an_uninterpretable_is_published_is_a_422(): void
    {
        $this->seedQueue();

        $this->withToken($this->editorToken())->getJson('/api/cms/reviews?is_published=trve')
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error_code', 'validation_failed')
            ->assertJsonValidationErrors(['is_published']);
    }

    // ── The filter itself still filters ───────────────────────────────────

    public function test_the_published_flag_still_filters_both_ways(): void
    {
        $this->seedQueue();
        $token = $this->editorToken();

        $this->withToken($token)->getJson('/api/cms/reviews?is_published=0')
            ->assertOk()->assertJsonCount(1, 'data.items');

        $this->withToken($token)->getJson('/api/cms/reviews?is_published=false')
            ->assertOk()->assertJsonCount(1, 'data.items');

        $this->withToken($token)->getJson('/api/cms/reviews?is_published=1')
            ->assertOk()->assertJsonCount(2, 'data.items');

        // The canonical DSL form works too, like every other list.
        $this->withToken($token)->getJson('/api/cms/reviews?' . http_build_query(['is_published' => ['eq' => 'true']]))
            ->assertOk()->assertJsonCount(2, 'data.items');
    }

    public function test_rating_can_be_filtered_and_sorted(): void
    {
        $roomType = RoomType::factory()->create();

        foreach ([2, 4, 5] as $rating) {
            Review::factory()->create([
                'reviewable_type' => RoomType::class,
                'reviewable_id'   => $roomType->id,
                'rating'          => $rating,
            ]);
        }

        $token = $this->editorToken();

        $this->withToken($token)->getJson('/api/cms/reviews?rating[gte]=4')
            ->assertOk()->assertJsonCount(2, 'data.items');

        $ratings = $this->withToken($token)->getJson('/api/cms/reviews?sort=rating&sort_dir=asc')
            ->assertOk()->json('data.items.*.rating');

        $this->assertSame([2, 4, 5], $ratings);

        // …and a rating that is not a number is a 422, not a silent zero.
        $this->withToken($token)->getJson('/api/cms/reviews?rating=good')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['rating']);
    }

    /** Rule 1: a param the list never whitelisted is dropped, not a 422. */
    public function test_an_unknown_param_is_ignored(): void
    {
        $this->seedQueue();

        $this->withToken($this->editorToken())->getJson('/api/cms/reviews?is_active=nonsense')
            ->assertOk()
            ->assertJsonCount(3, 'data.items');
    }

    // ── per_page ──────────────────────────────────────────────────────────

    public function test_per_page_is_honoured(): void
    {
        $roomType = RoomType::factory()->create();
        Review::factory()->count(5)->create([
            'reviewable_type' => RoomType::class,
            'reviewable_id'   => $roomType->id,
        ]);

        $this->withToken($this->editorToken())->getJson('/api/cms/reviews?per_page=2')
            ->assertOk()
            ->assertJsonCount(2, 'data.items')
            ->assertJsonPath('data.meta.per_page', 2)
            ->assertJsonPath('data.meta.total', 5)
            ->assertJsonPath('data.meta.last_page', 3);
    }

    /**
     * `?per_page=100000` must not become one query for every review the hotel has
     * ever received, plus its guest and reviewable eager loads.
     */
    public function test_per_page_is_clamped_to_the_service_ceiling(): void
    {
        $roomType = RoomType::factory()->create();
        Review::factory()->count(3)->create([
            'reviewable_type' => RoomType::class,
            'reviewable_id'   => $roomType->id,
        ]);

        $this->withToken($this->editorToken())->getJson('/api/cms/reviews?per_page=100000')
            ->assertOk()
            ->assertJsonPath('data.meta.per_page', 100);
    }

    public function test_a_nonsense_per_page_falls_back_to_the_default(): void
    {
        $roomType = RoomType::factory()->create();
        Review::factory()->count(3)->create([
            'reviewable_type' => RoomType::class,
            'reviewable_id'   => $roomType->id,
        ]);

        $this->withToken($this->editorToken())->getJson('/api/cms/reviews?per_page=0')
            ->assertOk()
            ->assertJsonPath('data.meta.per_page', 15);
    }

    // ── Access control is unchanged ───────────────────────────────────────

    public function test_the_queue_is_still_gated(): void
    {
        $this->getJson('/api/cms/reviews')->assertStatus(401);

        $token = User::factory()->create()->createToken('t')->plainTextToken;
        $this->withToken($token)->getJson('/api/cms/reviews')->assertStatus(403);
    }

    public function test_cms_view_alone_can_read_the_queue(): void
    {
        $this->seedQueue();

        $user = User::factory()->create();
        $user->givePermissionTo('cms.view');

        $this->withToken($user->createToken('t')->plainTextToken)
            ->getJson('/api/cms/reviews')
            ->assertOk()
            ->assertJsonCount(3, 'data.items');
    }
}
