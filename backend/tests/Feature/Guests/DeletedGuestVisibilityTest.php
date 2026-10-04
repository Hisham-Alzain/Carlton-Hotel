<?php

namespace Tests\Feature\Guests;

use App\Models\Folio;
use App\Models\Guest;
use App\Models\GuestNote;
use App\Models\Reservation;
use App\Models\ReservationRoom;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use App\Services\Guest\GuestService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Phase 9.1 (GACC-03, D-12): deleted accounts on the Phase 4 staff surfaces —
 * hidden from the directory by default, flagged on directory and profile,
 * and inert to staff writes that would re-attach personal data.
 */
class DeletedGuestVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private Guest $active;
    private Guest $deleted;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->active  = Guest::factory()->create(['last_name' => 'Active']);
        $this->deleted = Guest::factory()->deleted()->create();
    }

    private function token(string ...$permissions): string
    {
        $user = User::factory()->create();
        $user->givePermissionTo($permissions);

        return $user->createToken('t')->plainTextToken;
    }

    private function directory(string $query = '')
    {
        return $this->withToken($this->token('guests.view'))->getJson('/api/guests'.$query);
    }

    private function uuids($response): array
    {
        return collect($response->json('data.items'))->pluck('uuid')->sort()->values()->all();
    }

    public function test_directory_hides_deleted_accounts_by_default(): void
    {
        $response = $this->directory()->assertOk();

        $this->assertSame([$this->active->uuid], $this->uuids($response));
        $this->assertSame(1, $response->json('data.meta.total'));
    }

    public function test_directory_shows_deleted_accounts_on_request(): void
    {
        $this->assertSame([$this->deleted->uuid], $this->uuids($this->directory('?account_status=deleted')->assertOk()));

        $both = [$this->active->uuid, $this->deleted->uuid];
        sort($both);
        $this->assertSame($both, $this->uuids($this->directory('?account_status[in]=active,deleted')->assertOk()));
    }

    public function test_an_unknown_status_matches_nothing(): void
    {
        // BaseFilter's contract: a whitelisted eq value is compared, not validated.
        $this->directory('?account_status=bogus')->assertOk()->assertJsonPath('data.meta.total', 0);
    }

    public function test_directory_rows_carry_the_flag(): void
    {
        $rows = collect($this->directory('?account_status[in]=active,deleted')->assertOk()->json('data.items'))->keyBy('uuid');

        $this->assertSame('active', $rows[$this->active->uuid]['account_status']);
        $this->assertNull($rows[$this->active->uuid]['account_deleted_at']);
        $this->assertSame('deleted', $rows[$this->deleted->uuid]['account_status']);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/', $rows[$this->deleted->uuid]['account_deleted_at']);
    }

    public function test_the_directory_stays_at_five_queries(): void
    {
        // A live stay with a room, so every eager-load query of the budget runs.
        $type = RoomType::factory()->create();
        $reservation = Reservation::factory()->confirmed()->create(['guest_id' => $this->active->id]);
        ReservationRoom::factory()->create([
            'reservation_id' => $reservation->id,
            'room_type_id'   => $type->id,
            'room_id'        => Room::factory()->create(['room_type_id' => $type->id])->id,
        ]);

        $this->expectsDatabaseQueryCount(5);

        $page = app(GuestService::class)->index([], null, 15)['data'];
        $this->assertSame(1, $page->total());
    }

    public function test_profile_of_a_deleted_guest_is_200_with_the_flag(): void
    {
        $r = Reservation::factory()->checkedOut()->create(['guest_id' => $this->deleted->id, 'last_name' => 'Haddad']);

        $this->withToken($this->token('guests.view'))
            ->getJson("/api/guests/{$this->deleted->uuid}")
            ->assertOk()
            ->assertJsonPath('data.account_status', 'deleted')
            ->assertJsonPath('data.stay_history.0.booking_code', $r->booking_code);
    }

    public function test_active_profile_reads_active(): void
    {
        $this->withToken($this->token('guests.view'))
            ->getJson("/api/guests/{$this->active->uuid}")
            ->assertOk()
            ->assertJsonPath('data.account_status', 'active')
            ->assertJsonPath('data.account_deleted_at', null);
    }

    /** @return array<string, array{string}> */
    public static function locales(): array
    {
        return ['en' => ['en'], 'ar' => ['ar'], 'fr' => ['fr'], 'tr' => ['tr'], 'es' => ['es']];
    }

    #[DataProvider('locales')]
    public function test_notes_on_a_deleted_guest_are_refused(string $locale): void
    {
        $this->withToken($this->token('guests.edit'))
            ->withHeaders(['Accept-Language' => $locale])
            ->postJson("/api/guests/{$this->deleted->uuid}/notes", ['body' => 'Allergic to nuts'])
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'guest_account_deleted')
            ->assertJsonPath('message', __('custom.errors.guest_account_deleted', [], $locale));

        $this->assertSame(0, GuestNote::count());
    }

    #[DataProvider('locales')]
    public function test_preferences_on_a_deleted_guest_are_refused(string $locale): void
    {
        $this->withToken($this->token('guests.edit'))
            ->withHeaders(['Accept-Language' => $locale])
            ->patchJson("/api/guests/{$this->deleted->uuid}/preferences", ['bed_type' => 'king'])
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'guest_account_deleted')
            ->assertJsonPath('message', __('custom.errors.guest_account_deleted', [], $locale));

        $fresh = $this->deleted->fresh();
        $this->assertNull($fresh->getRawOriginal('bed_type'));
        $this->assertNull($fresh->preferences_updated_at);
    }

    public function test_receipt_names_the_payer_from_the_reservation(): void
    {
        $reservation = Reservation::factory()->checkedOut()->create(['guest_id' => $this->deleted->id, 'last_name' => 'Haddad']);
        $folio = Folio::factory()->create(['reservation_id' => $reservation->id]);

        $html = view('pdf.receipt', [
            'receipt' => [
                'reservation'     => $reservation->load('guest'),
                'folio'           => $folio->load('items'),
                'payments'        => collect(),
                'balance_due_usd' => 0,
            ],
            'locale' => 'en',
            'isRtl'  => false,
        ])->render();

        $this->assertStringContainsString('Haddad', $html);
    }

    /** QA regression: a front-desk booking must not open a live stay on an erased account. */
    #[DataProvider('locales')]
    public function test_front_desk_booking_on_a_deleted_guest_is_refused(string $locale): void
    {
        $roomType = RoomType::factory()->create(['base_price_usd' => 100, 'is_active' => true]);
        Room::factory()->create(['room_type_id' => $roomType->id, 'is_active' => true]);

        $this->withToken($this->token('reservations.create'))
            ->withHeaders(['Accept-Language' => $locale])
            ->postJson('/api/cms/reservations', [
                'guest_uuid'     => $this->deleted->uuid,
                'room_type_uuid' => $roomType->uuid,
                'check_in'       => now()->addDays(3)->toDateString(),
                'check_out'      => now()->addDays(5)->toDateString(),
                'payment_method' => 'on_arrival',
            ])
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'guest_account_deleted')
            ->assertJsonPath('message', __('custom.errors.guest_account_deleted', [], $locale));

        $this->assertSame(0, Reservation::count());
    }
}
