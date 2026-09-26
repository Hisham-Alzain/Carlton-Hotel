<?php

namespace Tests\Feature\Guests;

use App\Models\Guest;
use App\Models\GuestNote;
use App\Models\Reservation;
use App\Models\ReservationRoom;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * GET|POST /api/guests/{guest}/notes (Phase 4, GUEST-03, D-01, D-06, D-07).
 */
class GuestNotesTest extends TestCase
{
    use RefreshDatabase;

    private const SENTINEL = 'NOTE-SENTINEL-7Q';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function staffUser(string ...$permissions): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo($permissions);
        return $user;
    }

    private function staffToken(string ...$permissions): string
    {
        return $this->staffUser(...$permissions)->createToken('t')->plainTextToken;
    }

    private function presetToken(string $role): string
    {
        $user = User::factory()->create();
        $user->assignRole($role);
        return $user->createToken('t')->plainTextToken;
    }

    private function postNote(Guest $guest, array $body, ?string $token = null)
    {
        return $this->withToken($token ?? $this->staffToken('guests.edit'))
            ->withHeaders(['Accept-Language' => 'en'])
            ->postJson("/api/guests/{$guest->uuid}/notes", $body);
    }

    private function getNotes(Guest $guest, string $query = '', ?string $token = null)
    {
        return $this->withToken($token ?? $this->staffToken('guests.view'))
            ->withHeaders(['Accept-Language' => 'en'])
            ->getJson("/api/guests/{$guest->uuid}/notes{$query}");
    }

    private function stayFor(Guest $guest, string $state, array $attributes = []): Reservation
    {
        $type        = RoomType::factory()->create();
        $room        = Room::factory()->create(['room_type_id' => $type->id]);
        $reservation = Reservation::factory()->{$state}()->create(array_merge(['guest_id' => $guest->id], $attributes));
        ReservationRoom::factory()->create([
            'reservation_id' => $reservation->id,
            'room_type_id'   => $type->id,
            'room_id'        => $room->id,
        ]);
        return $reservation;
    }

    public function test_staff_adds_a_note(): void
    {
        $guest = Guest::factory()->create();
        $staff = $this->staffUser('guests.edit');

        $res = $this->postNote($guest, ['body' => 'Prefers a quiet room'], $staff->createToken('t')->plainTextToken)
            ->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Guest note added.')
            ->assertJsonPath('data.body', 'Prefers a quiet room')
            ->assertJsonPath('data.author.uuid', $staff->uuid)
            ->assertJsonPath('data.author.name', $staff->name);

        $this->assertTrue(Str::isUuid($res->json('data.uuid')));
        $this->assertNotNull($res->json('data.created_at'));
        $this->assertSame(['uuid', 'body', 'author', 'created_at'], array_keys($res->json('data')));
        $this->assertDatabaseHas('guest_notes', [
            'guest_id' => $guest->id,
            'user_id'  => $staff->id,
            'body'     => 'Prefers a quiet room',
        ]);
    }

    public function test_notes_list_newest_first_and_paginates(): void
    {
        $guest = Guest::factory()->create();
        $base  = Carbon::parse('2027-03-10 09:00:00');

        for ($i = 0; $i < 17; $i++) {
            $note = GuestNote::factory()->create(['guest_id' => $guest->id, 'body' => "note {$i}"]);
            $note->forceFill(['created_at' => $base->copy()->addMinutes($i)])->save();
        }

        $res = $this->getNotes($guest)
            ->assertOk()
            ->assertJsonPath('data.meta.total', 17)
            ->assertJsonPath('data.meta.per_page', 15);
        $this->assertCount(15, $res->json('data.items'));
        $this->assertSame('note 16', $res->json('data.items.0.body'));
        $this->assertNotNull($res->json('data.items.0.author.uuid'));
        $this->assertArrayNotHasKey('id', $res->json('data.items.0'));

        $this->assertCount(17, $this->getNotes($guest, '?per_page=100')->json('data.items'));
        $this->getNotes($guest, '?per_page=500')->assertOk()->assertJsonPath('data.meta.per_page', 100);
    }

    public function test_same_second_notes_order_by_id_desc(): void
    {
        $guest = Guest::factory()->create();
        $at    = Carbon::parse('2027-03-10 09:00:00');
        $first = GuestNote::factory()->create(['guest_id' => $guest->id, 'body' => 'first']);
        $later = GuestNote::factory()->create(['guest_id' => $guest->id, 'body' => 'later']);
        GuestNote::whereKey([$first->id, $later->id])->update(['created_at' => $at]);

        $items = $this->getNotes($guest)->assertOk()->json('data.items');

        $this->assertSame([$later->uuid, $first->uuid], array_column($items, 'uuid'));
    }

    public function test_guest_without_notes_lists_nothing(): void
    {
        $guest = Guest::factory()->create();
        GuestNote::factory()->create(); // someone else's

        $this->getNotes($guest)
            ->assertOk()
            ->assertJsonPath('data.items', [])
            ->assertJsonPath('data.meta.total', 0);
    }

    public function test_arabic_and_emoji_round_trip(): void
    {
        $guest = Guest::factory()->create();
        $body  = 'ضيف مهم 🌙 يفضّل الطابق العالي';

        $this->postNote($guest, ['body' => $body])->assertStatus(201)->assertJsonPath('data.body', $body);
        $this->getNotes($guest)->assertOk()->assertJsonPath('data.items.0.body', $body);

        $this->postNote($guest, ['body' => str_repeat('ض', 2000)])->assertStatus(201);
    }

    public function test_body_validation(): void
    {
        $guest = Guest::factory()->create();

        foreach ([[], ['body' => ''], ['body' => '   '], ['body' => str_repeat('a', 2001)]] as $body) {
            $this->postNote($guest, $body)
                ->assertStatus(422)
                ->assertJsonPath('error_code', 'validation_failed')
                ->assertJsonValidationErrors('body');
        }

        $this->assertDatabaseCount('guest_notes', 0);
        $this->postNote($guest, ['body' => str_repeat('a', 2000)])->assertStatus(201);
    }

    public function test_notes_are_append_only(): void
    {
        $methods = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($route) => str_starts_with($route->uri(), 'api/guests/{guest}/notes'))
            ->flatMap(fn ($route) => $route->methods())
            ->unique()
            ->sort()
            ->values()
            ->all();

        $this->assertSame(['GET', 'HEAD', 'POST'], $methods);
    }

    public function test_gates(): void
    {
        $guest = Guest::factory()->create();
        $url   = "/api/guests/{$guest->uuid}/notes";

        $this->getJson($url)->assertStatus(401)->assertJsonPath('error_code', 'unauthorized');
        $this->postJson($url, ['body' => 'x'])->assertStatus(401)->assertJsonPath('error_code', 'unauthorized');

        $guestToken = $guest->createToken('t')->plainTextToken;
        $this->withToken($guestToken)->getJson($url)->assertStatus(401);
        $this->withToken($guestToken)->postJson($url, ['body' => 'x'])->assertStatus(401);

        $this->postNote($guest, ['body' => 'x'], $this->staffToken('guests.view'))
            ->assertStatus(403)->assertJsonPath('error_code', 'forbidden');
        $this->getNotes($guest, '', $this->staffToken('guests.edit'))
            ->assertStatus(403)->assertJsonPath('error_code', 'forbidden');

        $housekeeping = $this->presetToken('housekeeping');
        $this->getNotes($guest, '', $housekeeping)->assertStatus(403);
        $this->postNote($guest, ['body' => 'x'], $housekeeping)->assertStatus(403);

        foreach (['reception', 'concierge'] as $preset) {
            $token = $this->presetToken($preset);
            $this->postNote($guest, ['body' => "by {$preset}"], $token)->assertStatus(201);
            $this->getNotes($guest, '', $token)->assertOk();
        }

        $unknown = (string) Str::uuid();
        $this->withToken($this->staffToken('guests.view', 'guests.edit'))
            ->getJson("/api/guests/{$unknown}/notes")
            ->assertStatus(404)->assertJsonPath('error_code', 'not_found');
        $this->withToken($this->staffToken('guests.view', 'guests.edit'))
            ->postJson("/api/guests/{$unknown}/notes", ['body' => 'x'])
            ->assertStatus(404)->assertJsonPath('error_code', 'not_found');
    }

    public function test_notes_are_not_activity_logged(): void
    {
        $guest = Guest::factory()->create();

        $this->postNote($guest, ['body' => 'Watch out: ' . self::SENTINEL])->assertStatus(201);

        $this->assertSame(0, DB::table('activity_log')->where('subject_type', (new GuestNote)->getMorphClass())->count());

        foreach (DB::table('activity_log')->get() as $row) {
            $this->assertStringNotContainsString(self::SENTINEL, (string) $row->attribute_changes);
            $this->assertStringNotContainsString(self::SENTINEL, (string) $row->properties);
        }
    }

    public function test_notes_never_reach_guest_responses(): void
    {
        $guest = Guest::factory()->create();
        $this->stayFor($guest, 'confirmed', [
            'check_in'  => now()->addDays(3)->toDateString(),
            'check_out' => now()->addDays(5)->toDateString(),
        ]);
        $this->stayFor($guest, 'checkedIn', [
            'check_in'  => now()->subDay()->toDateString(),
            'check_out' => now()->addDays(2)->toDateString(),
        ]);
        $this->stayFor($guest, 'checkedOut');
        GuestNote::factory()->create(['guest_id' => $guest->id, 'body' => 'VIP ' . self::SENTINEL]);

        $token = $guest->createToken('t')->plainTextToken;

        $me = $this->withToken($token)->getJson('/api/auth/guest/me')->assertOk();
        $this->assertArrayNotHasKey('notes', $me->json('data'));

        foreach (['/api/auth/guest/me', '/api/stays/status', '/api/stays/active', '/api/stays/upcoming', '/api/stays/past', '/api/reservations'] as $url) {
            $res = $this->withToken($token)->getJson($url)->assertOk();
            $this->assertStringNotContainsString(self::SENTINEL, $res->getContent(), $url);
        }
    }

    public function test_author_deletion_keeps_the_note_and_guest_deletion_removes_it(): void
    {
        $guest  = Guest::factory()->create();
        $author = User::factory()->create();
        GuestNote::factory()->create(['guest_id' => $guest->id, 'user_id' => $author->id, 'body' => 'kept']);

        $author->forceDelete();

        $this->getNotes($guest)
            ->assertOk()
            ->assertJsonPath('data.items.0.body', 'kept')
            ->assertJsonPath('data.items.0.author', null);

        $guest->delete();
        $this->assertDatabaseCount('guest_notes', 0);
    }
}
