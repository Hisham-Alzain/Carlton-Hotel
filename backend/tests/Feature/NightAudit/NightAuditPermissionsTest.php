<?php

namespace Tests\Feature\NightAudit;

use App\Actions\NightAudit\OpenNightAuditAction;
use App\Enums\ReservationStatus;
use App\Models\NightAudit;
use App\Models\Reservation;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Phase 9 (D-01, D-15): the permission matrix of every night-audit and
 * reports route. Each later plan appends its route to `routes()`.
 */
class NightAuditPermissionsTest extends TestCase
{
    use RefreshDatabase;

    private const D = '2026-10-10';

    /** @var array{audit: string, check: string, blocker: string} */
    private array $ids;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        config(['hotel.timezone' => 'Asia/Damascus']);
        $this->travelTo(Carbon::parse('2026-10-10 19:00:00', 'UTC'));

        // One unsettled departure → a pending blocking check with an open blocker.
        Reservation::factory()->create([
            'status' => ReservationStatus::CHECKED_IN, 'check_in' => '2026-10-07', 'check_out' => self::D,
        ]);
        app(OpenNightAuditAction::class)->handle(self::D, true, User::factory()->create());

        $audit = NightAudit::with(['checks', 'blockers'])->sole();
        $this->ids = [
            'audit'   => $audit->uuid,
            'check'   => $audit->checks->first()->uuid,
            'blocker' => $audit->blockers->first()->uuid,
        ];
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: array<string, mixed>, 3: list<string>}>
     *         method, path template, valid body, permissions that may call it
     */
    public static function routes(): array
    {
        return [
            'show' => ['GET', '/api/operations/night-audit', [], ['reports.view', 'night_audit.manage']],
            // 09-06: attestation is night_audit.manage only (reports.view → 403).
            'check'   => ['PATCH', '/api/operations/night-audit/checks/{check}', ['status' => 'resolved', 'note' => 'ok'], ['night_audit.manage']],
            'blocker' => ['PATCH', '/api/operations/night-audit/blockers/{blocker}', ['note' => 'ok'], ['night_audit.manage']],
            // 09-07: close is night_audit.manage only (not ready → 422, still not 403).
            'close'   => ['POST', '/api/operations/night-audit/{audit}/close', [], ['night_audit.manage']],
            // 09-09: reports are reports.view only — a night auditor sees no revenue.
            'reports' => ['GET', '/api/reports/dashboard', [], ['reports.view']],
        ];
    }

    private function path(string $template): string
    {
        return strtr($template, [
            '{audit}'   => $this->ids['audit'],
            '{check}'   => $this->ids['check'],
            '{blocker}' => $this->ids['blocker'],
        ]);
    }

    private function hit(string $method, string $template, array $body, ?string $token = null)
    {
        $this->app['auth']->forgetGuards();
        $request = $token ? $this->withToken($token) : $this;

        return $request->json($method, $this->path($template), $body);
    }

    private function roleToken(string $role): string
    {
        $this->app['auth']->forgetGuards();

        return User::factory()->create()->assignRole($role)->createToken('t')->plainTextToken;
    }

    private function permissionToken(string ...$permissions): string
    {
        $this->app['auth']->forgetGuards();
        $user = User::factory()->create();
        if ($permissions !== []) {
            $user->givePermissionTo($permissions);
        }

        return $user->createToken('t')->plainTextToken;
    }

    public static function routesByPreset(): array
    {
        $cases = [];
        foreach (array_keys(self::routes()) as $route) {
            foreach (['reception', 'concierge', 'kitchen', 'housekeeping', 'events', 'content_editor', 'content_manager'] as $role) {
                $cases["{$route} as {$role}"] = [$route, $role];
            }
        }

        return $cases;
    }

    public static function routeNames(): array
    {
        return array_map(fn (string $name) => [$name], array_combine(array_keys(self::routes()), array_keys(self::routes())));
    }

    #[DataProvider('routeNames')]
    public function test_no_token_is_401(string $route): void
    {
        [$method, $path, $body] = self::routes()[$route];

        $this->hit($method, $path, $body)->assertStatus(401);
    }

    #[DataProvider('routesByPreset')]
    public function test_every_preset_is_403(string $route, string $role): void
    {
        [$method, $path, $body] = self::routes()[$route];

        $this->hit($method, $path, $body, $this->roleToken($role))
            ->assertStatus(403)->assertJsonPath('error_code', 'forbidden');
    }

    #[DataProvider('routeNames')]
    public function test_unrelated_permission_is_403(string $route): void
    {
        [$method, $path, $body] = self::routes()[$route];

        $this->hit($method, $path, $body, $this->permissionToken('folios.view', 'staff.manage'))
            ->assertStatus(403);
    }

    #[DataProvider('routeNames')]
    public function test_each_granting_permission_alone_passes_and_the_other_is_403(string $route): void
    {
        [$method, $path, $body, $allowed] = self::routes()[$route];

        foreach (['reports.view', 'night_audit.manage'] as $permission) {
            $status = $this->hit($method, $path, $body, $this->permissionToken($permission))->status();

            if (in_array($permission, $allowed, true)) {
                $this->assertNotSame(403, $status, "{$route} should admit {$permission}");
                $this->assertNotSame(401, $status);
            } else {
                $this->assertSame(403, $status, "{$route} must refuse {$permission}");
            }
        }
    }

    #[DataProvider('routeNames')]
    public function test_super_admin_passes(string $route): void
    {
        [$method, $path, $body] = self::routes()[$route];
        $this->app['auth']->forgetGuards();
        $token = User::factory()->superAdmin()->create()->createToken('t')->plainTextToken;

        $status = $this->hit($method, $path, $body, $token)->status();
        $this->assertNotContains($status, [401, 403]);
    }
}
