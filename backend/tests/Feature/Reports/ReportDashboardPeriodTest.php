<?php

namespace Tests\Feature\Reports;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Phase 9 (D-01, D-15, D-16): GET /api/reports/dashboard period contract and gate.
 */
class ReportDashboardPeriodTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/api/reports/dashboard';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        config(['hotel.timezone' => 'Asia/Damascus']);
        // 01:30 hotel time on 2026-10-10 is still 2026-10-09 in UTC.
        $this->travelTo(Carbon::parse('2026-10-09 22:30:00', 'UTC'));
    }

    private function token(string ...$permissions): string
    {
        $this->app['auth']->forgetGuards();
        $user = User::factory()->create();
        if ($permissions !== []) {
            $user->givePermissionTo($permissions);
        }

        return $user->createToken('t')->plainTextToken;
    }

    private function dashboard(array $query = [], ?string $token = null)
    {
        $url = self::URL . ($query ? '?' . http_build_query($query) : '');

        return $this->withToken($token ?? $this->token('reports.view'))->getJson($url);
    }

    public function test_default_period_is_hotel_today(): void
    {
        $data = $this->dashboard()->assertOk()->assertJsonPath('success', true)->json('data');

        $this->assertSame([
            'date_from' => '2026-10-10', 'date_to' => '2026-10-10', 'days' => 1, 'timezone' => 'Asia/Damascus',
        ], $data['period']);
        $this->assertStringEndsWith('Z', $data['generated_at']);
        $this->assertSame('2026-10-09T22:30:00Z', $data['generated_at']);
    }

    public function test_full_month_is_thirty_one_days(): void
    {
        $this->dashboard(['date_from' => '2026-10-01', 'date_to' => '2026-10-31'])
            ->assertOk()->assertJsonPath('data.period.days', 31);
    }

    public function test_future_period_is_allowed(): void
    {
        $this->dashboard(['date_from' => '2027-01-01', 'date_to' => '2027-01-05'])
            ->assertOk()->assertJsonPath('data.period.days', 5);
    }

    public static function invalidPeriods(): array
    {
        return [
            'only from'      => [['date_from' => '2026-10-01'], 'date_to'],
            'only to'        => [['date_to' => '2026-10-01'], 'date_from'],
            'reversed'       => [['date_from' => '2026-10-05', 'date_to' => '2026-10-04'], 'date_to'],
            'impossible day' => [['date_from' => '2026-02-30', 'date_to' => '2026-03-01'], 'date_from'],
            'slashes'        => [['date_from' => '2026/10/01', 'date_to' => '2026-10-02'], 'date_from'],
            'array'          => [['date_from' => ['x'], 'date_to' => '2026-10-02'], 'date_from'],
        ];
    }

    #[DataProvider('invalidPeriods')]
    public function test_invalid_periods_are_validation_errors(array $query, string $field): void
    {
        $this->dashboard($query)
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'validation_failed')
            ->assertJsonValidationErrors([$field]);
    }

    public function test_thirty_two_days_is_too_long(): void
    {
        $response = $this->dashboard(['date_from' => '2026-10-01', 'date_to' => '2026-11-01'])
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'validation_failed')
            ->assertJsonValidationErrors(['date_to']);

        $this->assertContains(__('custom.validation.report_period_too_long'), $response->json('errors.date_to'));
    }

    public function test_too_long_message_is_localized(): void
    {
        $this->app['auth']->forgetGuards();
        $response = $this->withToken($this->token('reports.view'))
            ->withHeader('Accept-Language', 'ar')
            ->getJson(self::URL . '?date_from=2026-10-01&date_to=2026-11-01')
            ->assertStatus(422);

        $this->assertContains(trans('custom.validation.report_period_too_long', [], 'ar'), $response->json('errors.date_to'));
    }

    public function test_no_token_is_401(): void
    {
        $this->getJson(self::URL)->assertStatus(401);
    }

    public function test_night_auditor_without_reports_view_is_403(): void
    {
        $this->dashboard([], $this->token('night_audit.manage'))
            ->assertStatus(403)->assertJsonPath('error_code', 'forbidden');
    }

    public function test_every_preset_is_403(): void
    {
        foreach (['reception', 'concierge', 'kitchen', 'housekeeping', 'events', 'content_editor', 'content_manager'] as $role) {
            $this->app['auth']->forgetGuards();
            $token = User::factory()->create()->assignRole($role)->createToken('t')->plainTextToken;
            $this->withToken($token)->getJson(self::URL)->assertStatus(403);
        }
    }

    public function test_super_admin_passes(): void
    {
        $this->app['auth']->forgetGuards();
        $token = User::factory()->superAdmin()->create()->createToken('t')->plainTextToken;

        $this->withToken($token)->getJson(self::URL)->assertOk();
    }
}
