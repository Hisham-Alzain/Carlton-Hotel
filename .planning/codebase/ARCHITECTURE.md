# Architecture

**Analysis Date:** 2026-09-25

## System Overview

```text
┌─────────────────────────────────────────────────────────────────────┐
│                         HTTP / API Routes                            │
│                     `routes/api.php` (P0–P10)                        │
│  Public  |  Auth: users (staff)  |  Auth: guests  |  Admin (CMS)     │
└──────────────────────────────┬──────────────────────────────────────┘
                               │
                               ▼
┌─────────────────────────────────────────────────────────────────────┐
│                        Controllers Layer                              │
│  `app/Http/Controllers/{Api,Admin,Auth,Staff}/`                      │
│  - Validates via FormRequest, dispatches to Service/Action           │
│  - Returns JsonResponse via response envelope helpers                │
└──────────────┬──────────────────────────┬──────────────────────────┘
               │                          │
               ▼                          ▼
┌──────────────────────────────┐  ┌────────────────────────────────┐
│    Service / Action Layer    │  │   Request Validation Layer     │
│  `app/Services/`             │  │  `app/Http/Requests/`          │
│  `app/Actions/`              │  │  Only validation rules, no     │
│  - Business logic            │  │  business logic                │
│  - Transactions              │  │                                │
└──────────────┬───────────────┘  └────────────────────────────────┘
               │
               ▼
┌─────────────────────────────────────────────────────────────────────┐
│                    Resource Shaping Layer                             │
│                  `app/Http/Resources/`                               │
│                  - Outputs public fields to JSON                     │
│                  - Eager-loaded relations via whenLoaded()           │
└─────────────────────────────────────────────────────────────────────┘
               │
               ▼
┌─────────────────────────────────────────────────────────────────────┐
│                      Model & Persistence                              │
│  `app/Models/`                                                        │
│  - Eloquent models with traits (HasUuid, SoftDeletes, etc.)          │
│  - Relationships, enums, scopes                                      │
└─────────────────────────────────────────────────────────────────────┘
```

## Component Responsibilities

| Component | Responsibility | File |
|-----------|----------------|------|
| Route | Map HTTP verbs + URI to controller action. No business logic. | `routes/api.php` |
| Controller | Read `request()`, call service/action, format response envelope. | `app/Http/Controllers/{Domain}/` |
| FormRequest | Validate input via `rules()`, authorize via `authorize()`. | `app/Http/Requests/{Domain}/` |
| Service | CRUD over a single model, transaction scope. Never sees HTTP. | `app/Services/{Domain}/` |
| Action | Multi-step domain operation spanning services. Verb-named. | `app/Actions/{Domain}/` |
| Filter | Query builder filter from request params (whitelist + apply). | `app/Filters/` |
| Resource | Shape model + relations to JSON for client. | `app/Http/Resources/{Domain}/` |
| Model | Eloquent entity, relationships, scopes, casts. | `app/Models/` |
| Exception | Domain error with `error_code` and translation key. | `app/Exceptions/` |

## Pattern Overview

**Overall:** Layered MVC with explicit separation of concerns

**Key Characteristics:**
- **Route-bound models:** Controllers receive resolved models via implicit binding; services never query by ID
- **Response envelope:** All responses wrapped in `{success, message, data, request_id}`; errors converted to envelope by global exception handler
- **Domain exceptions:** Business logic throws typed exceptions (one per `error_code`); HTTP conversion happens centrally
- **Transactions:** Multi-step writes wrapped in `DB::transaction()`; pessimistic locks for inventory
- **Eager loading:** Every relation used in a Resource declared in service's `$with`; Resource guards with `whenLoaded()`
- **Soft deletes:** Models marked for deletion stay in table; `HandlesRecycleBin` trait + recycle bin routes restore or force-destroy
- **Translatable fields:** Spatie HasTranslations trait stores locale maps per column; Resources return full map via `getTranslations()`

## Layers

**HTTP Entry Point (`routes/api.php`):**
- Purpose: Map all endpoints by phase (P0–P10), guard by auth/permission middleware
- Location: `routes/api.php`
- Contains: Route groups by phase, middleware stacking, resource verb mappings
- Depends on: Controllers
- Used by: HTTP clients (Flutter, web)

**Controller Layer (`app/Http/Controllers/`):**
- Purpose: Read request, call service/action, format envelope response
- Location: `app/Http/Controllers/{Api,Admin,Auth,Staff}/`
- Contains: Controller classes extending `BaseController` or `BaseCRUDController`
- Depends on: Service, Action, FormRequest, Resource
- Used by: Routes

**Request/Validation Layer (`app/Http/Requests/`):**
- Purpose: Declare validation rules and authorization checks
- Location: `app/Http/Requests/{Domain}/`
- Contains: FormRequest classes extending `BaseRequest`
- Depends on: Validation rules only
- Used by: Controllers (implicit binding in Laravel)

**Service Layer (`app/Services/`):**
- Purpose: Business logic for CRUD operations on a single model
- Location: `app/Services/{Domain}/`
- Contains: Classes extending `BaseService` with `$model`, `$with`, `$filter`
- Depends on: Model, Filter, Action, other Services
- Used by: Controller, other Services
- Never touches: HTTP, `request()`, validation

**Action Layer (`app/Actions/`):**
- Purpose: Complex multi-domain operations (verb-named, transactional)
- Location: `app/Actions/{Domain}/`
- Contains: Plain classes with `handle()` method returning `['data' => ..., 'code' => ...]`
- Depends on: Models, Services, other Actions
- Used by: Controller, other Actions
- Never touches: HTTP, `request()`, validation

**Filter Layer (`app/Filters/`):**
- Purpose: Build WHERE/ORDER clauses from query params
- Location: `app/Filters/`
- Contains: Classes extending `BaseFilter` with `$safeParms` (whitelist) and `apply(Builder $query)`
- Depends on: Model, validation rules
- Used by: Service during `index()`

**Resource Layer (`app/Http/Resources/`):**
- Purpose: Shape model fields and relations for JSON response
- Location: `app/Http/Resources/{Domain}/`
- Contains: Classes extending `BaseResource` with `toArray()` returning field map
- Depends on: Model fields, eager-loaded relations
- Used by: Controller response methods
- Never queries: No DB access inside `toArray()`

**Model Layer (`app/Models/`):**
- Purpose: Entity definition, relationships, enums, database-facing logic
- Location: `app/Models/`
- Contains: Eloquent models with traits, relationships, scopes, casts
- Depends on: Other models via relationships
- Used by: Service, Action, Resource
- Never touches: Controllers, HTTP, business rules

## Data Flow

### Primary Request Path (CRUD Index)

1. **Route registration** (`routes/api.php` line X) — maps `GET /api/resource` to `ResourceController@index`
2. **Controller index()** (`app/Http/Controllers/Api/ResourceController`) — reads `perPageParam()` and `indexParams()` from request
3. **Service index()** (`app/Services/Resource/ResourceService::index()`) — instantiates filter, applies to query builder, paginates
4. **Filter apply()** (`app/Filters/ResourceFilter::apply()`) — builds WHERE/ORDER via `$safeParms` whitelist
5. **Query execution** — Eloquent returns paginated collection with eager loads from `$with`
6. **Controller response** (`paginatedSuccess()`) — wraps paginator in Resource collection and envelope
7. **Response** — JSON with `{success: true, data: {items: [...], meta: {...}}, request_id: ...}`

### Create/Update Path

1. **Route binding** — URI parameter (e.g., `{roomType}`) resolved to model via implicit binding
2. **FormRequest validation** — `rules()` and `authorize()` execute before controller receives model
3. **Service store/update()** — wrapped in `DB::transaction()`, calls Model::create/update, eager-loads `$with`
4. **Nested transaction** — Actions called within transaction if multi-domain (e.g., CreateReservationAction uses pessimistic lock)
5. **Model refresh** — after write, fresh load with `loadMissing($with)` to get any defaults/computed columns
6. **Controller response** — `storeResponse()` / `updateResponse()` returns envelope with resource

### Soft Delete / Recycle Bin

1. **Soft delete route** — `DELETE /api/cms/resource/{uuid}` calls `destroy()`
2. **Service destroy()** — calls `$model->delete()`, which marks `deleted_at` without DB cascade
3. **CascadesSoftDeletes trait** — carries soft delete to related records on `deleting` event
4. **PurgesMedia trait** — fires on `forceDeleted`, removes files from storage
5. **Restore route** — `POST /api/cms/resource/{uuid}/restore` calls `restore()`, needs `.withTrashed()` binding
6. **Force destroy** — `DELETE /api/cms/resource/{uuid}/force` permanently removes record

### Error Flow

1. **Exception thrown** — Service/Action throws domain exception (e.g., `NoAvailabilityException`)
2. **Global exception handler** (`app/Exceptions/Handler`) — catches by type, converts to envelope
3. **Exception attributes** — type determines `error_code`, message is translated key, context is payload
4. **Response** — `{success: false, message: __('...'), error_code: '...', data: {...}}`

**State Management:**
- Request scope: Controller receives request object, passes needed values to Service
- Model scope: Service operates on models; changes persist via `save()`/`update()`
- Transaction scope: Multi-step writes wrapped in `DB::transaction()`
- No global singletons except service container

## Key Abstractions

**BaseService:**
- Purpose: Declare `$model`, `$with` (eager loads), `$filter`, `$perPage`; inherit CRUD verbs
- Examples: `app/Services/Cms/RoomTypeService`, `app/Services/Booking/ReservationService`
- Pattern: Extend, set properties, override `query()` for custom scopes, add domain methods

**BaseCRUDController:**
- Purpose: Inherit full CRUD routing; controller declares `$resource`, `service()` method, one-liner verbs
- Examples: `app/Http/Controllers/Admin/RoomTypeController`, `app/Http/Controllers/Admin/FacilityController`
- Pattern: Extend, add `use HandlesRecycleBin;` if soft-deletable, type-hint verbs for implicit binding

**BaseFilter:**
- Purpose: Whitelist query params, apply WHERE/ORDER to builder
- Examples: `app/Filters/RoomTypeFilter`, `app/Filters/ReviewFilter`
- Pattern: Extend, set `$safeParms = ['field' => ['eq', 'like', 'gte'], ...]`, override `apply()` for custom joins

**BaseResource:**
- Purpose: Shape model fields; guard relations with `whenLoaded()`
- Examples: `app/Http/Resources/Cms/RoomTypeResource`, `app/Http/Resources/Booking/ReservationResource`
- Pattern: Extend, return array with field map, use `getTranslations()` for translatable fields, `whenLoaded()` for relations

**Action (plain class):**
- Purpose: Multi-domain operation with side effects (email, payment, lock)
- Examples: `app/Actions/Booking/CreateReservationAction`, `app/Actions/Folio/SettleFolioAction`
- Pattern: Constructor inject dependencies, `handle()` method wraps writes in `DB::transaction()`, returns envelope

## Entry Points

**Health Check:**
- Location: `routes/api.php` line 76
- Triggers: GET /health
- Responsibilities: Return status + timestamp, no auth

**Staff Authentication:**
- Location: `routes/api.php` line 86–105
- Triggers: POST /auth/login (throttled 10/min), POST /auth/logout, GET /auth/me
- Responsibilities: Validate credentials, issue token, return user profile

**Guest Authentication:**
- Location: `routes/api.php` line 108–116
- Triggers: POST /auth/guest/request-otp (throttled 10/min), POST /auth/guest/verify-otp, GET /auth/guest/me
- Responsibilities: Send OTP, validate, issue guest token, return profile

**CMS (Admin Content Management):**
- Location: `routes/api.php` line 195–484
- Triggers: GET/POST/PUT/DELETE /cms/{resource}
- Responsibilities: CRUD over content (rooms, amenities, pages, promotions, etc.), recycle bin

**Booking (Guest & Public):**
- Location: `routes/api.php` line 487–547
- Triggers: GET /public/availability, POST /reservations, GET /stays/active, etc.
- Responsibilities: Query availability, create reservation, retrieve stays, settle folio

**Operations (Staff only):**
- Location: `routes/api.php` line 550+
- Triggers: GET /operations/requests, PATCH /operations/requests/{id}/status
- Responsibilities: List service requests, assign staff, route to department

## Architectural Constraints

- **Threading:** Single-threaded event loop. Long-running tasks (email, image processing) dispatched to jobs queue.
- **Global state:** Service container via `App` facade for request instance; no module-level singletons except config.
- **Circular imports:** Avoided by service injection and interface contracts. Actions depend on Services; Services never import Actions.
- **Database scope:** Per-request connection; transactions committed at HTTP response boundary or explicit rollback on exception.
- **Soft deletes:** `whereNull('deleted_at')` applied globally via trait scope; unique indexes scoped to live rows via `LiveRowPresenceVerifier`.
- **File storage:** S3/local storage abstraction via `FileTrait` on models; media deleted on `forceDeleted` event.
- **Media lifecycle:** Attached to models via polymorphic `Media` table; cascades on model soft-delete if stored, purges on force-delete.

## Anti-Patterns

### Hand-written pagination in a controller

**What happens:** Controller calls `Model::paginate()` directly instead of service method

**Why it's wrong:** Services own the business logic (filters, eager loads, scopes); bypassing them duplicates logic across endpoints and loses filter reuse

**Do this instead:** Call `Service::index()` which handles pagination, filtering, and eager loading in one place. See `app/Http/Controllers/Admin/RoomTypeController::index()` — it inherits the verb from `BaseCRUDController` without writing any pagination code.

### Querying by ID in a service method

**What happens:** Service method does `Model::find($id)` or `where('id', $id)->first()`

**Why it's wrong:** Controllers always receive models via implicit route binding (param already resolved); querying again is redundant and loses that safety

**Do this instead:** Accept the model as a parameter. Service methods signature: `show(Model $model)`, `update(Model $model, array $data)`, etc. See `app/Services/Cms/RoomTypeService::show()` — no ID lookup.

### Business logic in a controller

**What happens:** Controller method contains conditions, loops, or calculations before calling service

**Why it's wrong:** Controllers exist to glue HTTP to business logic, not to contain business logic; it prevents reuse by jobs/commands and breaks testing isolation

**Do this instead:** Move all logic to Service or Action. Controller only reads request, validates via FormRequest, calls service, returns envelope. See `app/Http/Controllers/Admin/RoomTypeController::store()` — one line.

### Wrapping paginated query in a transaction

**What happens:** Controller wraps `index()` call in `DB::transaction()`, creating a long-running read transaction

**Why it's wrong:** Paginated reads are not atomic and don't benefit from transaction isolation; long transactions block other writers and cause replica lag

**Do this instead:** Transactions wrap only multi-step writes (store, update, delete). See `app/Services/Cms/RoomTypeService::store()` — transaction wraps create + pivot sync, not the index query.

### Returning raw Model array instead of Resource

**What happens:** Controller returns `model->toArray()` or raw JSON instead of wrapping in Resource

**Why it's wrong:** Breaks the contract for the client (missing envelope, wrong field names, unguarded relations, no translations)

**Do this instead:** Always pass model(s) through Resource. `BaseController::success()` and `paginatedSuccess()` handle wrapping; controllers never hand-write JSON. See `app/Http/Resources/Cms/RoomTypeResource::toArray()`.

## Error Handling

**Strategy:** Domain exceptions thrown by Services/Actions; caught by global handler; converted to envelope with stable `error_code`

**Patterns:**
- Throw `NoAvailabilityException` for booking conflicts (maps to error_code `booking.no_availability`)
- Throw `OutOfStockException` for service capacity (maps to error_code `service.out_of_stock`)
- Throw `BusinessRuleException` for conditional failures (maps to error_code `business.rule_violated`)
- Throw `NotFoundException` only for missing soft-deleted records (maps to error_code `entity.not_found`)
- Global handler in `app/Exceptions/Handler::render()` wraps exception in envelope with `success: false`, `error_code`, translated `message`, and optional `data` payload
- Clients branch on `error_code`, never on message text (codes are stable contract; messages can change)

## Cross-Cutting Concerns

**Logging:**
- Framework: Monolog via Laravel config
- Pattern: `LogsActivity` trait on models records all mutations to `activity_log` table; exception handler logs unhandled errors
- When: Model mutations (create, update, delete) recorded with user_id and old/new values; unexpected exceptions logged with stack trace

**Validation:**
- Framework: Laravel native + Spatie rules for soft-delete visibility
- Pattern: FormRequest::rules() declares conditions, custom rules for business logic (e.g., unique availability)
- Approach: `LiveRowPresenceVerifier` scopes uniqueness to live (non-deleted) rows; `unique()` rule on update exempts current record

**Authentication:**
- Framework: Laravel Sanctum for token-based auth (users, guests guards)
- Pattern: Two separate guards — `auth:users` for staff (session-based or token), `auth:guests` for guest tokens from OTP flow
- Approach: Middleware stacks by route; permission checks via Spatie `permission:` middleware (rows, not roles)

**Transactions:**
- Framework: `DB::transaction()` wrapper with automatic rollback on exception
- Pattern: Every multi-step write wrapped; read-only operations never wrapped
- Approach: Pessimistic locks (`lockForUpdate()`) used in Actions that compete for inventory (CreateReservationAction locks RoomType during booking)

---

*Architecture analysis: 2026-09-25*
