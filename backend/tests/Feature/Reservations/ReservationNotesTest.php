<?php

namespace Tests\Feature\Reservations;

use App\Models\Guest;
use App\Models\Reservation;
use App\Models\ReservationRoom;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * PATCH /api/cms/reservations/{reservation}/notes (Phase 3, RESV-01, D-11, D-13).
 */
class ReservationNotesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
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

    /** A confirmed reservation with one room line (type + room). */
    private function stay(array $attributes = [], string $state = 'confirmed'): Reservation
    {
        $type        = RoomType::factory()->create();
        $room        = Room::factory()->create(['room_type_id' => $type->id]);
        $reservation = Reservation::factory()->{$state}()->create($attributes);
        ReservationRoom::factory()->create([
            'reservation_id' => $reservation->id,
            'room_type_id'   => $type->id,
            'room_id'        => $room->id,
        ]);
        return $reservation;
    }

    private function patchNotes(Reservation $reservation, array $body, ?string $token = null)
    {
        return $this->withToken($token ?? $this->staffToken('reservations.create'))
            ->withHeaders(['Accept-Language' => 'en'])
            ->patchJson("/api/cms/reservations/{$reservation->uuid}/notes", $body);
    }

    /** activity_log rows for this reservation that touched `notes`, oldest first. */
    private function notesActivity(Reservation $reservation)
    {
        return DB::table('activity_log')
            ->where('subject_type', $reservation->getMorphClass())
            ->where('subject_id', $reservation->id)
            ->where('event', 'updated')
            ->orderBy('id')
            ->get()
            ->map(fn ($row) => [
                'row'     => $row,
                'changes' => json_decode($row->attribute_changes ?? 'null', true) ?? [],
            ])
            ->filter(fn ($a) => array_key_exists('notes', $a['changes']['attributes'] ?? []))
            ->values();
    }

    public function test_staff_can_set_notes(): void
    {
        $reservation = $this->stay();

        $this->patchNotes($reservation, ['notes' => 'VIP, late arrival'])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Reservation notes updated.')
            ->assertJsonPath('data.uuid', $reservation->uuid)
            ->assertJsonPath('data.notes', 'VIP, late arrival')
            ->assertJsonPath('data.rooms.0.room_uuid', $reservation->rooms()->first()->room->uuid)
            ->assertJsonPath('data.guest.uuid', $reservation->guest->uuid);

        $this->assertDatabaseHas('reservations', ['id' => $reservation->id, 'notes' => 'VIP, late arrival']);
    }

    public function test_staff_can_replace_notes(): void
    {
        $reservation = $this->stay();
        $token       = $this->staffToken('reservations.create');

        $this->patchNotes($reservation, ['notes' => 'first text'], $token)->assertOk();
        $this->patchNotes($reservation, ['notes' => 'replacement'], $token)
            ->assertOk()
            ->assertJsonPath('data.notes', 'replacement');

        $this->assertSame('replacement', $reservation->fresh()->notes);
    }

    public function test_null_clears_notes(): void
    {
        $reservation = $this->stay();
        $reservation->update(['notes' => 'to be cleared']);

        $this->patchNotes($reservation, ['notes' => null])
            ->assertOk()
            ->assertJsonPath('data.notes', null);

        $this->assertNull($reservation->fresh()->notes);
    }

    public function test_empty_and_whitespace_notes_are_stored_as_null(): void
    {
        $reservation = $this->stay();
        $token       = $this->staffToken('reservations.create');

        foreach (['', '   '] as $blank) {
            $reservation->update(['notes' => 'something']);

            $this->patchNotes($reservation, ['notes' => $blank], $token)
                ->assertOk()
                ->assertJsonPath('data.notes', null);

            $this->assertNull($reservation->fresh()->notes);
        }
    }

    public function test_notes_key_is_required(): void
    {
        $reservation = $this->stay();

        $this->patchNotes($reservation, [])
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'validation_failed')
            ->assertJsonValidationErrors(['notes']);
    }

    public function test_notes_must_be_a_string(): void
    {
        $reservation = $this->stay();
        $token       = $this->staffToken('reservations.create');

        foreach ([123, ['a']] as $bad) {
            $this->patchNotes($reservation, ['notes' => $bad], $token)
                ->assertStatus(422)
                ->assertJsonPath('error_code', 'validation_failed')
                ->assertJsonValidationErrors(['notes']);
        }

        $this->assertNull($reservation->fresh()->notes);
    }

    public function test_notes_length_boundary(): void
    {
        $reservation = $this->stay();
        $token       = $this->staffToken('reservations.create');

        $this->patchNotes($reservation, ['notes' => str_repeat('a', 2000)], $token)->assertOk();
        $this->assertSame(2000, strlen($reservation->fresh()->notes));

        $this->patchNotes($reservation, ['notes' => str_repeat('a', 2001)], $token)
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'validation_failed')
            ->assertJsonValidationErrors(['notes']);
        $this->assertSame(2000, strlen($reservation->fresh()->notes));
    }

    public function test_arabic_and_emoji_notes_round_trip_unchanged(): void
    {
        $reservation = $this->stay();
        $token       = $this->staffToken('reservations.create', 'reservations.view');
        $text        = 'ملاحظة: النزيل يفضّل طابقاً عالياً 🌙✨';

        $this->patchNotes($reservation, ['notes' => $text], $token)
            ->assertOk()
            ->assertJsonPath('data.notes', $text);

        $this->assertSame($text, $reservation->fresh()->notes);

        $this->withToken($token)
            ->getJson("/api/cms/reservations/{$reservation->uuid}")
            ->assertOk()
            ->assertJsonPath('data.notes', $text);

        // The limit counts code points: 2000 Arabic letters are 4000 bytes.
        $this->patchNotes($reservation, ['notes' => str_repeat('ب', 2000)], $token)->assertOk();
        $this->assertSame(2000, mb_strlen($reservation->fresh()->notes));

        $this->patchNotes($reservation, ['notes' => str_repeat('ب', 2001)], $token)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['notes']);
    }

    public function test_notes_are_editable_in_any_status(): void
    {
        $token = $this->staffToken('reservations.create');

        foreach (['cancelled', 'checkedOut', 'checkedIn', 'pendingVerification'] as $state) {
            $reservation = $this->stay([], $state);

            $this->patchNotes($reservation, ['notes' => "note for {$state}"], $token)
                ->assertOk()
                ->assertJsonPath('data.notes', "note for {$state}");
        }
    }

    public function test_repeating_the_same_notes_writes_no_new_activity(): void
    {
        $reservation = $this->stay();
        $token       = $this->staffToken('reservations.create');

        $this->patchNotes($reservation, ['notes' => 'same'], $token)->assertOk()->assertJsonPath('data.notes', 'same');
        $this->patchNotes($reservation, ['notes' => 'same'], $token)->assertOk()->assertJsonPath('data.notes', 'same');

        $this->assertCount(1, $this->notesActivity($reservation));
    }

    public function test_each_edit_is_recorded_in_order(): void
    {
        $reservation = $this->stay();
        $staff       = User::factory()->create();
        $staff->givePermissionTo('reservations.create');
        $token = $staff->createToken('t')->plainTextToken;

        foreach (['a', 'b', null] as $value) {
            $this->patchNotes($reservation, ['notes' => $value], $token)->assertOk();
        }

        $rows = $this->notesActivity($reservation);
        $this->assertCount(3, $rows);

        $pairs = $rows->map(fn ($a) => [$a['changes']['old']['notes'] ?? null, $a['changes']['attributes']['notes']])->all();
        $this->assertSame([[null, 'a'], ['a', 'b'], ['b', null]], $pairs);

        foreach ($rows as $a) {
            $this->assertSame($staff->getMorphClass(), $a['row']->causer_type);
            $this->assertEquals($staff->id, $a['row']->causer_id);
        }

        $this->assertNull($reservation->fresh()->notes);
    }

    public function test_last_write_wins_without_merging(): void
    {
        $reservation = $this->stay();
        $token       = $this->staffToken('reservations.create');

        $this->patchNotes($reservation, ['notes' => 'first'], $token)->assertOk();
        $this->patchNotes($reservation, ['notes' => 'second'], $token)->assertOk();

        $stored = $reservation->fresh()->notes;
        $this->assertSame('second', $stored);
        $this->assertStringNotContainsString('first', $stored);
    }

    public function test_notes_update_touches_no_other_column(): void
    {
        $reservation = $this->stay([
            'checked_in_at'   => now()->subDay(),
            'hold_expires_at' => now()->addHour(),
        ], 'checkedIn');

        $columns = ['status', 'check_in', 'check_out', 'total_usd', 'checked_in_at', 'checked_out_at', 'hold_expires_at', 'payment_method', 'guest_id', 'booking_code'];
        $before  = (array) DB::table('reservations')->where('id', $reservation->id)->first($columns);

        $this->patchNotes($reservation, ['notes' => 'desk note'])->assertOk();

        $after = (array) DB::table('reservations')->where('id', $reservation->id)->first($columns);
        $this->assertSame($before, $after);
    }

    public function test_staff_detail_returns_notes(): void
    {
        $reservation = $this->stay();
        $this->patchNotes($reservation, ['notes' => 'Allergic to feathers'])->assertOk();

        $this->withToken($this->staffToken('reservations.view'))
            ->getJson("/api/cms/reservations/{$reservation->uuid}")
            ->assertOk()
            ->assertJsonPath('data.notes', 'Allergic to feathers');

        $items = $this->withToken($this->staffToken('reservations.view'))
            ->getJson('/api/cms/reservations')
            ->assertOk()
            ->json('data.items');
        $this->assertSame('Allergic to feathers', collect($items)->firstWhere('uuid', $reservation->uuid)['notes']);
    }

    public function test_guest_routes_never_expose_notes(): void
    {
        $guest       = Guest::factory()->create();
        $reservation = $this->stay(['guest_id' => $guest->id]);
        $reservation->update(['notes' => 'Staff-only remark']);
        $token = $guest->createToken('guest')->plainTextToken;

        $show = $this->withToken($token)
            ->getJson("/api/reservations/{$reservation->uuid}")
            ->assertOk()
            ->assertJsonPath('data.uuid', $reservation->uuid)
            ->assertJsonMissingPath('data.notes');
        $this->assertStringNotContainsString('Staff-only remark', $show->getContent());

        $index = $this->withToken($token)->getJson('/api/reservations')->assertOk();
        $items = $index->json('data.items');
        $this->assertNotEmpty($items);
        foreach ($items as $item) {
            $this->assertArrayNotHasKey('notes', $item);
        }
        $this->assertStringNotContainsString('Staff-only remark', $index->getContent());
    }

    public function test_requires_reservations_create(): void
    {
        $reservation = $this->stay();

        foreach ([$this->staffToken('reservations.view'), $this->presetToken('kitchen')] as $token) {
            $this->patchNotes($reservation, ['notes' => 'nope'], $token)
                ->assertForbidden()
                ->assertJsonPath('error_code', 'forbidden');
        }

        $this->assertNull($reservation->fresh()->notes);
    }

    public function test_requires_a_staff_token(): void
    {
        $reservation = $this->stay();

        $this->patchJson("/api/cms/reservations/{$reservation->uuid}/notes", ['notes' => 'x'])
            ->assertUnauthorized()
            ->assertJsonPath('error_code', 'unauthorized');

        $guestToken = Guest::factory()->create()->createToken('guest')->plainTextToken;
        $this->withToken($guestToken)
            ->patchJson("/api/cms/reservations/{$reservation->uuid}/notes", ['notes' => 'x'])
            ->assertUnauthorized()
            ->assertJsonPath('error_code', 'unauthorized');

        $this->assertNull($reservation->fresh()->notes);
    }

    public function test_unknown_reservation_returns_404(): void
    {
        $this->withToken($this->staffToken('reservations.create'))
            ->patchJson('/api/cms/reservations/'.Str::uuid().'/notes', ['notes' => 'x'])
            ->assertNotFound()
            ->assertJsonPath('error_code', 'not_found');
    }
}
