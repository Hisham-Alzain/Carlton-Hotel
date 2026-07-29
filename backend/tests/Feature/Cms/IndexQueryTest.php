<?php

namespace Tests\Feature\Cms;

use App\Models\Amenity;
use App\Models\RoomType;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pagination, filtering, search and sorting on the index endpoints.
 *
 * The `per_page` half of this is a regression suite for a live bug: the public
 * website asks every collection endpoint for `per_page=100` and the parameter
 * used to be discarded, silently truncating any collection past 15 rows.
 */
class IndexQueryTest extends TestCase
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

    // ── per_page on CMS index ─────────────────────────────────────────────

    public function test_cms_index_honors_per_page(): void
    {
        Amenity::factory()->count(60)->create();

        $res = $this->withToken($this->editorToken())
            ->getJson('/api/cms/amenities?per_page=50')
            ->assertOk();

        $this->assertCount(50, $res->json('data.items'));
        $this->assertSame(50, $res->json('data.meta.per_page'));
        $this->assertSame(60, $res->json('data.meta.total'));
        $this->assertSame(2, $res->json('data.meta.last_page'));
    }

    public function test_cms_index_defaults_to_fifteen_when_per_page_is_omitted(): void
    {
        Amenity::factory()->count(20)->create();

        $res = $this->withToken($this->editorToken())
            ->getJson('/api/cms/amenities')
            ->assertOk();

        $this->assertCount(15, $res->json('data.items'));
        $this->assertSame(15, $res->json('data.meta.per_page'));
    }

    public function test_cms_index_clamps_per_page_to_the_cap(): void
    {
        Amenity::factory()->count(120)->create();

        $res = $this->withToken($this->editorToken())
            ->getJson('/api/cms/amenities?per_page=500')
            ->assertOk();

        $this->assertCount(100, $res->json('data.items'));
        $this->assertSame(100, $res->json('data.meta.per_page'));
    }

    public function test_cms_index_falls_back_to_the_default_for_junk_per_page(): void
    {
        Amenity::factory()->count(20)->create();
        $token = $this->editorToken();

        foreach (['0', '-5', 'abc'] as $value) {
            $res = $this->withToken($token)
                ->getJson("/api/cms/amenities?per_page={$value}")
                ->assertOk();

            $this->assertSame(15, $res->json('data.meta.per_page'), "per_page={$value}");
        }
    }

    // ── per_page on the public API (the live-site regression) ─────────────

    public function test_public_index_returns_every_row_for_per_page_100(): void
    {
        RoomType::factory()->count(24)->create();
        RoomType::factory()->inactive()->count(3)->create();

        $res = $this->getJson('/api/public/room-types?per_page=100')->assertOk();

        // 24 active, the 3 drafts stay hidden — the public contract is unchanged.
        $this->assertCount(24, $res->json('data.items'));
        $this->assertSame(100, $res->json('data.meta.per_page'));
        $this->assertSame(24, $res->json('data.meta.total'));
        $this->assertSame(1, $res->json('data.meta.last_page'));
    }

    public function test_public_index_still_defaults_to_fifteen(): void
    {
        RoomType::factory()->count(24)->create();

        $res = $this->getJson('/api/public/room-types')->assertOk();

        $this->assertCount(15, $res->json('data.items'));
        $this->assertSame(15, $res->json('data.meta.per_page'));
    }

    public function test_public_index_clamps_per_page_to_the_cap(): void
    {
        RoomType::factory()->count(30)->create();

        $res = $this->getJson('/api/public/room-types?per_page=1000')->assertOk();

        $this->assertSame(100, $res->json('data.meta.per_page'));
    }

    public function test_public_index_ignores_cms_filter_params(): void
    {
        RoomType::factory()->count(2)->create();
        RoomType::factory()->inactive()->count(4)->create();

        // The public route must not gain a filter surface — asking for drafts
        // returns the active rows, not the drafts.
        $res = $this->getJson('/api/public/room-types?is_active=false&search=zzzz')->assertOk();

        $this->assertSame(2, $res->json('data.meta.total'));
    }

    // ── Envelope shape ────────────────────────────────────────────────────

    public function test_envelope_shape_is_unchanged(): void
    {
        Amenity::factory()->count(3)->create();

        $this->withToken($this->editorToken())
            ->getJson('/api/cms/amenities?per_page=50')
            ->assertOk()
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'items',
                    'meta' => ['current_page', 'per_page', 'total', 'last_page'],
                ],
                'request_id',
            ])
            ->assertJsonPath('success', true)
            ->assertJsonMissingPath('data.links')
            ->assertJsonMissingPath('data.meta.from');
    }

    // ── is_active filtering ───────────────────────────────────────────────

    public function test_cms_index_filters_by_is_active(): void
    {
        Amenity::factory()->count(5)->create();
        $draft = Amenity::factory()->inactive()->create();
        $token = $this->editorToken();

        $res = $this->withToken($token)
            ->getJson('/api/cms/amenities?is_active=false')
            ->assertOk();

        $this->assertSame(1, $res->json('data.meta.total'));
        $this->assertSame($draft->uuid, $res->json('data.items.0.uuid'));

        $this->assertSame(
            5,
            $this->withToken($token)->getJson('/api/cms/amenities?is_active=true')
                ->assertOk()->json('data.meta.total'),
        );

        // Unfiltered stays unfiltered — editors see drafts by default.
        $this->assertSame(
            6,
            $this->withToken($token)->getJson('/api/cms/amenities')
                ->assertOk()->json('data.meta.total'),
        );
    }

    public function test_cms_index_accepts_the_operator_dsl_form(): void
    {
        Amenity::factory()->count(2)->create();
        Amenity::factory()->inactive()->count(3)->create();

        $res = $this->withToken($this->editorToken())
            ->getJson('/api/cms/amenities?is_active[eq]=0')
            ->assertOk();

        $this->assertSame(3, $res->json('data.meta.total'));
    }

    public function test_cms_index_ignores_columns_outside_the_whitelist(): void
    {
        Amenity::factory()->count(4)->create(['icon' => 'tv']);
        Amenity::factory()->create(['icon' => 'safe']);

        // `icon` is not in AmenityFilter::$safeParms — the param is dropped
        // rather than reaching the query.
        $res = $this->withToken($this->editorToken())
            ->getJson('/api/cms/amenities?icon=safe')
            ->assertOk();

        $this->assertSame(5, $res->json('data.meta.total'));
    }

    // ── Search across translatable JSON columns ───────────────────────────

    public function test_cms_search_matches_an_english_name(): void
    {
        Amenity::factory()->create([
            'slug' => 'rain-shower',
            'name' => ['en' => 'Rain Shower', 'ar' => 'دش مطري'],
        ]);
        Amenity::factory()->count(4)->create();

        $res = $this->withToken($this->editorToken())
            ->getJson('/api/cms/amenities?search=rain sho')
            ->assertOk();

        $this->assertSame(1, $res->json('data.meta.total'));
        $this->assertSame('Rain Shower', $res->json('data.items.0.name.en'));
    }

    public function test_cms_search_is_case_insensitive(): void
    {
        Amenity::factory()->create([
            'slug' => 'espresso-machine',
            'name' => ['en' => 'Espresso Machine', 'ar' => 'ماكينة إسبريسو'],
        ]);

        $res = $this->withToken($this->editorToken())
            ->getJson('/api/cms/amenities?search=ESPRESSO')
            ->assertOk();

        $this->assertSame(1, $res->json('data.meta.total'));
    }

    public function test_cms_search_matches_an_arabic_name(): void
    {
        Amenity::factory()->create([
            'slug' => 'safe-box',
            'name' => ['en' => 'Safe Box', 'ar' => 'خزنة أمان'],
        ]);
        Amenity::factory()->count(4)->create();

        $res = $this->withToken($this->editorToken())
            ->getJson('/api/cms/amenities?search=' . urlencode('خزنة'))
            ->assertOk();

        $this->assertSame(1, $res->json('data.meta.total'));
        $this->assertSame('Safe Box', $res->json('data.items.0.name.en'));
    }

    public function test_cms_search_combines_with_is_active_as_an_and(): void
    {
        Amenity::factory()->create([
            'slug' => 'balcony-one',
            'name' => ['en' => 'Balcony', 'ar' => 'شرفة'],
        ]);
        Amenity::factory()->inactive()->create([
            'slug' => 'balcony-two',
            'name' => ['en' => 'Balcony Suite', 'ar' => 'شرفة جناح'],
        ]);

        // The OR-chain over locales must not escape the is_active condition.
        $res = $this->withToken($this->editorToken())
            ->getJson('/api/cms/amenities?search=balcony&is_active=false')
            ->assertOk();

        $this->assertSame(1, $res->json('data.meta.total'));
        $this->assertSame('Balcony Suite', $res->json('data.items.0.name.en'));
    }

    public function test_cms_search_matches_a_plain_column(): void
    {
        Amenity::factory()->create([
            'slug' => 'nespresso-pod',
            'name' => ['en' => 'Coffee Pod', 'ar' => 'كبسولة قهوة'],
        ]);
        Amenity::factory()->count(3)->create();

        $res = $this->withToken($this->editorToken())
            ->getJson('/api/cms/amenities?search=nespresso')
            ->assertOk();

        $this->assertSame(1, $res->json('data.meta.total'));
    }

    // ── Sorting ───────────────────────────────────────────────────────────

    public function test_cms_index_sorts_by_a_whitelisted_column(): void
    {
        Amenity::factory()->create(['slug' => 'a', 'sort_order' => 3]);
        Amenity::factory()->create(['slug' => 'b', 'sort_order' => 1]);
        Amenity::factory()->create(['slug' => 'c', 'sort_order' => 2]);
        $token = $this->editorToken();

        $asc = $this->withToken($token)->getJson('/api/cms/amenities?sort=sort_order')
            ->assertOk()->json('data.items.*.slug');
        $this->assertSame(['b', 'c', 'a'], $asc);

        $desc = $this->withToken($token)->getJson('/api/cms/amenities?sort=sort_order&sort_dir=desc')
            ->assertOk()->json('data.items.*.slug');
        $this->assertSame(['a', 'c', 'b'], $desc);
    }

    public function test_cms_index_ignores_a_sort_column_outside_the_whitelist(): void
    {
        Amenity::factory()->create(['slug' => 'a', 'sort_order' => 3]);
        Amenity::factory()->create(['slug' => 'b', 'sort_order' => 1]);

        // `icon` is not sortable — the service's own ordering survives.
        $order = $this->withToken($this->editorToken())
            ->getJson('/api/cms/amenities?sort=icon&sort_dir=desc')
            ->assertOk()->json('data.items.*.slug');

        $this->assertSame(['b', 'a'], $order);
    }

    // ── Auth still applies to the filtered route ──────────────────────────

    public function test_cms_index_requires_authentication(): void
    {
        $this->getJson('/api/cms/amenities?per_page=50')
            ->assertStatus(401)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error_code', 'unauthorized');
    }

    public function test_cms_index_requires_the_cms_permission(): void
    {
        $token = User::factory()->create()->createToken('t')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/cms/amenities?per_page=50')
            ->assertStatus(403)
            ->assertJsonPath('error_code', 'forbidden');
    }
}
