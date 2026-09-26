<?php

namespace Tests\Feature\Reservations;

use App\Models\Folio;
use App\Models\Reservation;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * GET /api/cms/reservations — `status` and `folio_status` filters
 * (Phase 3, D-10).
 */
class ReservationIndexFilterTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<string, Reservation> */
    private array $r = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->travelTo(Carbon::parse('2027-03-12 09:00:00'));
    }

    private function staffToken(string ...$permissions): string
    {
        $user = User::factory()->create();
        $user->givePermissionTo($permissions);
        return $user->createToken('t')->plainTextToken;
    }

    private function presetToken(string $role): string
    {
        $user = User::factory()->create();
        $user->assignRole($role);
        return $user->createToken('t')->plainTextToken;
    }

    /**
     * One reservation per status, plus checked_out+open folio (the forced
     * one), checked_out+settled folio and checked_in+open folio. Each is
     * created one minute after the previous, so newest-first is deterministic.
     */
    private function fixture(): void
    {
        $specs = [
            'pending_verification' => ['pending_verification', null],
            'pending'              => ['pending', null],
            'confirmed'            => ['confirmed', null],
            'checked_in'           => ['checked_in', null],
            'checked_out'          => ['checked_out', null],
            'cancelled'            => ['cancelled', null],
            'out_open'             => ['checked_out', 'open'],
            'out_settled'          => ['checked_out', 'settled'],
            'in_open'              => ['checked_in', 'open'],
        ];

        $minute = 0;
        foreach ($specs as $key => [$status, $folio]) {
            $reservation = Reservation::factory()->create([
                'status'          => $status,
                'hold_expires_at' => $status === 'pending_verification' ? now()->addMinutes(5) : null,
            ]);
            $reservation->forceFill(['created_at' => now()->subHours(1)->addMinutes($minute++)])->save();

            if ($folio !== null) {
                Folio::factory()->create([
                    'reservation_id' => $reservation->id,
                    'status'         => $folio,
                    'settled_at'     => $folio === 'settled' ? now() : null,
                ]);
            }
            $this->r[$key] = $reservation;
        }
    }

    private function index(string $query = '', ?string $token = null)
    {
        return $this->withToken($token ?? $this->staffToken('reservations.view'))
            ->getJson('/api/cms/reservations'.($query !== '' ? '?'.$query : ''));
    }

    /** @return list<string> sorted uuids */
    private function uuids($response): array
    {
        $uuids = collect($response->json('data.items'))->pluck('uuid')->all();
        sort($uuids);
        return $uuids;
    }

    /** @return list<string> sorted uuids of the named fixture rows */
    private function expected(string ...$keys): array
    {
        $uuids = array_map(fn ($k) => $this->r[$k]->uuid, $keys);
        sort($uuids);
        return $uuids;
    }

    public function test_status_filter(): void
    {
        $this->fixture();

        $this->assertSame(
            $this->expected('checked_out', 'out_open', 'out_settled'),
            $this->uuids($this->index('status=checked_out')->assertOk()),
        );

        $both = $this->expected('checked_in', 'in_open', 'checked_out', 'out_open', 'out_settled');
        $this->assertSame($both, $this->uuids($this->index('status[in]=checked_in,checked_out')->assertOk()));
        $this->assertSame($both, $this->uuids($this->index('status[]=checked_in&status[]=checked_out')->assertOk()));

        // Not validated against the enum: an unknown status simply matches nothing.
        $this->index('status=teleported')->assertOk()->assertJsonCount(0, 'data.items');
    }

    public function test_folio_status_filter(): void
    {
        $this->fixture();

        $this->assertSame($this->expected('out_open', 'in_open'), $this->uuids($this->index('folio_status=open')->assertOk()));
        $this->assertSame($this->expected('out_settled'), $this->uuids($this->index('folio_status=settled')->assertOk()));
    }

    public function test_checked_out_with_open_folio_is_queryable(): void
    {
        $this->fixture();

        $this->index('status=checked_out&folio_status=open')
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.uuid', $this->r['out_open']->uuid);
    }

    public function test_empty_values_mean_unfiltered(): void
    {
        $this->fixture();

        $this->index('status=&folio_status=')->assertOk()->assertJsonCount(9, 'data.items');
    }

    public function test_invalid_folio_status_is_rejected(): void
    {
        $this->fixture();

        foreach (['folio_status=paid', 'folio_status[]=open', 'folio_status=OPEN'] as $query) {
            $this->index($query)
                ->assertStatus(422)
                ->assertJsonPath('success', false)
                ->assertJsonPath('error_code', 'validation_failed')
                ->assertJsonValidationErrors(['folio_status']);
        }
    }

    public function test_unknown_params_are_ignored(): void
    {
        $this->fixture();

        $this->index('colour=red')->assertOk()->assertJsonCount(9, 'data.items');
    }

    public function test_pagination_and_order_are_unchanged(): void
    {
        $this->fixture();

        $response = $this->index()->assertOk();
        $meta     = $response->json('data.meta');

        foreach (['current_page', 'per_page', 'total', 'last_page'] as $key) {
            $this->assertArrayHasKey($key, $meta);
        }
        $this->assertSame(1, $meta['current_page']);
        $this->assertSame(15, $meta['per_page']);
        $this->assertSame(9, $meta['total']);
        $this->assertSame(1, $meta['last_page']);

        $newestFirst = array_map(fn ($k) => $this->r[$k]->uuid, array_reverse(array_keys($this->r)));
        $this->assertSame($newestFirst, collect($response->json('data.items'))->pluck('uuid')->all());

        // Filters keep the newest-first order.
        $this->assertSame(
            [$this->r['out_settled']->uuid, $this->r['out_open']->uuid, $this->r['checked_out']->uuid],
            collect($this->index('status=checked_out')->json('data.items'))->pluck('uuid')->all(),
        );
    }

    public function test_index_still_requires_reservations_view(): void
    {
        $this->index('', $this->presetToken('kitchen'))
            ->assertForbidden()
            ->assertJsonPath('error_code', 'forbidden');

        $this->flushHeaders();
        $this->app->get('auth')->forgetGuards();
        $this->getJson('/api/cms/reservations?status=checked_out')
            ->assertUnauthorized()
            ->assertJsonPath('error_code', 'unauthorized');
    }
}
