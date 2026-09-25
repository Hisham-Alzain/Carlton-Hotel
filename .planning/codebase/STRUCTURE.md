# Codebase Structure

**Analysis Date:** 2026-09-25

## Directory Layout

```
backend/
├── app/
│   ├── Actions/                 # Multi-domain operations (verbs)
│   │   ├── Auth/                # OTP, token, profile updates
│   │   ├── Booking/             # Reservations, availability, pricing
│   │   ├── Chat/                # Messaging
│   │   ├── Cms/                 # Content management
│   │   ├── Events/              # Event inquiries
│   │   ├── Folio/               # Bill generation, settlement
│   │   ├── Notification/        # Device tokens, alerts
│   │   ├── Operations/          # Service request routing
│   │   ├── Payment/             # Transaction recording
│   │   ├── Review/              # Rating calculations
│   │   └── Service/             # Bookings, check-in, DND
│   ├── Adapters/                # Channel adapters (Direct, WalkIn)
│   ├── Base/                    # Base classes (all 9 of them)
│   │   ├── BaseController.php   # HTTP helpers, envelope
│   │   ├── BaseCRUDController.php # CRUD verb routing
│   │   ├── BaseIndexController.php
│   │   ├── BasePublicIndexController.php
│   │   ├── BaseService.php      # CRUD methods, pagination
│   │   ├── BaseRequest.php      # Validation defaults
│   │   ├── BaseResource.php     # toArray() wrapper
│   │   ├── BaseFilter.php       # Query filter base
│   │   ├── BaseCollection.php   # Paginator wrapper
│   │   └── HandlesRecycleBin.php # restore/forceDestroy
│   ├── Console/                 # Commands
│   │   ├── Commands/Postman/    # Postman environment refresh
│   │   ├── PurgeRecycleBin.php  # Clean up bin
│   │   └── ReleaseExpiredHolds.php
│   ├── Contracts/               # Interfaces (ChannelAdapter, etc.)
│   ├── Enums/                   # Status enums (ReservationStatus, etc.)
│   │   └── Concerns/            # Traits for enum casting
│   ├── Events/                  # Laravel event classes
│   ├── Exceptions/              # Domain exceptions (one per error_code)
│   │   ├── NoAvailabilityException.php
│   │   ├── OutOfStockException.php
│   │   └── BusinessRuleException.php
│   ├── Filters/                 # Query filters (extend BaseFilter)
│   │   ├── RoomTypeFilter.php
│   │   ├── ReviewFilter.php
│   │   └── ...
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Admin/           # CMS CRUD controllers
│   │   │   │   ├── RoomTypeController.php (BaseCRUDController + HandlesRecycleBin)
│   │   │   │   ├── FacilityController.php
│   │   │   │   └── ...
│   │   │   ├── Api/             # Public + guest API controllers
│   │   │   │   ├── RoomTypeController.php (BasePublicIndexController)
│   │   │   │   ├── ReservationController.php (custom verbs)
│   │   │   │   └── ...
│   │   │   ├── Auth/            # Login, OTP, guest auth
│   │   │   │   ├── StaffAuthController.php
│   │   │   │   └── GuestAuthController.php
│   │   │   └── Staff/           # Staff management
│   │   │       ├── PermissionController.php
│   │   │       ├── RoleController.php
│   │   │       └── StaffController.php
│   │   ├── Middleware/          # Custom middleware (is_checked_in, etc.)
│   │   ├── Requests/            # FormRequest validation
│   │   │   ├── Admin/
│   │   │   ├── Auth/
│   │   │   ├── Booking/
│   │   │   ├── Cms/
│   │   │   ├── Events/
│   │   │   ├── Folio/
│   │   │   ├── Notification/
│   │   │   ├── Operations/
│   │   │   ├── Payment/
│   │   │   ├── Review/
│   │   │   ├── Service/
│   │   │   └── Staff/
│   │   └── Resources/           # Response shaping
│   │       ├── Booking/
│   │       ├── Chat/
│   │       ├── Cms/
│   │       └── ... (mirrors request structure)
│   ├── Jobs/                    # Queued tasks
│   ├── Listeners/               # Event listeners
│   ├── Models/                  # Eloquent models (50+)
│   │   ├── Room.php
│   │   ├── Reservation.php
│   │   ├── Guest.php
│   │   └── ...
│   ├── Payments/                # Payment processing logic
│   ├── Policies/                # Authorization policies (Spatie)
│   ├── Providers/               # Service providers
│   ├── Services/                # Business logic, one per domain
│   │   ├── Auth/
│   │   ├── Booking/
│   │   ├── Chat/
│   │   ├── Cms/
│   │   ├── Events/
│   │   ├── Firebase/            # Firebase messaging
│   │   ├── Folio/
│   │   ├── Notification/
│   │   ├── Operations/
│   │   ├── Payment/
│   │   ├── Review/
│   │   └── Service/
│   ├── Support/                 # Shared utilities, traits
│   ├── Traits/                  # Model traits
│   │   ├── HasUuid.php          # UUID primary key
│   │   ├── LogsActivity.php     # Activity logging
│   │   ├── PurgesMedia.php      # File cleanup on delete
│   │   ├── FileTrait.php        # File storage wrapper
│   │   ├── CascadesSoftDeletes.php # Cascade soft delete to relations
│   │   └── MirrorsToFirestore.php # Sync to Firebase
│   └── Validation/              # Custom validation rules
│
├── config/                      # Laravel config files
│
├── database/
│   ├── migrations/              # Schema changes (additive only)
│   ├── seeders/                 # Data fixtures
│   └── database.sqlite          # Dev SQLite database
│
├── lang/
│   ├── en/custom.php            # English strings (__('custom.key'))
│   └── ar/custom.php            # Arabic translations
│
├── routes/
│   ├── api.php                  # All API routes (52.9 KB), organized by P0–P10
│   ├── console.php              # Command scheduling
│   └── web.php                  # Web middleware group (unused)
│
├── storage/
│   ├── app/                     # Local file storage
│   └── logs/                    # Application logs
│
├── tests/
│   ├── Feature/                 # HTTP tests (one per endpoint group)
│   │   ├── BaseControllerRoutingTest.php
│   │   ├── PublicIndexBoundaryTest.php
│   │   └── ... (feature tests)
│   └── Unit/                    # Service/Action unit tests
│
├── .env                         # Secrets (not committed)
├── .env.example                 # Template (committed)
├── artisan                      # CLI entry point
├── composer.json                # Dependencies
└── laravel.log                  # Runtime logs
```

## Directory Purposes

**app/Base:**
- Purpose: Common base classes for all layers (9 classes total)
- Contains: BaseController, BaseCRUDController, BaseService, BaseRequest, BaseResource, BaseFilter, BaseCollection, BaseIndexController, BasePublicIndexController, HandlesRecycleBin trait
- Key files: `app/Base/BaseService.php` (CRUD template), `app/Base/BaseController.php` (envelope helpers)

**app/Actions:**
- Purpose: Complex multi-domain operations (transactional verbs)
- Contains: Plain classes with `handle()` method, constructor-injected dependencies
- Organized by: Domain (Auth, Booking, Payment, etc.), not by verb
- Key files: `app/Actions/Booking/CreateReservationAction.php` (pessimistic lock example), `app/Actions/Folio/SettleFolioAction.php`

**app/Services:**
- Purpose: Business logic for CRUD over a single model
- Contains: Classes extending BaseService, declare `$model`, `$with`, `$filter`
- Organized by: Domain (Auth, Booking, Cms, etc.)
- Key files: `app/Services/Cms/RoomTypeService.php` (standard example), `app/Services/Booking/ReservationService.php` (custom methods)

**app/Http/Controllers:**
- Purpose: HTTP request handler, calls service/action, formats response
- Organized by: Guard (Admin, Api, Auth, Staff) and domain
- Admin: CRUD + soft delete operations, restricted to staff with permissions
- Api: Public + guest endpoints, read-only or guest-scoped writes
- Auth: Login, OTP, token management
- Staff: Staff account and permission management
- Key files: `app/Http/Controllers/Admin/RoomTypeController.php` (BaseCRUDController example), `app/Http/Controllers/Api/ReservationController.php` (custom verbs)

**app/Http/Requests:**
- Purpose: FormRequest validation rules and authorization
- Contains: Rule declarations, never business logic
- Organized by: Domain folder matching Controllers and Services
- File naming: `{Action}{Resource}Request.php` (CreateRoomTypeRequest, UpdateReservationRequest)
- Key files: `app/Http/Requests/Cms/CreateRoomTypeRequest.php`

**app/Http/Resources:**
- Purpose: Shape model(s) for JSON output
- Contains: toArray() method returning field map, guards for relations
- Organized by: Domain, mirroring Requests structure
- File naming: `{Resource}Resource.php` (RoomTypeResource)
- Key files: `app/Http/Resources/Cms/RoomTypeResource.php` (translatable fields example), `app/Http/Resources/Booking/ReservationResource.php`

**app/Models:**
- Purpose: Eloquent entity definitions
- Contains: Relationships, enums, scopes, casts, fillable attributes
- Key traits: HasUuid (public key), SoftDeletes (recycle bin), LogsActivity (audit), PurgesMedia (cleanup)
- Key files: `app/Models/Reservation.php` (core booking model), `app/Models/Room.php` (simple example)

**app/Exceptions:**
- Purpose: Domain exceptions (one per error_code)
- Contains: Typed exception classes with error_code property
- Key files: `app/Exceptions/NoAvailabilityException.php`, `app/Exceptions/BusinessRuleException.php`

**app/Filters:**
- Purpose: Query builder filters from request params
- Contains: Classes extending BaseFilter with `$safeParms` whitelist
- Key files: `app/Filters/RoomTypeFilter.php` (standard example), `app/Filters/CmsContentFilter.php` (shared CMS logic)

**app/Enums:**
- Purpose: Enum definitions for status, types, etc.
- Key files: `app/Enums/ReservationStatus.php` (casted to models), `app/Enums/RoomStatus.php`

**routes/api.php:**
- Purpose: All endpoint declarations organized by phase
- Structure: P0 (health), P1 (auth), P3 (CMS), P4 (booking), P6 (events), etc.
- Key concepts:
  - Routes grouped by middleware (auth:users, auth:guests, permission:)
  - Soft-delete routes declared BEFORE the resource routes (to avoid UUID collision)
  - Public reads under `/public` prefix
  - Admin writes require `cms.edit`, reads require `cms.view|cms.edit`
- File: `routes/api.php` (52.9 KB, flat list by phase for easy scanning)

**lang/en, lang/ar:**
- Purpose: User-facing strings in translations
- Contains: Keys matching `__('custom.key')` calls throughout codebase
- File: `lang/en/custom.php`, `lang/ar/custom.php` (must match keys)

## Key File Locations

**Entry Points:**
- `routes/api.php`: All HTTP routes, organized P0–P10
- `app/Http/Controllers/Auth/StaffAuthController.php`: `/auth/login` entry point
- `app/Http/Controllers/Auth/GuestAuthController.php`: `/auth/guest/request-otp` entry point

**Configuration:**
- `.env`: Secrets (database, API keys, Firebase creds) — not committed
- `.env.example`: Template with required keys — committed
- `config/app.php`: App name, timezone, providers
- `config/database.php`: DB connection config (reads .env)
- `config/auth.php`: Guards definition (users, guests)

**Core Logic:**
- `app/Base/BaseService.php`: CRUD template (index, show, store, update, destroy)
- `app/Base/BaseController.php`: Response envelope helpers (success, paginatedSuccess, respondFromService)
- `app/Base/HandlesRecycleBin.php`: Soft-delete trait (trashed, restore, forceDestroy)
- `app/Actions/`: Complex verbs (CreateReservationAction, SettleFolioAction)
- `app/Services/`: Domain logic (one per domain folder)

**Database:**
- `database/migrations/`: Schema changes (migrations are additive only, no rollbacks in production)
- `database/seeders/`: Data fixtures (run before dev/demo)
- `database/database.sqlite`: Dev database file (ignored in production)

**Testing:**
- `tests/Feature/BaseControllerRoutingTest.php`: Tests BaseCRUDController routing
- `tests/Feature/PublicIndexBoundaryTest.php`: Tests public list authorization boundary
- `tests/Feature/`: One test class per endpoint group

## Naming Conventions

**Files:**
- Controllers: `{Resource}Controller.php` (RoomTypeController, ReservationController)
- Services: `{Resource}Service.php` (RoomTypeService, ReservationService)
- Actions: `{Verb}{Resource}Action.php` (CreateReservationAction, SettleFolioAction)
- Requests: `{Verb}{Resource}Request.php` (CreateRoomTypeRequest, UpdateReservationRequest)
- Resources: `{Resource}Resource.php` (RoomTypeResource, ReservationResource)
- Filters: `{Resource}Filter.php` (RoomTypeFilter, ReviewFilter)
- Models: `{Resource}.php` (Room, Reservation, Guest)
- Migrations: `YYYY_MM_DD_HHMMSS_action_verb.php` (2024_01_15_120000_create_rooms_table.php)
- Exceptions: `{Description}Exception.php` (NoAvailabilityException, OutOfStockException)

**Classes:**
- Controllers: PascalCase, inherits from `BaseController` or `BaseCRUDController`
- Services: PascalCase, inherits from `BaseService`
- Actions: `{Verb}{Resource}` format (CreateReservation, SettleFolio, not CreateReservationAction in the class name itself)
- Requests: `{Verb}{Resource}Request` format
- Resources: `{Resource}Resource` format
- Exceptions: `{Description}Exception` format

**Methods:**
- Controllers: Laravel resource verbs (index, show, store, update, destroy, restore, forceDestroy) — not PascalCase
- Services: CRUD verbs (index, show, store, update, destroy, trashed, restore, forceDestroy) plus domain methods in camelCase
- Actions: `handle()` entry point, private domain methods in camelCase
- Custom endpoints: camelCase (requestOtp, verifyOtp, approve, settle, routeRequest)

**Variables:**
- Model instances: singular lowerCamelCase (`$room`, `$reservation`, `$guest`)
- Collections: plural lowerCamelCase (`$rooms`, `$reservations`)
- Route parameters: kebab-case in URI, bound to camelCase variable names (`{roomType}` → `$roomType`)

**Routes:**
- Resource routes: kebab-case, plural (`/api/rooms`, `/api/reservations`, `/api/room-types`)
- Nested routes: `/{parent}/{parentId}/{child}` (e.g., `/rooms/{room}/images`)
- Custom endpoints: kebab-case, verb-prefixed (`/auth/guest/request-otp`, `/folio/approve`)
- Soft-delete routes: declared FIRST in the group (before plural resource routes) to avoid UUID collision with `{uuid}` wildcard

## Where to Add New Code

**New Feature (full endpoint):**
- Primary code: `app/Services/{Domain}/{Resource}Service.php` — extend BaseService, declare `$model`, `$with`, `$filter`
- Controller: `app/Http/Controllers/{Admin|Api}/{Resource}Controller.php` — extend BaseCRUDController (or BaseController for custom verbs)
- Validation: `app/Http/Requests/{Domain}/{Verb}{Resource}Request.php` — extend BaseRequest, declare rules()
- Response shaping: `app/Http/Resources/{Domain}/{Resource}Resource.php` — extend BaseResource, return field map
- Route: Add to `routes/api.php` in appropriate phase section, grouped by guard and permission
- Tests: `tests/Feature/{Resource}ControllerTest.php` — happy path, unauthenticated, wrong role, validation

**New Component/Module (domain):**
- Service: Create folder `app/Services/{NewDomain}/`, add `{Resource}Service.php` classes
- Controllers: Create folder `app/Http/Controllers/Admin/{NewDomain}/` and `app/Http/Controllers/Api/{NewDomain}/`
- Requests: Create folder `app/Http/Requests/{NewDomain}/`, add `*Request.php` classes
- Resources: Create folder `app/Http/Resources/{NewDomain}/`, add `*Resource.php` classes
- Actions: Create folder `app/Actions/{NewDomain}/`, add `{Verb}*Action.php` classes
- Models: Add to `app/Models/`, include traits (HasUuid, SoftDeletes, LogsActivity, etc.)
- Filters: Add to `app/Filters/`, extend BaseFilter
- Routes: Add section to `routes/api.php` with appropriate phase comment

**Utilities/Helpers:**
- Shared traits: `app/Traits/` (e.g., HasUuid, LogsActivity, FileTrait)
- Shared contracts/interfaces: `app/Contracts/` (e.g., ChannelAdapterInterface)
- Custom rules: `app/Validation/` (custom validation rule classes)
- Adapters: `app/Adapters/` (channel adapters, third-party integrations)
- Support helpers: `app/Support/` (static utility classes)

## Special Directories

**database/migrations:**
- Purpose: Schema evolution (additive only)
- Generated: Yes (created with `php artisan make:migration`)
- Committed: Yes
- Rules: Never destroy columns, never rollback in production; use soft deletes and new tables for denormalization

**database/seeders:**
- Purpose: Test data fixtures
- Generated: Manually or via factory
- Committed: Yes
- Uses: `DatabaseSeeder.php` calls domain seeders; run with `php artisan db:seed`

**storage/app:**
- Purpose: Local file storage for uploads (images, documents)
- Generated: At runtime (file uploads)
- Committed: No (git-ignored)
- Managed by: `FileTrait::storeFile()`, `PurgesMedia` on force-delete

**lang/en, lang/ar:**
- Purpose: User-facing translations
- Generated: Manual
- Committed: Yes
- Rules: Every string passed to `__('custom.key')` must have entries in BOTH lang files; error_code descriptions and validation messages included

**tests/Feature:**
- Purpose: HTTP integration tests
- Generated: Manual
- Committed: Yes
- Pattern: One test class per endpoint group, assertions on envelope structure, run via `php artisan test`

---

*Structure analysis: 2026-09-25*
