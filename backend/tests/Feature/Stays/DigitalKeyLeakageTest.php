<?php

namespace Tests\Feature\Stays;

use App\Contracts\FirebaseServiceInterface;
use App\Events\CheckInApproved;
use App\Models\CheckInApproval;
use App\Models\DeviceToken;
use App\Models\Guest;
use App\Models\Reservation;
use App\Models\ReservationRoom;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Support\FakeFirebaseService;
use Tests\TestCase;

/**
 * The single place every digital-key exposure path is enumerated (Phase 4,
 * D-11, CONTEXT "Specific Ideas"). Any new surface touching a Reservation
 * belongs here. Positive control: only the owning guest's own stay reads ever
 * contain the code.
 */
class DigitalKeyLeakageTest extends TestCase
{
    use RefreshDatabase;

    private const KEY_PATTERN = '/[ABCDEFGHJKLMNPQRSTUVWXYZ23456789]{4}-[ABCDEFGHJKLMNPQRSTUVWXYZ23456789]{4}-[ABCDEFGHJKLMNPQRSTUVWXYZ23456789]{4}/';

    private FakeFirebaseService $firebase;

    private string $staffToken;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        config(['hotel.timezone' => 'Asia/Damascus', 'hotel.check_out_time' => '12:00']);
        $this->travelTo(Carbon::parse('2027-03-10 09:00:00'));
        $this->firebase = new FakeFirebaseService;
        $this->app->instance(FirebaseServiceInterface::class, $this->firebase);

        $staff = User::factory()->create();
        $staff->givePermissionTo(['reservations.view', 'reservations.create', 'reservations.cancel', 'folios.settle']);
        $this->staffToken = $staff->createToken('t')->plainTextToken;
    }

    /** A confirmed stay arriving today with room 701, a pending approval and a device token. */
    private function stay(): Reservation
    {
        $type        = RoomType::factory()->create();
        $reservation = Reservation::factory()->confirmed()->create(['check_in' => '2027-03-10', 'check_out' => '2027-03-12']);
        ReservationRoom::factory()->create([
            'reservation_id' => $reservation->id,
            'room_type_id'   => $type->id,
            'room_id'        => Room::factory()->create(['room_type_id' => $type->id, 'number' => '701'])->id,
        ]);
        CheckInApproval::factory()->create(['reservation_id' => $reservation->id]);
        DeviceToken::factory()->create(['guest_id' => $reservation->guest_id]);

        return $reservation;
    }

    private function decide(Reservation $reservation, string $status)
    {
        return $this->withToken($this->staffToken)
            ->patchJson("/api/cms/check-in-approvals/{$reservation->uuid}/approve", ['status' => $status])
            ->assertOk();
    }

    /** @return array{0: string, 1: string} plaintext code and hash */
    private function issue(Reservation $reservation): array
    {
        $this->decide($reservation, 'approved');
        $fresh = Reservation::findOrFail($reservation->id);

        $this->assertNotNull($fresh->digital_key_code);

        return [$fresh->digital_key_code, $fresh->digital_key_hash];
    }

    private function assertFree(string $haystack, array $secrets, string $where): void
    {
        foreach ($secrets as $secret) {
            $this->assertStringNotContainsString($secret, $haystack, "{$where} leaks a key secret");
        }
    }

    public function test_model_serialisation_hides_the_key(): void
    {
        $stay        = $this->stay();
        [$code, $hash] = $this->issue($stay);
        $model       = Reservation::findOrFail($stay->id);

        foreach ([
            'toArray'           => json_encode($model->toArray()),
            'json_encode'       => json_encode($model),
            'toJson'            => $model->toJson(),
            'attributesToArray' => json_encode($model->attributesToArray()),
        ] as $where => $serialised) {
            $this->assertFree($serialised, [$code, $hash, 'digital_key_code', 'digital_key_hash'], $where);
        }
    }

    public function test_activity_log_never_holds_the_key(): void
    {
        $stay    = $this->stay();
        $secrets = [];

        array_push($secrets, ...$this->issue($stay));

        // A direct forceFill re-save of a fresh credential.
        $model = Reservation::findOrFail($stay->id);
        $model->forceFill(['digital_key_code' => 'WXYZ-2345-6789', 'digital_key_hash' => hash('sha256', 'x')])->save();
        array_push($secrets, 'WXYZ-2345-6789', hash('sha256', 'x'));

        $this->decide($stay, 'rejected');
        array_push($secrets, ...$this->issue($stay));

        $this->withToken($this->staffToken)->deleteJson("/api/cms/reservations/{$stay->uuid}")->assertNoContent();

        $rows = DB::table('activity_log')->get();
        $this->assertNotEmpty($rows);
        foreach ($rows as $row) {
            $this->assertFree((string) $row->attribute_changes . (string) $row->properties, [...$secrets, 'digital_key_code', 'digital_key_hash'], "activity_log #{$row->id}");
        }
    }

    public function test_pushes_mirrors_and_notifications_never_hold_the_key(): void
    {
        $stay          = $this->stay();
        [$code, $hash] = $this->issue($stay);

        $this->assertNotEmpty($this->firebase->pushes);
        foreach ($this->firebase->pushes as $push) {
            $this->assertFree($push['title'] . $push['body'] . json_encode($push['data']), [$code, $hash], 'push');
        }
        foreach ($this->firebase->mirrors as $mirror) {
            $this->assertFree(json_encode($mirror), [$code, $hash], 'mirror');
        }

        $rows = DB::table('guest_notifications')->get();
        $this->assertNotEmpty($rows);
        foreach ($rows as $row) {
            $this->assertFree($row->title . $row->body . $row->data, [$code, $hash], 'guest_notifications');
        }
    }

    public function test_queued_event_payload_holds_no_code(): void
    {
        $stay          = $this->stay();
        [$code, $hash] = $this->issue($stay);

        $payload = serialize(new CheckInApproved(
            Reservation::findOrFail($stay->id),
            CheckInApproval::where('reservation_id', $stay->id)->firstOrFail(),
        ));

        $this->assertFree($payload, [$code, $hash], 'serialised CheckInApproved');
    }

    public function test_staff_responses_never_hold_the_key(): void
    {
        $stay          = $this->stay();
        $approve       = $this->decide($stay, 'approved')->getContent();
        $fresh         = Reservation::findOrFail($stay->id);
        [$code, $hash] = [$fresh->digital_key_code, $fresh->digital_key_hash];
        $this->assertNotNull($code);

        $bodies = [
            'approve PATCH'              => $approve,
            'GET cms/reservations'       => $this->withToken($this->staffToken)->getJson('/api/cms/reservations')->assertOk()->getContent(),
            'GET cms/reservations/{r}'   => $this->withToken($this->staffToken)->getJson("/api/cms/reservations/{$stay->uuid}")->assertOk()->getContent(),
            'GET cms/check-in-approvals' => $this->withToken($this->staffToken)->getJson('/api/cms/check-in-approvals')->assertOk()->getContent(),
            'GET front-desk/room-board'  => $this->withToken($this->staffToken)->getJson('/api/front-desk/room-board?date=2027-03-10')->assertOk()->getContent(),
        ];

        foreach ($bodies as $where => $body) {
            $this->assertFree($body, [$code, $hash, 'digital_key_code', 'digital_key_hash'], $where);
        }
        // The fixture really is on the board and in the lists (the scan is not vacuous).
        $this->assertStringContainsString($stay->uuid, $bodies['GET cms/reservations']);
        $this->assertStringContainsString($stay->uuid, $bodies['GET cms/check-in-approvals']);
    }

    public function test_only_the_owning_guest_sees_the_key(): void
    {
        $stay          = $this->stay();
        [$code]        = $this->issue($stay);
        $owner         = Guest::findOrFail($stay->guest_id);
        $stranger      = Guest::factory()->create();

        $this->withToken($owner->createToken('t')->plainTextToken)
            ->getJson('/api/stays/upcoming')
            ->assertOk()
            ->assertJsonPath('data.0.digital_key.code', $code);

        $body = $this->withToken($stranger->createToken('t')->plainTextToken)->getJson('/api/stays/upcoming')->assertOk()->getContent();
        $this->assertStringNotContainsString($code, $body);
    }

    public function test_postman_artifacts_never_hold_a_key(): void
    {
        $envPath        = base_path('docs/postman/carlton-api.postman_environment.json');
        $collectionPath = base_path('docs/postman/carlton-api.postman_collection.json');

        $env = json_decode(file_get_contents($envPath), true);
        $this->assertIsArray($env);
        foreach ($env['values'] ?? [] as $variable) {
            $this->assertStringNotContainsString('digital_key', strtolower((string) ($variable['key'] ?? '')));
            $this->assertDoesNotMatchRegularExpression(self::KEY_PATTERN, (string) ($variable['value'] ?? ''));
        }

        $collectionText = file_get_contents($collectionPath);
        $collection     = json_decode($collectionText, true);
        $this->assertIsArray($collection);
        foreach ($collection['variable'] ?? [] as $variable) {
            $this->assertStringNotContainsString('digital_key', strtolower((string) ($variable['key'] ?? '')));
        }

        // Script lines live as JSON strings in `exec` arrays: scan each one.
        preg_match_all('/"((?:[^"\\\\]|\\\\.)*)"/', $collectionText, $strings);
        foreach ($strings[1] as $line) {
            if (preg_match('/pm\.(environment|collectionVariables)\.set\(/', $line)) {
                $this->assertStringNotContainsString('digital_key', $line, "Postman script stores a key: {$line}");
            }
        }

        $this->assertStringNotContainsString(
            'digital_key',
            file_get_contents(app_path('Console/Commands/Postman/RefreshPostmanEnvironment.php')),
        );
    }
}
