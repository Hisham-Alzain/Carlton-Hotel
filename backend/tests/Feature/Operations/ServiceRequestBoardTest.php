<?php

namespace Tests\Feature\Operations;

use App\Contracts\FirebaseServiceInterface;
use App\Enums\ServiceRequestPriority;
use App\Enums\ServiceRequestStatus;
use App\Http\Resources\Operations\ServiceRequestBoardResource;
use App\Models\Guest;
use App\Models\HousekeepingTask;
use App\Models\Reservation;
use App\Models\ReservationRoom;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\ServiceItem;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Services\Operations\ServiceRequestBoardService;
use Database\Seeders\GuestServiceCatalogSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\RecordsRowLocks;
use Tests\Support\FakeFirebaseService;
use Tests\TestCase;

/** GET /api/cms/service-requests[/{serviceRequest}] — staff request board (Phase 6, SVC-01; D-15..D-17). */
class ServiceRequestBoardTest extends TestCase
{
    use RecordsRowLocks, RefreshDatabase;

    private const URL = '/api/cms/service-requests';

    private int $roomNumber = 300;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(GuestServiceCatalogSeeder::class);
        config(['hotel.timezone' => 'Asia/Damascus']);
        $this->travelTo(Carbon::parse('2027-03-12 10:00:00'));
        $this->app->instance(FirebaseServiceInterface::class, new FakeFirebaseService());
    }

    private function staffToken(string ...$permissions): string
    {
        $user = User::factory()->create();
        $user->givePermissionTo($permissions);

        return $user->createToken('t')->plainTextToken;
    }

    private function viewer(): string
    {
        return $this->staffToken('service_requests.view');
    }

    private function board(array $query = [], ?string $token = null)
    {
        return $this->withToken($token ?? $this->viewer())->getJson(self::URL . ($query ? '?' . http_build_query($query) : ''));
    }

    private function uuids($response): array
    {
        return collect($response->assertOk()->json('data.items'))->pluck('uuid')->all();
    }

    /** A stay holding one freshly-numbered room. */
    private function stayWithRoom(?string $number = null): Reservation
    {
        $reservation = Reservation::factory()->create(['check_out' => '2027-03-14']);
        $type        = RoomType::factory()->create();
        $room        = Room::factory()->create(['room_type_id' => $type->id, 'number' => $number ?? (string) ++$this->roomNumber]);

        ReservationRoom::factory()->create([
            'reservation_id' => $reservation->id,
            'room_type_id'   => $type->id,
            'room_id'        => $room->id,
        ]);

        return $reservation;
    }

    /** A catalogue request with guest, room, item, assignee and a housekeeping task. */
    private function fullRequest(array $attrs = []): ServiceRequest
    {
        $reservation = $this->stayWithRoom();
        $item        = ServiceItem::where('is_active', true)->whereHas('category', fn ($q) => $q->where('code', 'housekeeping'))->firstOrFail();

        $request = ServiceRequest::factory()->create(array_merge([
            'guest_id'         => $reservation->guest_id ?? Guest::factory(),
            'reservation_id'   => $reservation->id,
            'service_item_id'  => $item->id,
            'type'             => 'housekeeping',
            'department'       => 'housekeeping',
            'assigned_user_id' => User::factory(),
        ], $attrs));

        HousekeepingTask::factory()->request($request)->create([
            'room_id' => $reservation->rooms()->value('room_id'),
        ]);

        return $request;
    }

    public function test_requires_a_token(): void
    {
        $this->getJson(self::URL)->assertStatus(401);
    }

    public function test_view_permission_is_required(): void
    {
        $this->board([], $this->staffToken('housekeeping.view'))->assertStatus(403)->assertJsonPath('success', false);
        $this->board([], $this->staffToken('cms.edit'))->assertStatus(403);
    }

    public function test_every_preset_with_service_requests_view_can_read(): void
    {
        foreach (['kitchen', 'housekeeping', 'reception', 'concierge', 'events'] as $role) {
            $user = User::factory()->create();
            $user->assignRole($role);

            $this->board([], $user->createToken('t')->plainTextToken)->assertOk();
        }
    }

    public function test_row_shape(): void
    {
        $request = $this->fullRequest();
        $legacy  = ServiceRequest::factory()->create(['type' => 'room_service', 'service_item_id' => null, 'created_at' => now()->subHour()]);

        $items = collect($this->board()->assertOk()->assertJsonPath('success', true)->json('data.items'))->keyBy('uuid');
        $row   = $items[$request->uuid];

        $this->assertSame([
            'uuid', 'type', 'category_code', 'department', 'status', 'priority', 'notes', 'created_at', 'updated_at',
            'guest', 'reservation', 'service_item', 'assigned_user', 'housekeeping_task',
        ], array_keys($row));

        $request->load(['guest', 'reservation', 'serviceItem', 'assignedUser', 'housekeepingTask']);
        $this->assertSame('housekeeping', $row['category_code']);
        $this->assertSame(['uuid' => $request->guest->uuid, 'name' => $request->guest->name], $row['guest']);
        $this->assertSame($request->reservation->uuid, $row['reservation']['uuid']);
        $this->assertSame($request->reservation->booking_code, $row['reservation']['booking_code']);
        $this->assertSame('2027-03-14', $row['reservation']['check_out']);
        $this->assertSame((string) $this->roomNumber, $row['reservation']['room_number']);
        $this->assertSame($request->serviceItem->uuid, $row['service_item']['uuid']);
        $this->assertSame($request->serviceItem->getTranslations('name'), $row['service_item']['name']);
        $this->assertArrayHasKey('expected_minutes', $row['service_item']);
        $this->assertArrayHasKey('price_usd', $row['service_item']);
        $this->assertSame(['uuid' => $request->assignedUser->uuid, 'name' => $request->assignedUser->name], $row['assigned_user']);
        $this->assertSame(['uuid' => $request->housekeepingTask->uuid, 'status' => 'pending'], $row['housekeeping_task']);

        $this->assertNull($items[$legacy->uuid]['category_code']);
        $this->assertNull($items[$legacy->uuid]['service_item']);
        $this->assertNull($items[$legacy->uuid]['housekeeping_task']);
    }

    public function test_status_filter(): void
    {
        $new  = ServiceRequest::factory()->create(['status' => ServiceRequestStatus::NEW]);
        $done = ServiceRequest::factory()->create(['status' => ServiceRequestStatus::COMPLETED]);
        ServiceRequest::factory()->create(['status' => ServiceRequestStatus::CANCELLED]);

        $this->assertSame([$new->uuid], $this->uuids($this->board(['status' => 'new'])));
        $this->assertEqualsCanonicalizing([$new->uuid, $done->uuid], $this->uuids($this->board(['status' => ['in' => 'new,completed']])));
    }

    public function test_department_priority_and_type_filters(): void
    {
        $a = ServiceRequest::factory()->create(['department' => 'housekeeping', 'priority' => ServiceRequestPriority::HIGH, 'type' => 'laundry']);
        $b = ServiceRequest::factory()->create(['department' => 'kitchen', 'priority' => ServiceRequestPriority::LOW, 'type' => 'room_service']);

        $this->assertSame([$a->uuid], $this->uuids($this->board(['department' => 'housekeeping'])));
        $this->assertSame([$b->uuid], $this->uuids($this->board(['priority' => 'low'])));
        $this->assertSame([$a->uuid], $this->uuids($this->board(['type' => ['in' => 'laundry,spa']])));
    }

    public function test_created_at_range_filter(): void
    {
        $old = ServiceRequest::factory()->create(['created_at' => '2027-03-10 08:00:00']);
        $new = ServiceRequest::factory()->create(['created_at' => '2027-03-12 08:00:00']);

        $this->assertSame([$new->uuid], $this->uuids($this->board(['created_at' => ['gte' => '2027-03-11 00:00:00']])));
        $this->assertSame([$old->uuid], $this->uuids($this->board(['created_at' => ['lte' => '2027-03-11 00:00:00']])));
    }

    public function test_assignee_filter(): void
    {
        $user       = User::factory()->create();
        $assigned   = ServiceRequest::factory()->create(['assigned_user_id' => $user->id]);
        $unassigned = ServiceRequest::factory()->create();

        $this->assertSame([$assigned->uuid], $this->uuids($this->board(['assignee' => $user->uuid])));
        $this->assertSame([$unassigned->uuid], $this->uuids($this->board(['assignee' => 'unassigned'])));
    }

    public function test_room_filter(): void
    {
        $in = ServiceRequest::factory()->create(['reservation_id' => $this->stayWithRoom('718')->id]);
        ServiceRequest::factory()->create(['reservation_id' => $this->stayWithRoom('719')->id]);

        $this->assertSame([$in->uuid], $this->uuids($this->board(['room' => '718'])));
    }

    public function test_guest_filter(): void
    {
        $guest = Guest::factory()->create(['name' => 'Layla Haddad']);
        $mine  = ServiceRequest::factory()->create(['guest_id' => $guest->id]);
        ServiceRequest::factory()->create(['guest_id' => Guest::factory()->create(['name' => 'Omar Saleh'])->id]);

        $this->assertSame([$mine->uuid], $this->uuids($this->board(['guest' => $guest->uuid])));
        $this->assertSame([$mine->uuid], $this->uuids($this->board(['guest' => 'hADDa'])));
    }

    public function test_date_filter_uses_the_hotel_day(): void
    {
        $inside  = ServiceRequest::factory()->create(['created_at' => '2027-03-12 20:59:59']);
        ServiceRequest::factory()->create(['created_at' => '2027-03-12 21:00:00']);

        $this->assertSame([$inside->uuid], $this->uuids($this->board(['date' => '2027-03-12'])));
    }

    public function test_bad_date_and_bad_assignee_are_422(): void
    {
        $this->board(['date' => '12/03/2027'])
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'validation_failed')
            ->assertJsonValidationErrors('date');

        $this->board(['assignee' => 'nobody'])
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'validation_failed')
            ->assertJsonValidationErrors('assignee');
    }

    public function test_blank_filters_are_ignored(): void
    {
        ServiceRequest::factory()->count(2)->create();

        $this->board(['status' => '', 'assignee' => '', 'date' => '', 'room' => '', 'guest' => ''])
            ->assertOk()
            ->assertJsonCount(2, 'data.items');
    }

    public function test_empty_board(): void
    {
        $this->board()->assertOk()->assertJsonPath('data.items', [])->assertJsonPath('data.meta.total', 0);
    }

    public function test_default_order_and_priority_sort(): void
    {
        $at   = now()->subHour();
        $low  = ServiceRequest::factory()->create(['created_at' => $at, 'priority' => ServiceRequestPriority::LOW]);
        $high = ServiceRequest::factory()->create(['created_at' => $at, 'priority' => ServiceRequestPriority::HIGH]);
        $norm = ServiceRequest::factory()->create(['created_at' => $at, 'priority' => ServiceRequestPriority::NORMAL]);

        $this->assertSame([$norm->uuid, $high->uuid, $low->uuid], $this->uuids($this->board()));
        $this->assertSame([$high->uuid, $norm->uuid, $low->uuid], $this->uuids($this->board(['sort' => 'priority', 'sort_dir' => 'desc'])));
    }

    public function test_service_path_is_at_most_seven_queries(): void
    {
        for ($i = 0; $i < 15; $i++) {
            $this->fullRequest();
        }

        DB::flushQueryLog();
        DB::enableQueryLog();

        $page = app(ServiceRequestBoardService::class)->index([], null, 15)['data'];
        $rows = ServiceRequestBoardResource::collection($page->getCollection())->resolve();

        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertCount(15, $rows);
        $this->assertLessThanOrEqual(7, $queries);
        $this->assertNotNull($rows[0]['reservation']['room_number']);
        $this->assertNotNull($rows[0]['housekeeping_task']);
    }

    public function test_show_and_unknown_uuid(): void
    {
        $request = $this->fullRequest();

        $this->getJson(self::URL . '/' . $request->uuid)->assertStatus(401);
        $this->withToken($this->staffToken('housekeeping.view'))->getJson(self::URL . '/' . $request->uuid)->assertStatus(403);
        $this->app['auth']->forgetGuards();

        $this->withToken($this->viewer())->getJson(self::URL . '/' . $request->uuid)
            ->assertOk()
            ->assertJsonPath('data.uuid', $request->uuid)
            ->assertJsonPath('data.category_code', 'housekeeping')
            ->assertJsonPath('data.reservation.room_number', (string) $this->roomNumber)
            ->assertJsonPath('data.housekeeping_task.status', 'pending');

        $this->withToken($this->viewer())->getJson(self::URL . '/00000000-0000-0000-0000-000000000000')->assertStatus(404);
    }

    public function test_no_write_routes(): void
    {
        $request = ServiceRequest::factory()->create();
        $token   = $this->staffToken('service_requests.view', 'service_requests.update', 'service_requests.assign');

        foreach (['postJson', 'putJson', 'patchJson', 'deleteJson'] as $verb) {
            foreach ([self::URL, self::URL . '/' . $request->uuid] as $url) {
                // The board is read-only: every write verb is a 405 envelope.
                $this->withToken($token)->{$verb}($url, ['status' => 'completed'])
                    ->assertStatus(405)
                    ->assertJsonPath('error_code', 'method_not_allowed');
            }
        }

        $this->assertSame(ServiceRequestStatus::NEW, $request->fresh()->status);
    }

    public function test_board_rows_are_progressed_through_the_queue(): void
    {
        ServiceRequest::factory()->create();
        $uuid     = $this->board()->json('data.items.0.uuid');
        $assignee = User::factory()->withPermissions('service_requests.update')->create();

        $this->withToken($this->staffToken('service_requests.assign'))
            ->patchJson("/api/operations/queue/service-requests/{$uuid}/assign", ['user_uuid' => $assignee->uuid])
            ->assertOk();

        $this->withToken($this->staffToken('service_requests.update'))
            ->patchJson("/api/operations/queue/service-requests/{$uuid}/status", ['status' => 'in_progress'])
            ->assertOk();

        $this->board()
            ->assertJsonPath('data.items.0.assigned_user.uuid', $assignee->uuid)
            ->assertJsonPath('data.items.0.status', 'in_progress');
    }

    public function test_board_is_a_pure_read(): void
    {
        $this->fullRequest();
        $token  = $this->viewer();
        $counts = fn () => [ServiceRequest::count(), HousekeepingTask::count(), DB::table('activity_log')->count()];
        $before = $counts();

        $this->board([], $token)->assertOk();
        $this->assertSame($before, $counts());

        $locks = $this->lockedSelects(function () {
            $page = app(ServiceRequestBoardService::class)->index([], null, 15)['data'];
            ServiceRequestBoardResource::collection($page->getCollection())->resolve();
        });
        $this->assertSame([], $locks);
    }
}
