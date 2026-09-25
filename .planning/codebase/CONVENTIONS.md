# Coding Conventions

**Analysis Date:** 2026-09-25

## Naming Patterns

**Files:**
- Controllers: `{Resource}Controller.php` — one controller per resource CRUD, named after the model
  - Example: `RoomTypeController.php`
  - Location: `app/Http/Controllers/Admin/` or `app/Http/Controllers/Api/` (no role-based subdirs)
- Services: `{Domain}/{Resource}Service.php` — one service per model
  - Example: `app/Services/Cms/RoomTypeService.php`
- Requests: `Http/Requests/{Domain}/{Action}{Resource}Request.php` — domain-based, not role-based
  - Example: `app/Http/Requests/Cms/CreateRoomTypeRequest.php`, `UpdateRoomTypeRequest.php`
- Resources: `Http/Resources/{Domain}/{Resource}Resource.php`
  - Example: `app/Http/Resources/Cms/RoomTypeResource.php`
- Filters: `Filters/{Resource}Filter.php` — one per queryable model
  - Example: `app/Filters/RoomTypeFilter.php`
- Models: `Models/{Resource}.php` — singular, PascalCase
  - Example: `app/Models/RoomType.php`
- Actions: `Actions/{Domain}/{Verb}{Resource}Action.php` — present tense verbs for operations
  - Example: `app/Actions/Booking/CreateReservationAction.php`
- Migrations: `database/migrations/YYYY_MM_DD_HHMMSS_create_table_name_table.php`
- Factories: `database/factories/{Resource}Factory.php`
- Tests: `tests/Feature/{Domain}/{Resource}Test.php` or `tests/Unit/{Domain}/{ActionName}Test.php`

**Functions and Methods:**
- Laravel resource verbs only: `index`, `show`, `store`, `update`, `destroy`, `restore`, `forceDestroy`
- Custom methods: `camelCase` — never PascalCase (e.g., `indexPublic()`, `splitAmenities()`)
- Service methods: verbs matching controllers — `index()`, `show()`, `store()`, `update()`, `destroy()`, `trashed()`, `restore()`, `forceDestroy()`
- Action methods: single entry point `handle()` returning `['data' => ..., 'code' => 200|201]`
- Scope methods: `scope` prefix in camelCase (e.g., `scopeActive()`)
- Query helpers: descriptive, no `get` prefix (e.g., `with()`, not `getWith()`)

**Variables:**
- `$data`, `$model`, `$request`, `$response` — common conventions
- `$with` — array of eager-load relations on services
- `$filter` — filter class reference on services
- `$fillable` — mass-assignable columns on models
- `$translatable` — translatable column names on models
- `$sortable` — sortable columns on filters
- Collection variables: plural, nullable prefix `$?items`, `$?users`, `$?models`

**Types:**
- Exceptions: Domain-specific, extending `App\Exceptions\DomainException`
  - Examples: `NotFoundException`, `NoAvailabilityException`, `OtpExpiredException`
  - Naming: present-tense adjectives or past-tense participles (e.g., `OutOfStockException`, not `OutOfStock`)
- Enums: for fixed value sets — location: `app/Enums/{Name}.php`
  - Examples: `RoomView::CITY`, `BedType::KING`
- Collections: plural names, use `Collection<Model>` type hints

## Code Style

**Formatting:**
- Tool: Laravel Pint (`laravel/pint` in composer.json v1.27+)
- Run: `composer run pint` (formats all PHP files)
- Style: PSR-12 with Laravel opinionations — 4-space indent, no tabs
- Line length: no hard limit enforced; prefer readable wrapping at ~120 chars
- Blank lines: one between method declarations, two between class sections

**Linting:**
- Tool: None enforced (Pint is formatter only)
- Quality: Convention enforcement via skill (`tupcode-laravel-backend`)
- PHPStan/Psalm: Not configured — rely on static analysis via development workflow

## Import Organization

**Order:**
1. Built-in Laravel namespaces (`Illuminate\*`)
2. First-party Spatie packages (`Spatie\*`)
3. First-party packages (`App\*`, `Database\*`)
4. Local (relative) imports never used — always fully qualified

**Path Aliases:**
- `App\Base\*` — base layer classes (controllers, services, requests, resources, filters)
- `App\Models\*` — all models
- `App\Services\*` — services organized by domain
- `App\Actions\*` — actions organized by domain
- `App\Filters\*` — filter classes
- `App\Enums\*` — enums
- `App\Exceptions\*` — domain exceptions
- `App\Traits\*` — shared trait implementations
- `App\Http\Controllers\Admin\*` — admin (staff) controllers
- `App\Http\Controllers\Api\*` — public controllers
- `App\Http\Requests\*` — form requests organized by domain
- `App\Http\Resources\*` — response resources organized by domain
- `Tests\*` — test classes

## Error Handling

**Patterns:**
- Throw domain exceptions (`App\Exceptions\DomainException` subclasses) on failure — never return error arrays
- Exception contract: must implement `errorCode(): string` and `statusCode(): int`
- Context payload: optional `context` array in constructor for machine-readable error details
- Global handler: `App\Exceptions\Handler` catches all domain exceptions and returns envelope
- HTTP exceptions: never thrown from services/actions — catch business logic errors, throw domain exceptions
- Validation errors: `FormRequest` throws `ValidationException` automatically — caught by handler, returns 422

**Examples:**
```php
// ✓ Throw domain exception with context
throw new NoAvailabilityException(
    __('custom.booking.no_availability'),
    ['room_id' => $room->id, 'dates' => $dates]
);

// ✗ Return error array
return ['error' => 'Out of stock', 'code' => 'out_of_stock'];

// ✗ Throw HTTP exception
throw new HttpException(400, 'Invalid input');
```

## Logging

**Framework:** Console + Syslog (Laravel default)

**Patterns:**
- Activity logging: `LogsActivity` trait on models — automatic audit trail via Spatie
- Error logging: Automatic via exception handler
- Info logging: Not commonly used — logged exceptions and activity only
- Debug logging: Via Tinker, not in code
- No structured logging libraries (no Monolog config override)

## Comments

**When to Comment:**
- Complex algorithms or non-obvious business logic
- Gotchas or potential confusion points (e.g., soft delete cascades)
- TODO/FIXME with rationale (why, not just "fix this")
- Never comment obvious code (`$x = 1; // Set x to 1`)

**JSDoc/PHPDoc:**
- Required on public methods: parameter types, return type, throws
- DocBlock examples for complex logic
- Type hints preferred over DocBlock `@param` when possible (PHP 8.3 language feature)

**Examples:**
```php
// ✓ Explains complex filtering logic
/**
 * When soft-deleting a room type, cascade through rooms and soft-delete them too.
 * Hard delete resolves through Eloquent so media is purged rather than stranded
 * by the database's own ON DELETE CASCADE.
 */
protected function softDeleteCascades(): array

// ✗ Obvious, needs no comment
foreach ($items as $item) {
    $items[] = $item; // Add item to items
}
```

## Function Design

**Size:** 
- Prefer single-responsibility: ~20–40 lines for most methods
- Helpers: extract to private methods when > 10 lines of internal helper logic
- Actions: allowed to be longer (~50–100 lines) if single operation

**Parameters:**
- Never more than 4 parameters — use value objects or spread arrays beyond that
- Route-bound models are implicit via Laravel binding, passed directly (not IDs)
- Request object never passed to services/actions — extract needed data in controller
- Type hints required (no `mixed` or omitted types)

**Return Values:**
- Controllers: `JsonResponse` from base helper methods
- Services/Actions: `['data' => ..., 'code' => 200|201]` always
- Model queries: `Builder`, `Collection`, or paginated results
- Single models: return model directly or throw `NotFoundException`

**Examples:**
```php
// ✓ Service returns array with data and status code
public function store(array $data): array {
    $model = Model::create($data);
    return ['data' => $model, 'code' => 201];
}

// ✗ Service returns response
public function store(array $data): Response {
    return response()->json($data, 201);
}

// ✓ Controller delegates to service
public function store(CreateRequest $request): JsonResponse {
    return $this->storeResponse($request);
}
```

## Module Design

**Exports:**
- Services extend `App\Base\BaseService` — declare `$model`, `$with`, optional `$filter`
- Controllers extend `App\Base\BaseCRUDController` or `BaseIndexController` — declare `$resource`, implement `service()`
- Models: public methods only, private helpers prefixed with nothing (convention is clear from context)
- Actions: plain classes, public `handle()` method, dependencies injected via constructor

**Barrel Files:**
- Not used — explicit imports from class files only
- Reason: Laravel's PSR-4 autoloading and IDE navigation prefer direct imports

## Layering Boundaries

**What each layer owns:**
- **Route layer:** Route definitions with guards and middleware
- **Controller layer:** Request parsing, permission checks via `authorize()`, delegating to service
- **FormRequest layer:** Input validation, normalization (e.g., phone number E.164)
- **Service layer:** Business logic, model interactions, transactions, eager loading
- **Action layer:** Cross-service orchestration, complex workflows, transaction wrapping
- **Model layer:** Data shape, relations, scopes, utility methods
- **Resource layer:** Response shaping, translations, conditional inclusion via `whenLoaded()`
- **Filter layer:** Query parameter operators (`eq`, `like`, `gte`, `lte`, `in`), safe parameters

**Never cross boundaries:**
- Services never read `request()`, never return HTTP responses
- Controllers never query the database directly
- Resources never trigger database queries
- Models never know about HTTP or services
- Filters never apply business logic

## Transactionality

**Pattern:**
- Every multi-step write wrapped in `DB::transaction(function () { ... })`
- Location: inside service/action, not controller
- Rollback: automatic on exception, no manual rollback needed
- Nested: Laravel supports nested transactions safely via savepoints

**Examples:**
```php
// ✓ Transaction wraps both writes
DB::transaction(function () use ($data) {
    $roomType = RoomType::create($data);
    $roomType->amenityList()->sync($amenities);
});

// ✗ No transaction (risky)
$roomType = RoomType::create($data);
$roomType->amenityList()->sync($amenities); // May fail, orphaning data
```

## Eager Loading

**Pattern:**
- Declare `protected array $with = ['relation1', 'relation2']` on service
- Load in service's `query()` method or action setup
- Resource uses `whenLoaded()` to guard optional relations
- Never query inside Resource methods

**Examples:**
```php
// ✓ Service declares eager loads
class RoomTypeService extends BaseService {
    protected array $with = ['images', 'amenityList'];
}

// ✓ Resource guards with whenLoaded
'images' => MediaResource::collection($this->whenLoaded('images'))

// ✗ Query inside resource (N+1 risk)
'amenity_count' => $this->amenityList()->count()
```

## Localization

**Pattern:**
- Every user-facing string: `__('custom.key')`
- Key presence: required in both `lang/en/custom.php` and `lang/ar/custom.php`
- Translatable model fields: `HasTranslations` trait + `$translatable` array
- Locale switching: `Accept-Language` header sets `app()->getLocale()`

**Examples:**
```php
// ✓ Localized message
throw new NotFoundException(__('custom.room_not_found'));

// ✓ Translatable field returned in full
$this->getTranslations('name') // Returns ['en' => '...', 'ar' => '...']

// ✗ Hardcoded English
throw new Exception('Room not found');
```

---

*Convention analysis: 2026-09-25*
