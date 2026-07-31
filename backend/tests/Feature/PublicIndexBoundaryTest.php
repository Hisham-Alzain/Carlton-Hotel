<?php

namespace Tests\Feature;

use App\Base\BasePublicIndexController;
use App\Models\Amenity;
use App\Models\DiningVenue;
use App\Models\EventSpace;
use App\Models\Experience;
use App\Models\Facility;
use App\Models\Faq;
use App\Models\HomeSlider;
use App\Models\JournalPost;
use App\Models\Promotion;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\Testimonial;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * A public list must not gain a filter surface.
 *
 * `BaseIndexController::index()` hands the raw query string to the service's
 * filter, which is right for a staff list. Wiring a public route to it would let
 * `?is_active=false` widen an anonymous list to unpublished content — a draft
 * room type readable by anyone who guesses the parameter.
 *
 * `BasePublicIndexController` exists to make that impossible rather than merely
 * unconventional: it overrides `index()` to call `indexPublic()`, which takes a
 * page size and nothing else. This suite pins both halves — the behaviour on
 * every live public list, and the structural property that no public list
 * controller is wired to the filtering `index()`.
 */
class PublicIndexBoundaryTest extends TestCase
{
    use RefreshDatabase;

    /** path => model, for every list under `/api/public` served by the base class. */
    private const PUBLIC_LISTS = [
        'amenities'     => Amenity::class,
        'faqs'          => Faq::class,
        'home-sliders'  => HomeSlider::class,
        'testimonials'  => Testimonial::class,
        'room-types'    => RoomType::class,
        'rooms'         => Room::class,
        'facilities'    => Facility::class,
        'dining-venues' => DiningVenue::class,
        'event-spaces'  => EventSpace::class,
        'promotions'    => Promotion::class,
        'experiences'   => Experience::class,
        'journal'       => JournalPost::class,
    ];

    public function test_no_query_string_filter_can_widen_a_public_list(): void
    {
        foreach (self::PUBLIC_LISTS as $path => $model) {
            $model::factory()->count(2)->create(['is_active' => true]);
            $model::factory()->count(3)->create(['is_active' => false]);

            foreach ([
                '?is_active=false',
                '?is_active[eq]=false',
                '?is_active=',
                '?search=a',
                '?sort=id&sort_dir=desc',
                '?is_active=false&search=a&per_page=100',
            ] as $query) {
                $res = $this->getJson("/api/public/{$path}{$query}")->assertOk();

                $this->assertSame(
                    2,
                    $res->json('data.meta.total'),
                    "GET /api/public/{$path}{$query} changed the size of a public list",
                );
            }

            $model::query()->forceDelete();
        }
    }

    public function test_a_bad_filter_value_cannot_even_reach_the_validator_on_a_public_list(): void
    {
        RoomType::factory()->count(2)->create(['is_active' => true]);

        // On a CMS list this is a 422 from the filter's cast. On a public list
        // the value is never read, so it is simply ignored.
        $this->getJson('/api/public/room-types?is_active=trve')
            ->assertOk()
            ->assertJsonPath('data.meta.total', 2);
    }

    public function test_every_public_list_route_is_served_by_the_public_base_class(): void
    {
        $checked = 0;

        foreach (Route::getRoutes() as $route) {
            if (! str_starts_with($route->uri(), 'api/public/')) {
                continue;
            }
            if (! str_ends_with((string) $route->getActionName(), '@index')) {
                continue;
            }

            $controller = $route->getController();

            // Controllers with a bespoke public list (Gallery and Menu return two
            // collections, not one paginator) are exempt by design, but must not
            // be reading the query string either.
            if (! $controller instanceof BasePublicIndexController) {
                $this->assertStringNotContainsString(
                    'indexParams',
                    (string) file_get_contents((new \ReflectionClass($controller))->getFileName()),
                    $route->getActionName().' reads the query string on a public route',
                );

                continue;
            }

            $checked++;
        }

        $this->assertGreaterThanOrEqual(12, $checked, 'expected at least 12 public lists on BasePublicIndexController');
    }

    public function test_the_public_base_class_never_reads_the_filter_params(): void
    {
        $source = (string) file_get_contents(
            (new \ReflectionClass(BasePublicIndexController::class))->getFileName()
        );

        // The whole security property in one assertion: the override cannot pass
        // a filter down because it never asks for one.
        $body = substr($source, (int) strpos($source, 'public function index'));

        $this->assertStringNotContainsString('indexParams', $body);
        $this->assertStringContainsString('indexPublic', $body);
    }

    public function test_every_public_controller_service_actually_offers_index_public(): void
    {
        foreach (Route::getRoutes() as $route) {
            if (! str_starts_with($route->uri(), 'api/public/')) {
                continue;
            }

            $controller = $route->getController();

            if (! $controller instanceof BasePublicIndexController) {
                continue;
            }

            $service = (new \ReflectionMethod($controller, 'service'));
            $service->setAccessible(true);

            $this->assertTrue(
                method_exists($service->invoke($controller), 'indexPublic'),
                $route->getActionName().'\'s service has no indexPublic()',
            );
        }
    }
}
