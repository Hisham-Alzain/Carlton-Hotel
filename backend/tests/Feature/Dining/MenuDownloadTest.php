<?php

namespace Tests\Feature\Dining;

use App\Models\DiningVenue;
use App\Models\Media;
use App\Services\Cms\DiningVenueService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * DINING-02 public half (D-11, D-26, D-30): 200 `{url,…}` / 204 none / 404
 * unknown, inactive or trashed venue. No auth.
 */
class MenuDownloadTest extends TestCase
{
    use RefreshDatabase;

    private function url(string $uuid): string
    {
        return "/api/public/dining-venues/{$uuid}/menu/download";
    }

    private function menu(DiningVenue $venue, string $name = 'menu.pdf'): Media
    {
        return Media::factory()->attachedTo($venue)->create([
            'collection' => 'menu',
            'path'       => "cms/DiningVenue/{$venue->uuid}/{$name}",
            'file_name'  => $name,
            'mime_type'  => 'application/pdf',
            'size'       => 2048,
        ]);
    }

    public function test_returns_the_menu_file_url(): void
    {
        $venue = DiningVenue::factory()->create(['is_active' => true]);
        Media::factory()->attachedTo($venue)->create(); // an image, ignored
        $menu = $this->menu($venue);

        $data = $this->getJson($this->url($venue->uuid))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->json('data');

        $this->assertSame(['url', 'file_name', 'mime_type', 'size', 'updated_at'], array_keys($data));
        $this->assertStringEndsWith($menu->path, $data['url']);
        $this->assertSame('menu.pdf', $data['file_name']);
        $this->assertSame('application/pdf', $data['mime_type']);
        $this->assertSame(2048, $data['size']);
        $this->assertSame($menu->updated_at->toIso8601String(), $data['updated_at']);
    }

    public function test_the_latest_menu_wins(): void
    {
        $venue = DiningVenue::factory()->create(['is_active' => true]);
        $this->menu($venue, 'old.pdf');
        $this->menu($venue, 'new.pdf');

        $this->getJson($this->url($venue->uuid))->assertOk()->assertJsonPath('data.file_name', 'new.pdf');
    }

    public function test_no_menu_is_204_with_an_empty_body(): void
    {
        $venue = DiningVenue::factory()->create(['is_active' => true]);
        Media::factory()->attachedTo($venue)->create();

        $response = $this->getJson($this->url($venue->uuid))->assertNoContent();

        $this->assertSame('', $response->getContent());
    }

    public static function missingVenues(): array
    {
        return [['unknown'], ['inactive'], ['trashed']];
    }

    #[DataProvider('missingVenues')]
    public function test_unknown_inactive_or_trashed_venue_is_404(string $case): void
    {
        $uuid = match ($case) {
            'unknown'  => (string) Str::uuid(),
            'inactive' => tap(DiningVenue::factory()->create(['is_active' => false]), fn ($v) => $this->menu($v))->uuid,
            'trashed'  => tap(DiningVenue::factory()->create(['is_active' => true]), function ($v) {
                $this->menu($v);
                $v->delete();
            })->uuid,
        };

        $this->getJson($this->url($uuid))->assertStatus(404)->assertJsonPath('error_code', 'not_found');
    }

    public function test_needs_no_token_and_ignores_one(): void
    {
        $venue = DiningVenue::factory()->create(['is_active' => true]);
        $this->menu($venue);

        $this->getJson($this->url($venue->uuid))->assertOk();
        $this->withToken('not-a-real-token')->getJson($this->url($venue->uuid))->assertOk();
    }

    public function test_query_budget(): void
    {
        $venue = DiningVenue::factory()->create(['is_active' => true]);
        $this->menu($venue);

        // The route binding (1) + the menu row (1) — D-30 cap 2.
        $this->expectsDatabaseQueryCount(2);

        $bound = DiningVenue::where('uuid', $venue->uuid)->firstOrFail();
        app(DiningVenueService::class)->menuFile($bound);
    }
}
