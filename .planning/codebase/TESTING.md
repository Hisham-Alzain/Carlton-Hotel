# Testing Patterns

**Analysis Date:** 2026-09-25

## Test Framework

**Runner:**
- PHPUnit 12.5.12
- Config: `backend/phpunit.xml`
- Database: SQLite in-memory (`:memory:`)
- Environment: `APP_ENV=testing`

**Assertion Library:**
- PHPUnit built-in assertions
- Laravel testing helpers: `->assertOk()`, `->assertStatus()`, `->assertJsonPath()`, etc.
- Custom assertions: via `Tests\TestCase` base class

**Run Commands:**
```bash
composer test                    # Run all tests (see composer.json "test" script)
php artisan test                 # Run all tests directly
php artisan test --filter=RoomTypeTest  # Run single test class
php artisan test tests/Feature/Cms/RoomTypeTest.php  # Run specific file
php artisan test --parallel     # Run in parallel (auto-workers)
```

## Test File Organization

**Location:**
- Feature tests: `tests/Feature/{Domain}/{Resource}Test.php`
  - Example: `tests/Feature/Cms/RoomTypeTest.php`
  - Example: `tests/Feature/Booking/ReservationTest.php`
- Unit tests: `tests/Unit/{Domain}/{ActionName}Test.php`
  - Example: `tests/Unit/Booking/CreateReservationActionTest.php`
- Support: `tests/Support/` — shared test utilities (currently empty, used for helpers if needed)

**Naming:**
- Test files match resource or action name + `Test.php`
- Test methods: `test_` prefix, snake_case, descriptive
  - Example: `test_admin_can_create_room_type`
  - Example: `test_unauthenticated_cannot_access_admin_endpoints`
  - Example: `test_validation_rejects_unknown_bed_type`

**Structure:**
```
tests/Feature/
├── Cms/
│   ├── RoomTypeTest.php
│   ├── RoomTest.php
│   ├── AmenityTest.php
│   └── ...
├── Booking/
│   ├── ReservationTest.php
│   ├── AvailabilityTest.php
│   └── ...
├── Auth/
├── Payment/
├── Service/
├── ... (19 feature directories observed)
└── BaseControllerRoutingTest.php
    ExceptionEnvelopeTest.php
    GuardsResolveTest.php
tests/Unit/
├── ... (no unit tests yet observed in structure — anticipated by skill)
└── Support/
    └── (empty — for test fixtures/factories)
```

## Test Structure

**Suite Organization:**
```php
class RoomTypeTest extends TestCase {
    use RefreshDatabase;

    protected function setUp(): void {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function editorToken(): string {
        $user = User::factory()->create();
        $user->givePermissionTo('cms.edit');
        return $user->createToken('t')->plainTextToken;
    }

    private function payload(array $overrides = []): array {
        return array_merge([/* default data */], $overrides);
    }

    // ── Happy path ────────────────────
    public function test_admin_can_create_room_type(): void { ... }

    // ── Auth failure ────────────────────
    public function test_unauthenticated_cannot_access_admin_endpoints(): void { ... }

    // ── Permission failure ───────────────
    public function test_staff_without_cms_edit_cannot_access_admin_endpoints(): void { ... }

    // ── Validation failure ───────────────
    public function test_create_room_type_requires_bilingual_name(): void { ... }
}
```

**Patterns:**
- `RefreshDatabase` trait: fresh database state for every test
- `setUp()`: seed permissions/roles once, create auth tokens as needed
- Helper methods: `private function editorToken()`, `payload()` — DRY up test data
- Section comments: group related tests with `// ── Section name ────`
- One assertion focus per test (or related assertions on same response)

## Mocking

**Framework:** Mockery (`mockery/mockery` in composer.json)

**Patterns:**
```php
// ✓ Mock Firebase Admin SDK (not available in test env)
$this->mock(Firebase::class, function ($mock) {
    $mock->shouldReceive('sendMessage')
        ->once()
        ->with(\Mockery::hasKey('to'))
        ->andReturn(true);
});

// ✓ Spy on job dispatch (no queue worker)
Queue::fake();
// ... run code ...
Queue::assertDispatched(SendEmailJob::class);

// ✗ Real HTTP calls
$response = Http::get('https://real-api.example.com'); // Don't do this
```

**What to Mock:**
- External services: Firebase Admin SDK, LLM providers, Qdrant vector DB
- Queued jobs: use `Queue::fake()` and assert dispatch
- HTTP calls: never hit real endpoints
- Filesystem: use `Storage::fake('disk')` for uploads

**What NOT to Mock:**
- Eloquent models (use factories and `RefreshDatabase`)
- Database queries (test against real in-memory SQLite)
- Laravel facades (use their testing helpers instead: `Queue::fake()`, `Storage::fake()`)
- Business logic being tested (let it run)

## Fixtures and Factories

**Test Data:**
```php
// ✓ Model factory
$roomType = RoomType::factory()->create([
    'name' => ['en' => 'Suite', 'ar' => 'جناح'],
]);

// ✓ Factory with relations
$reservation = Reservation::factory()
    ->for($room)
    ->for($guest)
    ->create(['check_in' => '2024-01-15']);

// ✓ Multiple records
$amenities = Amenity::factory()->count(6)->create();

// ✗ Hardcoded test data (unmaintainable)
$roomType = new RoomType();
$roomType->name = 'Suite';
$roomType->save();
```

**Location:**
- Factories: `database/factories/{Resource}Factory.php`
- Seeder: `database/seeders/RolesAndPermissionsSeeder.php` — roles/permissions setup

**Coverage:**
- Every model has a factory (`RoomType`, `Room`, `Amenity`, `User`, `Reservation`, etc.)
- Factories use realistic data (bilingual names, valid UUIDs, proper timestamps)

## Coverage

**Requirements:** None enforced (no badge or CI gate)

**Observed Coverage:**
- Feature tests: ~95% — happy path, auth failure, permission failure, validation failure per endpoint
- Unit tests: Minimal — mainly feature-test focused
- Critical paths (booking, payment, auth): 100% — P1 and P2 modules from test-discipline skill
- View: NEVER IN TESTS (Laravel API only)

**View Coverage:**
```bash
php artisan test --coverage --coverage-html=coverage
# Opens coverage/index.html in browser
```

## Test Types

**Feature Tests:**
- **Scope:** Full HTTP stack, route through controller to service
- **Approach:** `withToken()` for auth, `getJson()/postJson()`, assert response shape
- **Cleanup:** `RefreshDatabase` handle by trait
- **One test per endpoint path** (CRUD × permission gate × validation)

**Example:**
```php
public function test_admin_can_create_room_type(): void {
    $res = $this->withToken($this->editorToken())
        ->postJson('/api/cms/room-types', $this->payload())
        ->assertStatus(201)
        ->assertJsonPath('success', true);

    $this->assertNotEmpty($res->json('data.uuid'));
}
```

**Unit Tests:**
- **Scope:** Single class in isolation (Action or complex service method)
- **Approach:** Instantiate, call method, assert return shape or exception
- **Cleanup:** `RefreshDatabase` or `DB::transaction` wrapping
- **Why:** Actions with complex logic deserve direct testing

**Example:**
```php
public function test_create_reservation_action_returns_data_and_code(): void {
    $action = new CreateReservationAction(new ReservationService(...));
    
    $result = $action->handle(
        room: $room,
        checkIn: '2024-01-15',
        checkOut: '2024-01-20'
    );

    $this->assertArrayHasKey('data', $result);
    $this->assertArrayHasKey('code', $result);
    $this->assertSame(201, $result['code']);
}
```

**E2E Tests:** Not present — feature tests serve this role

## Common Patterns

**Async Testing:**
```php
// ✓ Queue fake + assert dispatch
Queue::fake();
// ... trigger event that queues job ...
Queue::assertDispatched(SendNotificationJob::class, function ($job) {
    return $job->user->id === $user->id;
});

// ✗ Sleep for queue worker
sleep(1);
// No — queue is synchronous in testing anyway
```

**Error Testing:**
```php
// ✓ Catch domain exception
$this->expectException(NoAvailabilityException::class);
// ... code that throws ...

// ✓ Assert response error code
$this->withToken($this->editorToken())
    ->postJson('/api/bookings', $this->invalidPayload())
    ->assertStatus(422)
    ->assertJsonPath('error_code', 'validation_failed');

// ✗ Test HTTP exceptions (controller handles them, test response shape instead)
$this->withToken($token)
    ->getJson('/api/endpoint')
    ->assertStatus(401); // Test this, not the exception
```

**Concurrent/Deterministic Testing:**
```php
// ✓ BMS concurrency test (mandatory in test-discipline P4)
public function test_reservation_concurrency_only_one_succeeds(): void {
    $room = Room::factory()->create();
    $lastRoom = Room::factory()->for($room->roomType)->create();
    
    // Two simultaneous calls for the last available room
    $results = [
        $this->withToken($token1)
            ->postJson('/api/bookings', [
                'room_id' => $lastRoom->uuid,
                'check_in' => '2024-01-15',
                'check_out' => '2024-01-20'
            ]),
        $this->withToken($token2)
            ->postJson('/api/bookings', [
                'room_id' => $lastRoom->uuid,
                'check_in' => '2024-01-15',
                'check_out' => '2024-01-20'
            ])
    ];

    // One succeeds (201), one fails (409 or 422)
    $codes = array_map(fn ($r) => $r->status(), $results);
    $this->assertContains(201, $codes);
    $this->assertContains(409, $codes); // or appropriate conflict code
}
```

## Test-Driven Development Checklist

**Before declaring a feature done:**
- [ ] Feature test exists for happy path
- [ ] Test asserts response envelope shape: `success`, `data`/`error_code`, `request_id`
- [ ] Auth failure test (unauthenticated returns 401)
- [ ] Permission failure test (wrong permission returns 403)
- [ ] Validation failure test (returns 422 with `error_code: "validation_failed"`)
- [ ] `php artisan test` exits 0 — all tests pass
- [ ] No test hits real external service (Firebase, Qdrant, LLM)
- [ ] Complex actions have unit tests asserting exception throws and return shape
- [ ] Model factories exist for all models used in tests
- [ ] Factories use realistic data (bilingual, proper types)

## Assertion Patterns

**Response Shape:**
```php
// ✓ Assert envelope on success
->assertOk()
->assertJsonStructure(['success', 'message', 'data', 'request_id']);

// ✓ Assert paginated response
->assertJsonStructure(['success', 'data' => ['items', 'meta']]);

// ✓ Assert specific fields
->assertJsonPath('data.uuid', $expected->uuid)
->assertJsonPath('data.name.en', 'Updated');

// ✗ Assert just status code (weak)
->assertStatus(200); // Doesn't verify shape
```

**Database State:**
```php
// ✓ Assert model was created
$this->assertDatabaseHas('room_types', ['uuid' => $uuid]);

// ✓ Assert soft delete
$this->assertSoftDeleted('room_types', ['id' => $roomType->id]);

// ✓ Assert count
$this->assertDatabaseCount('amenities', 6);

// ✗ Query after assertion (redundant)
$this->assertDatabaseHas(...);
$roomType->refresh();
```

**Collections:**
```php
// ✓ Assert count
$this->assertCount(3, $response->json('data.items'));

// ✓ Assert structure in collection
$items = $response->json('data.items');
$this->assertSame($expected->uuid, $items[0]['uuid']);

// ✗ No assertion (test passes regardless)
$response = $this->getJson(...);
```

## Test Database

**Setup:**
- SQLite `:memory:` database (phpunit.xml `DB_DATABASE=:memory:`)
- Fresh schema for every test via `RefreshDatabase` trait
- Migrations run once, rollback/refresh via transaction per test
- `Test_TOKEN` environment variable prevents concurrent disk collisions

**Cleanup:**
- Automatic via `RefreshDatabase` — no manual cleanup needed
- Fake disks (`Storage::fake('public')`) auto-purged per `TestCase::purgeTestingDisks()`
- Database transactions rolled back at end of test

## Test Token & Auth

**Pattern:**
```php
// ✓ Create user, grant permission, make token
private function editorToken(): string {
    $user = User::factory()->create();
    $user->givePermissionTo('cms.edit');
    return $user->createToken('test')->plainTextToken;
}

// ✓ Use in request
$this->withToken($this->editorToken())
    ->postJson('/api/endpoint', [])
    ->assertOk();

// ✓ Memoize if using same token multiple times
private function $editorToken;
private function editorToken() {
    return $this->editorToken ??= ...
}
```

**Guard Resolution:**
- Two Sanctum guards: `users` (staff) and `guests` (public)
- `withToken()` resets guard state (clears memoized user) — prevents false positives on permission changes mid-test

## Example Test Patterns by Module

**CMS CRUD (RoomTypeTest pattern):**
1. Happy path CRUD — create, read, update, delete
2. Auth failures — 401 unauthenticated, 403 wrong permission
3. Validation failures — 422 with `error_code: "validation_failed"`
4. Public endpoints — only active records visible, anonymous access allowed
5. Locale switching — `Accept-Language: ar` returns Arabic fields
6. File uploads — `Storage::fake()`, assert URLs in response
7. Relations — pivot table updates, highlights selection

**Booking Concurrency (mandatory P4):**
1. Create two reservations for the last available room
2. Assert exactly one succeeds (201)
3. Assert exactly one fails (conflict error)
4. Verify database state: only one reservation in DB

**Auth Workflow (mandatory P1):**
1. OTP issuance — phone + email returned
2. OTP expiry — throws `OtpExpiredException` after timeout
3. Rate limiting — 5 attempts then `TooManyRequestsException`
4. Validation — phone normalized to E.164 format

---

*Testing analysis: 2026-09-25*
