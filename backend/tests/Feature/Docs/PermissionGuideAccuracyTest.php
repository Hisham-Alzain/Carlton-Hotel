<?php

namespace Tests\Feature\Docs;

use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\File;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * `docs/API_GUIDE_DASHBOARD.md` is the contract the admin-UI team gates its
 * navigation on. A stale sentence there is not a documentation nit — it is a
 * whole screen built against a permission that does not do what the guide says.
 *
 * It went stale exactly once already: the guide called `cms.view` "reserved,
 * no route gates on it" for several commits after the CMS routes were split
 * into `cms.view|cms.edit` reads and `cms.edit` writes. These tests make the
 * two claims most likely to mislead falsifiable against the router itself:
 *
 * 1. the read/write verb split the guide tells the UI to gate on, and
 * 2. the list of permissions the guide calls inert.
 */
class PermissionGuideAccuracyTest extends TestCase
{
    use RefreshDatabase;

    private const GUIDE = 'docs/API_GUIDE_DASHBOARD.md';

    /** Verbs that read. Everything else mutates. */
    private const READ_VERBS = ['GET', 'HEAD'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    // ── The router really does split CMS routes by verb ───────────────────

    public function test_cms_view_gates_real_routes(): void
    {
        // The claim the guide used to make — "no route currently gates on
        // cms.view" — must stay false.
        $this->assertNotEmpty(
            $this->routesGatedOn('permission:cms.view|cms.edit'),
            'No route gates on cms.view, so the guide must not tell the UI to use it.',
        );
    }

    public function test_every_cms_read_route_admits_cms_view(): void
    {
        $writeRoutesBehindTheReadGate = [];

        foreach ($this->routesGatedOn('permission:cms.view|cms.edit') as $uri => $verbs) {
            if (array_diff($verbs, self::READ_VERBS) !== []) {
                $writeRoutesBehindTheReadGate[] = implode('|', $verbs) . ' ' . $uri;
            }
        }

        $this->assertSame(
            [],
            $writeRoutesBehindTheReadGate,
            'A cms.view-only reviewer can mutate content through: '
            . implode(', ', $writeRoutesBehindTheReadGate),
        );
    }

    public function test_no_cms_read_route_is_locked_behind_cms_edit_alone(): void
    {
        $readRoutesBehindTheWriteGate = [];

        foreach ($this->routesGatedOn('permission:cms.edit') as $uri => $verbs) {
            if (array_diff($verbs, self::READ_VERBS) === []) {
                $readRoutesBehindTheWriteGate[] = implode('|', $verbs) . ' ' . $uri;
            }
        }

        $this->assertSame(
            [],
            $readRoutesBehindTheWriteGate,
            'The guide promises every CMS read admits cms.view, but these need cms.edit: '
            . implode(', ', $readRoutesBehindTheWriteGate),
        );
    }

    // ── The guide's own words ─────────────────────────────────────────────

    public function test_the_guide_no_longer_calls_cms_view_reserved(): void
    {
        $guide = $this->guide();

        foreach ([
            '`cms.view` and `pricing.edit` are reserved',
            '`cms.edit` alone gates every CMS write route',
            '## Module: CMS Content (`cms.edit`)',
            '## Module: In-Stay Service Catalog (`cms.edit`)',
        ] as $stale) {
            $this->assertStringNotContainsString(
                $stale,
                $guide,
                "The guide still says: {$stale}",
            );
        }

        $this->assertStringContainsString('cms.view|cms.edit', $guide);
    }

    /**
     * The claim that went stale the moment the recycle bin landed.
     *
     * "`DELETE` is permanent" is the single most dangerous sentence this guide
     * can carry once it is false: a dashboard built on it writes destructive
     * confirmation copy for an action that is now recoverable, and never builds
     * the trash screen that would let anyone get the record back.
     */
    public function test_the_guide_no_longer_calls_delete_permanent(): void
    {
        $guide = $this->guide();

        foreach ([
            'There are no soft deletes anywhere in the CMS',
            '`DELETE` is permanent',
        ] as $stale) {
            $this->assertStringNotContainsString($stale, $guide, "The guide still says: {$stale}");
        }

        foreach ([
            '/trashed',
            '/restore',
            '/force',
            'cms.restore',
            'cms.purge',
        ] as $documented) {
            $this->assertStringContainsString(
                $documented,
                $guide,
                "The guide does not document {$documented}, so the dashboard cannot build against it.",
            );
        }
    }

    /**
     * The bin gates the guide tells the UI to branch on must be the gates the
     * router actually carries — the same falsifiability the read/write split
     * above gets, for the three verbs that can destroy data.
     */
    public function test_the_bin_gates_the_guide_publishes_match_the_router(): void
    {
        foreach ([
            'permission:cms.restore|cms.purge' => self::READ_VERBS,
            'permission:cms.restore'           => ['POST'],
            'permission:cms.purge'             => ['DELETE'],
        ] as $gate => $expectedVerbs) {
            $routes = $this->routesGatedOn($gate);

            $this->assertNotEmpty($routes, "No route gates on {$gate}, but the guide tells the UI to.");

            foreach ($routes as $uri => $verbs) {
                $this->assertSame(
                    [],
                    array_diff($verbs, $expectedVerbs),
                    implode('|', $verbs)." {$uri} is behind {$gate}, which the guide describes as "
                    .implode('/', $expectedVerbs).' only.',
                );
            }

            // The guide spells a pipe-separated gate `a\|b` inside a markdown
            // table, so match on the permission names rather than the raw gate.
            foreach (explode('|', str_replace('permission:', '', $gate)) as $permission) {
                $this->assertStringContainsString($permission, $this->guide());
            }
        }
    }

    /**
     * The set of permissions the guide calls inert must be exactly the set that
     * really is. Naming an enforced permission as inert tells the UI team to
     * ignore a real `403`; omitting an inert one invites a screen gated on
     * nothing.
     */
    public function test_the_inert_permission_list_matches_reality(): void
    {
        $this->assertSame(
            $this->unenforcedPermissions(),
            $this->documentedInertPermissions(),
            'The guide\'s "Genuinely inert" list disagrees with the codebase.',
        );
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    /**
     * Registered routes carrying exactly `$gate`, as `uri => verbs`.
     *
     * @return array<string, list<string>>
     */
    private function routesGatedOn(string $gate): array
    {
        $matched = [];

        foreach (app(Router::class)->getRoutes() as $route) {
            if (in_array($gate, $route->gatherMiddleware(), true)) {
                $matched[$route->uri()] = array_values($route->methods());
            }
        }

        return $matched;
    }

    /**
     * Seeded permissions whose name appears nowhere in the routes or the
     * application source — so neither middleware, nor a policy, nor a service
     * check can be enforcing them.
     *
     * @return list<string>
     */
    private function unenforcedPermissions(): array
    {
        $source = '';

        foreach (File::allFiles(base_path('app')) as $file) {
            if ($file->getExtension() === 'php') {
                $source .= $file->getContents();
            }
        }

        foreach (File::files(base_path('routes')) as $file) {
            $source .= $file->getContents();
        }

        $unenforced = Permission::query()
            ->pluck('name')
            ->reject(fn (string $permission): bool => str_contains($source, $permission))
            ->values()
            ->all();

        sort($unenforced);

        return $unenforced;
    }

    /**
     * Permission names the guide's "Genuinely inert" section calls out.
     *
     * @return list<string>
     */
    private function documentedInertPermissions(): array
    {
        $guide = $this->guide();

        $start = strpos($guide, '### Genuinely inert');
        $this->assertNotFalse($start, 'The guide has no "Genuinely inert" section.');

        // Runs to the next heading of any level, so the section cannot silently
        // swallow permissions named further down the page.
        $length = preg_match('/\n#{2,3} /', substr($guide, $start + 4), $match, PREG_OFFSET_CAPTURE) === 1
            ? $match[0][1] + 4
            : null;

        $section = $length === null ? substr($guide, $start) : substr($guide, $start, $length);

        preg_match_all('/`([a-z_]+\.[a-z_]+)`/', $section, $found);

        $documented = array_values(array_unique($found[1]));
        sort($documented);

        return $documented;
    }

    private function guide(): string
    {
        $path = base_path(self::GUIDE);
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
