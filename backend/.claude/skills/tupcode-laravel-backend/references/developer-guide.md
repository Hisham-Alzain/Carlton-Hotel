# Backend — Developer Guide

> **Audience:** Laravel developers joining the backend.
> **Prerequisites:** Working knowledge of Laravel 12, Eloquent, and FormRequests.
> **Goal:** After reading this, you can build a complete feature (model → migration → service → controller → routes) in under an hour and have it match the conventions used everywhere else in the codebase.

The  backend is built on a thin, opinionated base layer that handles 80% of the boilerplate for you: pagination, error handling, response envelopes, validation, localization, audit logging, and request tracing. Your job as a feature developer is to declare *what* you're building; the base layer handles *how* it's shaped on the wire.

This guide walks through everything you need.

> **The code is the truth.** This guide drifted from it once, and the drift was not
> cosmetic: it named the base controllers in the wrong namespace and prescribed
> PascalCase route verbs (`GetAll`/`GetOne`/`Create`/`Update`/`Delete`) that no
> controller in this codebase has ever used, which actively misled work on this
> project. Those claims are corrected in sections 3, 4, 5 and 12, and every place
> where an earlier version described something the code has never done is now
> marked as such rather than quietly deleted — so that a reader who remembers the
> old advice can see it was wrong, not just missing. If this guide and `app/`
> disagree again, `app/` wins; fix the guide in the same PR.

---

## Table of contents

1. [Architecture at a glance](#1-architecture-at-a-glance)
2. [Response envelope (what every client sees)](#2-response-envelope)
3. [Building a feature, end to end](#3-building-a-feature-end-to-end)
4. [Controllers](#4-controllers)
5. [Services](#5-services)
6. [Requests](#6-requests)
7. [Resources](#7-resources)
8. [Filters](#8-filters)
9. [Exceptions](#9-exceptions)
10. [Actions (non-CRUD operations)](#10-actions)
11. [Traits](#11-traits)
12. [Routing conventions](#12-routing-conventions)
13. [Localization (AR/EN)](#13-localization)
14. [Common patterns and gotchas](#14-common-patterns-and-gotchas)
15. [Database design principles](#15-database-design-principles)
16. [Cross-cutting engineering principles](#16-cross-cutting-engineering-principles)
17. [Checklist before opening a PR](#17-pr-checklist)

---

## 1. Architecture at a glance

A request flows through these layers:

```
Route → Controller → FormRequest (validation) → Service → Model
                              ↓
                          Resource (response shaping)
                              ↓
        BaseController helpers / Exception handler
                              ↓
                          JSON envelope
```

**Rules of separation** (don't violate these):

| Layer | Knows about | Does NOT know about |
|---|---|---|
| Controller | Service, Request, Resource | DB queries, business rules |
| Service | Models, other services, actions | HTTP, requests, responses |
| Action | Models, services | HTTP, requests, responses |
| Model | Other models, traits | Services, HTTP |
| Request | Validation rules only | Business logic, DB writes |
| Resource | Model fields, locale | DB queries, business rules |

The big rule: **services and actions never return HTTP responses, never throw HTTP exceptions, never read `request()` directly.** They return arrays in the shape `['data' => ..., 'code' => 200]` or throw domain exceptions.

---

## 2. Response envelope

Every response, success or error, follows one of two shapes. **You do not write this manually** — the base controller and the global exception handler do it for you.

### Success

```json
{
  "success": true,
  "message": "Success",
  "data": { ... },
  "request_id": "550e8400-e29b-41d4-a716-446655440000"
}
```

### Paginated success

```json
{
  "success": true,
  "message": "Success",
  "data": {
    "items": [ ... ],
    "meta": { "current_page": 1, "last_page": 12, "per_page": 20, "total": 231, "from": 1, "to": 20 }
  },
  "request_id": "..."
}
```

### Error

```json
{
  "success": false,
  "message": "This product is out of stock.",
  "error_code": "out_of_stock",
  "context": { "product_id": 42 },
  "request_id": "..."
}
```

### Validation error (422)

```json
{
  "success": false,
  "message": "The email field is required.",
  "error_code": "validation_failed",
  "errors": { "email": ["The email field is required."] },
  "request_id": "..."
}
```

**Clients branch on `error_code`, never on `message`.** Keep `error_code` strings stable across releases.

---

## 3. Building a feature, end to end

We'll build a `Category` feature with full CRUD. By the end you'll see exactly how the layers fit together.

### Step 1: Migration

```php
// database/migrations/xxxx_create_categories_table.php
Schema::create('categories', function (Blueprint $table) {
    $table->id();
    // Every table that a public route can name carries a uuid — routes bind on
    // it, never on the sequential id. See HasUuid in section 11.
    $table->uuid('uuid')->unique();
    $table->json('name');            // translatable: Spatie stores a locale map
    $table->string('slug')->unique();
    $table->foreignId('parent_id')->nullable()->constrained('categories')->nullOnDelete();
    $table->boolean('is_active')->default(true);
    $table->unsignedInteger('sort_order')->default(0);
    $table->timestamps();
    // Content tables are soft-deletable so an editorial delete is recoverable.
    // Read section 14's soft-delete gotcha before adding this to a table with a
    // natural-key unique index or an ON DELETE CASCADE pointing at it.
    $table->softDeletes();

    $table->index(['parent_id', 'is_active']);
});
```

### Step 2: Model

```php
// app/Models/Category.php
namespace App\Models;

use App\Traits\HasTranslations;
use App\Traits\HasUuid;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Category extends Model
{
    use HasFactory, HasUuid, HasTranslations, LogsActivity, SoftDeletes;

    protected $fillable = ['name', 'slug', 'parent_id', 'is_active', 'sort_order'];

    /** Translatable fields — Spatie stores a {locale: value} map in the column. */
    protected $translatable = ['name'];

    protected $casts = ['is_active' => 'boolean'];

    public function parent()   { return $this->belongsTo(self::class, 'parent_id'); }
    public function children() { return $this->hasMany(self::class, 'parent_id'); }

    public function scopeActive($q) { return $q->where('is_active', true); }
}
```

`HasUuid` fills `uuid` on create and makes it the route key. `HasTranslations` is a
thin wrapper over `Spatie\Translatable\HasTranslations`. `LogsActivity` wraps
Spatie's activity log with fixed options (`logFillable`, `logOnlyDirty`,
`dontLogEmptyChanges`) and takes **no** `$logName` — there is no per-model audit
scope to declare.

### Step 3: Service

```php
// app/Services/Cms/CategoryService.php
namespace App\Services\Cms;

use App\Base\BaseService;
use App\Filters\CategoryFilter;
use App\Models\Category;

class CategoryService extends BaseService
{
    protected string $model = Category::class;
    protected ?string $filter = CategoryFilter::class;
    protected array $with = ['parent'];
}
```

That's the whole service for basic CRUD. No methods needed — the base class handles it.

Two things to note about the paths, because they are easy to get wrong:

- The base classes live in **`App\Base`**, not in the layer folders. `BaseService`,
  `BaseController`, `BaseIndexController`, `BaseCRUDController`, `BaseRequest`,
  `BaseResource`, `BaseFilter` and `BaseCollection` are all `app/Base/*.php`.
- Service subfolders are named by **domain**, not by role: `app/Services/Cms`,
  `Booking`, `Auth`, `Folio`, `Payment`, `Review`, `Operations`, `Service`,
  `Events`, `Chat`, `Notification`, `Firebase`. One `CategoryService` serves the
  admin and the public controller both.

### Step 4: Filter

```php
// app/Filters/CategoryFilter.php
namespace App\Filters;

use App\Base\BaseFilter;

class CategoryFilter extends BaseFilter
{
    protected array $safeParms = [
        'slug'       => ['eq', 'like', 'in'],
        'is_active'  => ['eq'],
        'parent_id'  => ['eq'],
    ];

    /** Columns `?search=` scans. */
    protected array $searchable = ['slug'];

    /** Translatable columns, so `search`/`sort` know to look inside the locale map. */
    protected array $translatable = ['name'];
}
```

Most CMS filters extend `App\Filters\CmsContentFilter` rather than `BaseFilter`
directly — it merges in the `is_active` whitelist entry and its boolean cast, and
declares the `$sortable` set, so a content list cannot lose the published/draft
toggle by redeclaring `$safeParms`. Check for an existing intermediate before
extending `BaseFilter`.

Now `?name[like]=elec&is_active[eq]=1` works automatically.

### Step 5: Requests

```php
// app/Http/Requests/Cms/CreateCategoryRequest.php
namespace App\Http\Requests\Cms;

use App\Base\BaseRequest;

class CreateCategoryRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            // Translatable fields arrive as a locale map, not as `name_ar`.
            'name'       => ['required', 'array'],
            'name.en'    => ['required', 'string', 'max:255'],
            'name.ar'    => ['required', 'string', 'max:255'],
            'slug'       => ['required', 'string', 'max:255', 'unique:categories,slug', 'regex:/^[a-z0-9-]+$/'],
            'parent_uuid' => ['nullable', 'exists:categories,uuid'],
            'is_active'  => ['boolean'],
            'sort_order' => ['integer', 'min:0'],
        ];
    }
}
```

```php
// app/Http/Requests/Cms/UpdateCategoryRequest.php
use Illuminate\Validation\Rule;

class UpdateCategoryRequest extends BaseRequest
{
    public function rules(): array
    {
        // The route binds the model, so `->ignore()` takes the model — there is no
        // `{id}` segment to read.
        return [
            'name'        => ['sometimes', 'array'],
            'name.en'     => ['sometimes', 'string', 'max:255'],
            'name.ar'     => ['sometimes', 'string', 'max:255'],
            'slug'        => ['sometimes', 'string', 'max:255', Rule::unique('categories', 'slug')->ignore($this->route('category')), 'regex:/^[a-z0-9-]+$/'],
            'parent_uuid' => ['nullable', 'exists:categories,uuid'],
            'is_active'   => ['boolean'],
            'sort_order'  => ['integer', 'min:0'],
        ];
    }
}
```

Requests are grouped by **domain**, not by role and domain: `Http/Requests/Cms`,
`Auth`, `Staff`, `Service`, `Booking`, ... Both the admin and the public
controller for a resource share the same request classes.

`unique:` and `exists:` are resolved by `App\Validation\LiveRowPresenceVerifier`,
which excludes soft-deleted rows on any table carrying a `deleted_at`. So
`unique:categories,slug` means "unique among live categories" and
`exists:categories,uuid` refuses a trashed parent. Do **not** hand-write
`->whereNull('deleted_at')` on a rule — it is already handled in one place.

### Step 6: Resource

```php
// app/Http/Resources/Cms/CategoryResource.php
namespace App\Http\Resources\Cms;

use App\Base\BaseResource;
use Illuminate\Http\Request;

class CategoryResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            // `uuid`, never `id` — the sequential key is not part of the API.
            'uuid'       => $this->uuid,
            'name'       => $this->getTranslations('name'),
            'slug'       => $this->slug,
            'is_active'  => $this->is_active,
            'sort_order' => $this->sort_order,
            'parent'     => new self($this->whenLoaded('parent')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
```

**Translatable fields go out as the whole locale map.** `getTranslations('name')`
returns `{"en": "...", "ar": "..."}` and the client picks — this codebase does
**not** collapse a translatable field to one language based on
`Accept-Language`. That keeps a CMS edit form (which needs both languages at
once) and the public site on one resource class. `Accept-Language` still drives
`app()->getLocale()`, and therefore every `__('custom.*')` message and validation
error; a handful of places that genuinely need one string — folio line
descriptions, the polymorphic bookable label — call
`getTranslation('name', $request->getLocale())` explicitly.

> There is no `localized()` helper. Earlier drafts of this guide described one;
> it has never existed in this codebase. `BaseResource` provides exactly one
> helper, `uuid()`, and otherwise leaves `toArray()` to you.

Resources are grouped by domain, mirroring the requests: `Http/Resources/Cms`,
`Auth`, `Service`, ...

### Step 7: Controller

Write it the way all 69 controllers in this codebase are written: extend
`App\Base\BaseController`, inject the service, and name the methods
`index`/`show`/`store`/`update`/`destroy`.

```php
// app/Http/Controllers/Admin/CategoryController.php
namespace App\Http\Controllers\Admin;

use App\Base\BaseController;
use App\Http\Requests\Cms\CreateCategoryRequest;
use App\Http\Requests\Cms\UpdateCategoryRequest;
use App\Http\Resources\Cms\CategoryResource;
use App\Models\Category;
use App\Services\Cms\CategoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryController extends BaseController
{
    public function __construct(private readonly CategoryService $service) {}

    public function index(Request $request): JsonResponse
    {
        return $this->paginatedSuccess(
            $this->service->index($this->indexParams($request), perPage: $this->perPageParam($request))['data'],
            CategoryResource::class,
            $request,
        );
    }

    public function show(Category $category, Request $request): JsonResponse
    {
        $result         = $this->service->show($category);
        $result['data'] = new CategoryResource($result['data']);

        return $this->respondFromService($result, request: $request);
    }

    public function store(CreateCategoryRequest $request): JsonResponse
    {
        $result         = $this->service->store($request->validated());
        $result['data'] = new CategoryResource($result['data']);

        return $this->respondFromService($result, request: $request);
    }

    public function update(UpdateCategoryRequest $request, Category $category): JsonResponse
    {
        $result         = $this->service->update($category, $request->validated());
        $result['data'] = new CategoryResource($result['data']);

        return $this->respondFromService($result, request: $request);
    }

    public function destroy(Category $category, Request $request): JsonResponse
    {
        $this->service->destroy($category);

        return $this->success(null, 'custom.messages.deleted', 204, $request);
    }
}
```

The model arrives already resolved — route model binding on `uuid`, via
`HasUuid::getRouteKeyName()`. Neither the controller nor the service ever looks up
a record by id, and a soft-deleted record simply fails to bind, which is where the
404 on a deleted resource comes from.

`app/Base/BaseCRUDController` would collapse the five methods above to zero, and
it exists — but **nothing extends it today**. See section 4 for what it offers and
why the migration has not happened; copy the hand-written shape above until it
does, because it is what every reviewer and every neighbouring file expects.

### Step 8: Routes

Flat, one line per endpoint, with the standard verb names:

```php
// routes/api.php
Route::middleware('auth:users')->prefix('cms')->group(function () {
    // Reads and writes are separately gated, so a reviewer can see the site
    // without being able to change it. The two Sanctum guards are `users`
    // (staff) and `guests` — there is no guard literally named `sanctum`.
    Route::middleware('permission:cms.view')->group(function () {
        Route::get('/categories',            [AdminCategoryController::class, 'index']);
        Route::get('/categories/{category}', [AdminCategoryController::class, 'show']);
    });

    Route::middleware('permission:cms.edit')->group(function () {
        Route::post  ('/categories',            [AdminCategoryController::class, 'store']);
        Route::put   ('/categories/{category}', [AdminCategoryController::class, 'update']);
        Route::delete('/categories/{category}', [AdminCategoryController::class, 'destroy']);
    });
});

// Public read — no auth, active records only, a separate controller and a
// separate service method (`indexPublic`) so no query string can reach it.
Route::prefix('public')->group(function () {
    Route::get('/categories',            [ApiCategoryController::class, 'index']);
    Route::get('/categories/{category}', [ApiCategoryController::class, 'show']);
});
```

Note `{category}` — a binding parameter, not `{id}`. Controllers are imported
under an alias (`AdminCategoryController`, `ApiCategoryController`) because
`routes/api.php` holds both the admin and the public controller for most
resources.

Done. The endpoint is live.

---

## 4. Controllers

### Method names: `index` / `show` / `store` / `update` / `destroy`

Laravel's resource verbs, everywhere, no exceptions. All 69 controller classes in
`app/Http/Controllers` use them, `routes/api.php` names them, and
`BaseIndexController`/`BaseCRUDController` declare them.

> **Correction to earlier versions of this guide.** This document, and the skill
> that summarises it, used to claim routes call PascalCase methods
> (`GetAll`/`GetOne`/`Create`/`Update`/`Delete`), and to place the base
> controllers in `App\Http\Controllers`. Neither has ever been true of this
> codebase: the verbs are `index`/`show`/`store`/`update`/`destroy` and the base
> classes are in `App\Base`. The claim actively misled work on this project.
> PascalCase belongs to other TupCode backends; if you are reading this guide for
> one of those, the method names are the one thing you must check against that
> repo rather than take from here.

### Hierarchy

```
Illuminate\Routing\Controller
  └── App\Base\BaseController        (success, paginatedSuccess, respondFromService,
  │                                   perPageParam, indexParams; uses AuthorizesRequests)
        └── App\Base\BaseIndexController   (index, show — read-only)
              └── App\Base\BaseCRUDController  (+ store, update, destroy)
```

`app/Http/Controllers/Controller.php` also exists — Laravel's generated empty
stub. Nothing extends it; it is dead weight, not a third base class.

### What `BaseController` actually gives you

| Method | Signature | Purpose |
|---|---|---|
| `success()` | `(mixed $data, string $messageKey, int $code, ?Request)` | The envelope: `success`, translated `message`, `data`, `request_id`. |
| `paginatedSuccess()` | `(LengthAwarePaginator, string $resourceClass, Request)` | `data.items` + `data.meta` (`current_page`, `per_page`, `total`, `last_page`). |
| `respondFromService()` | `(array $result, string $messageKey, ?Request)` | Unwraps `['data' => …, 'code' => …]`; swaps the message key to `custom.messages.created` on 201; renders a paginator through `BaseCollection`. |
| `perPageParam()` | `(Request): ?int` | The client's `per_page`, or null when absent. The default and the ceiling belong to the service. |
| `indexParams()` | `(Request): array` | The raw query string, handed to the service's filter. Safe because whitelisting happens in `$safeParms`. |

There is no `sendResponse()`, no `sendError()`, no `transform()`. Earlier versions
of this guide named all three; none has ever existed.

### The declarative base pair — real, and unused

`BaseIndexController` declares `$resource` and an abstract `service()`, and
implements `index()` and `show()`. `BaseCRUDController` adds `store()`,
`update()` and `destroy()`, taking an injected `BaseRequest` and a route-bound
`Model`:

```php
// What a controller on the base pair looks like. Nothing in the codebase does
// this yet — see the note below.
class CategoryController extends BaseCRUDController
{
    protected ?string $resource = CategoryResource::class;

    public function __construct(private readonly CategoryService $service) {}

    protected function service(): BaseService
    {
        return $this->service;
    }
}
```

Note what is **not** there: `$createRequest` and `$updateRequest`. Earlier versions
of this guide listed both as properties to declare; neither exists. Two abstract
type-hints stand between the base pair and a live route, and both have to be
closed before anything can extend it:

- `store()` and `update()` type-hint the abstract `App\Base\BaseRequest`, and
  nothing names the concrete FormRequest a route should validate against. PHP
  forbids narrowing a parameter type in an override, and Laravel cannot resolve an
  abstract class out of the container.
- `show()`, `update()` and `destroy()` type-hint the abstract
  `Illuminate\Database\Eloquent\Model`. Implicit route-model binding needs a
  concrete model class to bind, so `{category}` would arrive unresolved.

`BaseControllerPlumbingTest` therefore exercises them by calling
`store()`/`update()`/`show()` directly, passing a concrete `BaseRequest` subclass
and an already-loaded model. The read path (`index()`) has no such problem and is
route-ready today.

> **Known outstanding task: nothing extends `BaseCRUDController` or
> `BaseIndexController`.** All 69 controllers extend `BaseController` directly and
> hand-copy the bodies — 54 of them repeat the same `index()` one-liner, 50 the
> same `paginatedSuccess()` call. The base pair was written to end that
> duplication and is covered by `tests/Unit/BaseControllerPlumbingTest.php`
> through test-only subclasses, so its `per_page` and filter plumbing is not
> shipped untested — but the migration of the real controllers has not been done,
> and the two abstract type-hints above have to be resolved first. Roughly a day
> and a half of mechanical work once they are.
> `BaseIndexController::index()` deliberately makes the same `paginatedSuccess()`
> call the hand-written controllers make, so a controller that moves onto the base
> class emits a byte-identical envelope. Until that migration happens, **write new
> controllers in the hand-written shape** (section 3, step 7): consistency with 69
> neighbours beats being the only subclass.

### Adding a custom endpoint

When CRUD isn't enough (e.g. a `toggleActive` endpoint), add a method in the same
shape as the rest:

```php
public function toggleActive(Category $category, Request $request): JsonResponse
{
    $result         = $this->service->toggleActive($category);
    $result['data'] = new CategoryResource($result['data']);

    return $this->respondFromService($result, request: $request);
}
```

Then add the matching route:

```php
Route::patch('/categories/{category}/toggle', [AdminCategoryController::class, 'toggleActive']);
```

**Never put business logic in the controller.** Controllers wire requests to
services and shape responses. That's it.

### Folder structure

```
app/Http/Controllers/
  Admin/       ← CMS + staff dashboard endpoints (33 classes)
  Api/         ← public/unauthenticated endpoints (31 classes)
  Auth/        ← login, OTP, profile (2 classes)
  Staff/       ← staff account management (3 classes)
  Controller.php ← Laravel's empty stub; unused
```

There is no `User/`, `Driver/` or `Seller/` here — those are other TupCode
products. A guest-facing authenticated endpoint lives under `Api/` with the guest
guard on its route, not in a `User/` folder.

---

## 5. Services

### Base contract

Services return arrays of shape `['data' => ..., 'code' => 200]`. They throw domain exceptions for errors. **They do not return HTTP responses.**

### Hierarchy

```php
// app/Base/BaseService.php
abstract class BaseService
{
    protected string $model;
    protected ?string $filter = null;
    protected array $with = [];
    protected int $perPage = 15;      // used when the client does not ask
    protected int $maxPerPage = 100;  // client `per_page` is clamped, not rejected

    public function index(array $params = [], ?BaseFilter $filter = null, ?int $perPage = null): array
    public function show(Model $model): array
    public function store(array $data): array                   // DB::transaction, code 201
    public function update(Model $model, array $data): array    // DB::transaction
    public function destroy(Model $model): array                // DB::transaction, code 204

    // Recycle bin — only on models using SoftDeletes.
    public function trashed(array $params = [], ?BaseFilter $filter = null, ?int $perPage = null): array
    public function restore(Model $model): array
    public function forceDestroy(Model $model): array
}
```

The verbs match the controllers' and Laravel's: `index`/`show`/`store`/`update`/
`destroy`. They take a **route-bound model**, not an id — resolving records is the
router's job, so no service throws `NotFoundException` for a missing primary key.
`show()` and `store()` call `loadMissing($this->with)` so the response is shaped
from eager-loaded relations.

Public reads are a separate method, conventionally `indexPublic(?int $perPage)`: it
takes no filter params at all, so no query string can widen a public list past
`is_active = true`.

### Adding custom service methods

```php
class CategoryService extends BaseService
{
    protected string $model = Category::class;

    public function toggleActive(Category $category): array
    {
        $category->update(['is_active' => ! $category->is_active]);

        return ['data' => $category, 'code' => 200];
    }

    public function reorder(array $orderedUuids): array
    {
        return DB::transaction(function () use ($orderedUuids) {
            foreach (array_values($orderedUuids) as $index => $uuid) {
                Category::where('uuid', $uuid)->update(['sort_order' => $index]);
            }
            return ['data' => null, 'code' => 200];
        });
    }
}
```

Custom methods are `camelCase`, like the inherited ones.

### When to call another service

Inject it through the constructor — never instantiate with `new`:

```php
public function __construct(
    protected OrderService $orders,
    protected NotificationService $notifications,
) {}

public function markPaid(Order $order): array
{
    $order = $this->orders->show($order)['data'];
    // ...
    $this->notifications->sendOrderPaid($order);
    return ['data' => $order, 'code' => 200];
}
```

### When NOT to put logic in a service

If the operation is a **verb** that doesn't naturally belong to one resource (e.g. `CreateReservation`, `SettleFolio`, `RecalculateRating`), use an **Action** instead. See [section 10](#10-actions).

---

## 6. Requests

All form requests extend `BaseRequest`. The base handles the response shape on validation failure — you only write rules.

```php
namespace App\Http\Requests\Admin\Product;

use App\Http\Requests\BaseRequest;

class CreateProductRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'name'        => ['required', 'string', 'max:255'],
            'price'       => ['required', 'numeric', 'min:0'],
            'category_id' => ['required', 'exists:categories,id'],
        ];
    }

    /** Optional: customize attribute names in error messages. */
    public function attributes(): array
    {
        return ['category_id' => __('custom.category')];
    }
}
```

### Folder convention

```
app/Http/Requests/
  Cms/
    CreatePageRequest.php
    UpdatePageRequest.php
    UpsertSiteSettingsRequest.php
  Auth/
    UpdateGuestProfileRequest.php
  Booking/  Chat/  Events/  Folio/  Notification/
  Operations/  Payment/  Review/  Service/  Staff/
```

`{Domain}/{Action}{Resource}Request.php` — **one level of domain, not role plus
domain.** Both the admin and the public controller for a resource share the same
request classes, so there is nowhere for a role segment to go. Stick to this; it
makes the project navigable.

### Authorization in requests

By default `authorize()` returns `true`. Override only for instance-level checks that depend on the resource being accessed:

```php
public function authorize(): bool
{
    $order = Order::find($this->route('id'));
    return $order && $order->user_id === auth()->id();
}
```

Role-level checks belong in route middleware, not in `authorize()`.

---

## 7. Resources

All resources extend `App\Base\BaseResource`. It is deliberately almost empty: one
`uuid()` helper, and a standing instruction never to query inside `toArray()`.
Translatable fields go out as Spatie locale maps — see section 3, step 6.

### Basic resource

```php
namespace App\Http\Resources\Cms;

use App\Base\BaseResource;
use Illuminate\Http\Request;

class ProductResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid'        => $this->uuid,
            'name'        => $this->getTranslations('name'),
            'description' => $this->getTranslations('description'),
            'price_usd'   => (float) $this->price_usd,
            'category'    => new CategoryResource($this->whenLoaded('category')),
            'images'      => $this->whenLoaded('images', fn () => MediaResource::collection($this->images)),
            'created_at'  => $this->created_at?->toIso8601String(),
        ];
    }
}
```

### Resource collections

`App\Base\BaseCollection` is the one collection class, and it exists only so
`respondFromService()` can turn a paginator into `{items, meta}`. For a list, the
controller calls `paginatedSuccess($paginator, ProductResource::class, $request)`
and the base does `ProductResource::collection(...)` for you. Write a dedicated
`ResourceCollection` only for genuine collection-level metadata.

### Common pitfalls

- **Don't query inside `toArray`.** Eager-load relationships in the service. Resources that query lead to N+1 storms.
- **Use `whenLoaded`** for any relationship — gracefully omits the field if the relation wasn't loaded, instead of triggering a lazy query.
- **Don't expose `id`.** The public identifier is `uuid`.
- **Money is `DECIMAL` in USD.** Cast and convert on read; never store a float.

---

## 8. Filters

`BaseFilter` provides declarative query-string filtering. Define which params are safe and which operators they support.

```php
namespace App\Filters;

class ProductFilter extends BaseFilter
{
    protected $safeParms = [
        'name'        => ['like'],
        'price'       => ['eq', 'gt', 'gte', 'lt', 'lte'],
        'rating'      => ['gte'],
        'category_id' => ['eq'],
        'is_active'   => ['eq'],
    ];
}
```

### Query string syntax

```
?name[like]=phone&price[gte]=100&price[lte]=500&category_id[eq]=3
```

### Adding custom filter logic

For anything beyond simple column comparisons (joins, search, JSON queries), override `apply`:

```php
class ProductFilter extends BaseFilter
{
    protected $safeParms = [ /* ... */ ];

    public function apply(Builder $query, Request $request): Builder
    {
        $conditions = $this->transform($request);
        if (!empty($conditions)) $query->where($conditions);

        if ($request->filled('search')) {
            $term = $request->input('search');
            $query->where(fn($q) =>
                $q->where('name', 'LIKE', "%{$term}%")
                  ->orWhere('description', 'LIKE', "%{$term}%")
            );
        }

        if ($request->filled('tag')) {
            $query->whereHas('tags', fn($q) => $q->where('slug', $request->tag));
        }

        return $query;
    }
}
```

`BaseService::index()` instantiates the declared `$filter` from the controller's
query params and calls `apply()` on it. Override `apply()` only for search or
joins; call `parent::apply($query)` first so the `$safeParms` whitelist, the
`$searchable`/`$translatable` search and the `$sortable` sort still run.

---

## 9. Exceptions

**Throw domain exceptions from services and actions — never return error responses from them.** The global handler converts every exception to the standard envelope.

### Available exceptions

| Class | HTTP | error_code |
|---|---|---|
| `NotFoundException` | 404 | `not_found` |
| `ForbiddenException` | 403 | `forbidden` |
| `UnauthorizedException` | 401 | `unauthorized` |
| `ValidationFailedException` | 422 | `validation_failed` |
| `ConflictException` | 409 | `conflict` |
| `BusinessRuleException` | 409 | `business_rule_violation` |
| `OutOfStockException` | 409 | `out_of_stock` |
| `InsufficientBalanceException` | 409 | `insufficient_balance` |
| `PaymentFailedException` | 402 | `payment_failed` |
| `ExternalServiceException` | 502 | `external_service_failed` |
| `TooManyRequestsException` | 429 | `too_many_requests` |

### Usage

```php
public function Checkout(int $userId, array $items): array
{
    foreach ($items as $item) {
        $product = Product::find($item['product_id']);
        if (!$product) {
            throw new NotFoundException(
                __('custom.product_not_found'),
                ['product_id' => $item['product_id']]
            );
        }

        if ($product->stock < $item['quantity']) {
            throw new OutOfStockException(
                __('custom.product_out_of_stock'),
                ['product_id' => $product->id, 'available' => $product->stock]
            );
        }
    }
    // ...
}
```

### Adding a new exception type

```php
namespace App\Exceptions;

class CartLockedException extends BaseException
{
    protected int $statusCode = 409;
    protected string $errorCode = 'cart_locked';
}
```

Add the translation key in `lang/{en,ar}/custom.php` and you're done.

---

## 10. Actions

Actions are single-purpose classes for operations that don't fit CRUD. Use them when:

- The operation is a **verb**, not a resource (`CreateReservation`, `SettleFolio`, `AssignRoom`).
- The operation spans multiple services or domains.
- A service file is approaching 300+ lines.

### Anatomy

> **There is no `BaseAction` class, and the entry method is `handle()`, not
> `execute()`.** Earlier versions of this guide sketched `extends BaseAction` with
> an `execute()` and a `$this->transaction()` helper; none of it exists. All 33
> actions in `app/Actions` are plain classes with a `handle()` method that calls
> `DB::transaction` directly. A base class would buy one wrapper method and cost
> every action a parent — if you want one, propose it; do not write against it.

```php
namespace App\Actions\Booking;

use App\Exceptions\NoAvailabilityException;
use App\Models\Reservation;
use Illuminate\Support\Facades\DB;

class CreateReservationAction
{
    public function __construct(
        protected CalculateStayPriceAction $pricing,
    ) {}

    public function handle(array $data): array
    {
        return DB::transaction(function () use ($data) {
            $room = $this->findFreeRoom($data);   // SELECT … FOR UPDATE

            if ($room === null) {
                throw new NoAvailabilityException(__('custom.errors.no_availability'), [
                    'room_type_uuid' => $data['room_type_uuid'],
                ]);
            }

            $price = $this->pricing->handle($data)['data'];

            $reservation = Reservation::create([
                'guest_id'  => $data['guest_id'],
                'total_usd' => $price,
                'status'    => 'pending',
            ]);

            // ...attach rooms with their price snapshot, fire events...

            return ['data' => $reservation, 'code' => 201];
        });
    }
}
```

The price is **snapshotted** onto the child rows inside the same transaction:
editing a `PricingRule` afterwards must not change what an existing reservation
was charged.

### Calling from a controller

```php
public function store(CreateReservationAction $action, CreateReservationRequest $request): JsonResponse
{
    $result = $action->handle([
        ...$request->validated(),
        'guest_id' => $request->user()->id,
    ]);

    $result['data'] = new ReservationResource($result['data']);

    return $this->respondFromService($result, request: $request);
}
```

### Folder convention

By domain, mirroring `app/Services`:

```
app/Actions/
  Auth/  Booking/  Chat/  Cms/  Events/  Folio/
  Notification/  Operations/  Payment/  Review/  Service/  Staff/
    {Verb}{Noun}Action.php
```

---

## 11. Traits

Available traits on models. Use them; don't reinvent.

### `HasTranslations`

A thin wrapper over `Spatie\Translatable\HasTranslations`. There is **no**
`translations` table and no custom API: the values live in the column itself as a
JSON locale map.

```php
class Product extends Model
{
    use HasTranslations;
    protected $translatable = ['name', 'description'];
}

// usage — all of it Spatie's own API:
$product->setTranslation('name', 'ar', 'هاتف');
$product->getTranslation('name', 'ar');   // → 'هاتف'
$product->getTranslations('name');        // → ['en' => 'Phone', 'ar' => 'هاتف']
$product->name;                           // → the current locale's value
$product->update(['name' => ['en' => 'Phone', 'ar' => 'هاتف']]);
```

The column is `json`. Resources hand out `getTranslations()` maps; only code that
genuinely needs one string (a folio line description, a bookable's label) calls
`getTranslation($field, $locale)`.

> `setArabic()`, `arabic()`, `whereArabic()`, `withLocale()` and `localized()` do
> not exist. Earlier versions of this guide documented all five.

### `LogsActivity`

Wraps `Spatie\Activitylog`. Options are fixed in the trait — `logFillable()`,
`logOnlyDirty()`, `dontLogEmptyChanges()` — so a model declares nothing:

```php
class Product extends Model { use LogsActivity; }
```

There is no `$logName`, no `$logExcept`, and no `ActivityLog` model of our own —
entries land in Spatie's `activity_log` table and are read through
`Spatie\Activitylog\Models\Activity`. Control what is logged by controlling
`$fillable`.

### `FileTrait`

Thin helpers over the `Storage` facade — no extension whitelisting here (that
belongs in the FormRequest's `mimes:` rule) and no collision-safe renaming beyond
what Laravel's `store()` already does:

```php
$path = $this->storeFile($uploadedFile, 'products');     // → 'products/abc123.jpg'
$url  = $this->fileUrl($path);                            // → public URL, or null
$this->deleteFile($path);
```

`Media` uses it for its `url` accessor; `MediaService` is the only writer.

### `HasUuid`

Fills `uuid` on create and makes it the route key, so clients cannot enumerate by
integer id:

```php
class Product extends Model { use HasUuid; }
// getRouteKeyName() === 'uuid'
```

### `HasReviews`

Gives a content model guest reviews plus the denormalized `rating_avg` /
`rating_count` the mobile list screens read. The aggregates are written only by
`RecalculateRatingAction` and are never mass-assigned.

### `PurgesMedia`

A record's `media` rows — and the stored files nothing else references — go when
the record is **permanently** deleted. On a soft-deletable model the hook is
`forceDeleted`, so a recoverable delete keeps its photography; on any other model
it stays on `deleting`.

### `CascadesSoftDeletes`

Carries a soft delete down the relations named in `softDeleteCascades()`, and back
up on restore. Required on any soft-deletable parent with an `ON DELETE CASCADE`
pointing at it, because the database's own cascade never fires for a soft delete.
See section 14's soft-delete gotcha.

### `MirrorsToFirestore`

Mirrors a model's writes into Firestore for the ops dashboard's live queues.

> There is no `Cacheable` trait. Earlier versions of this guide described a
> memoised `GetOne` with a TTL; no such caching layer has ever existed here. Any
> caching you add needs its own documented invalidation strategy (section 16).

---

## 12. Routing conventions

### Method names

Laravel's resource verbs, and a route parameter that binds a model:

```php
Route::get   ('/categories',            [AdminCategoryController::class, 'index']);
Route::get   ('/categories/{category}', [AdminCategoryController::class, 'show']);
Route::post  ('/categories',            [AdminCategoryController::class, 'store']);
Route::put   ('/categories/{category}', [AdminCategoryController::class, 'update']);
Route::delete('/categories/{category}', [AdminCategoryController::class, 'destroy']);
```

`{category}` binds on `uuid` (via `HasUuid::getRouteKeyName()`), so a sequential id
never appears in a URL and a soft-deleted record 404s on binding. Bind on another
column explicitly where the URL is editorial — `{journalPost:slug}`.

`routes/api.php` is a flat list of one-line route declarations, not
`->controller(...)->group(...)` blocks: `grep` for a path has to land on the file,
the verb and the controller in one line. Controllers are imported with `Admin`/
`Api` aliases (`use App\Http\Controllers\Admin\AmenityController as
AdminAmenityController;`) because both halves of a resource are declared in the
same file.

### Prefix and gate by audience

```php
// Staff CMS. Reads and writes gated separately — `permission:`, not `role:`,
// because permissions are per-account and roles are only presets.
Route::middleware('auth:users')->prefix('cms')->group(function () {
    Route::middleware('permission:cms.view')->group(function () { /* index, show */ });
    Route::middleware('permission:cms.edit')->group(function () { /* store, update, destroy */ });
});

// Guest app — a second Sanctum guard over its own provider.
Route::middleware('auth:guests')->group(function () {
    Route::get('/reservations', [ApiReservationController::class, 'index']);
});
```

### Public routes

Unauthenticated endpoints sit under the `public` prefix and are served by
controllers in `app/Http/Controllers/Api`:

```php
Route::prefix('public')->group(function () {
    Route::get('/room-types',            [ApiRoomTypeController::class, 'index']);
    Route::get('/room-types/{roomType}', [ApiRoomTypeController::class, 'show']);
});
```

A public controller calls the service's `indexPublic()`, never `index()` — the
public list must not be reachable by query string. There is no `api/v1` prefix in
this codebase; the whole file is mounted under `/api`.

---

## 13. Localization

All user-facing strings live in `lang/{en,ar}/custom.php`.

```php
// lang/en/custom.php
return [
    'Success'             => 'Success',
    'not_found'           => 'Resource not found.',
    'product_out_of_stock' => 'This product is out of stock.',
    'category'            => 'category',
];
```

```php
// lang/ar/custom.php
return [
    'Success'             => 'تم بنجاح',
    'not_found'           => 'المورد غير موجود.',
    'product_out_of_stock' => 'هذا المنتج غير متوفر حالياً.',
    'category'            => 'فئة',
];
```

### Reading locale

The current locale is set from the `Accept-Language` header (`en` or `ar`). Use `__('custom.key')` everywhere — never hardcode strings.

### Locale-aware fields on models

Models with `HasTranslations` store every locale in the column itself, as a JSON
map (Spatie). Resources return the whole map via `getTranslations('field')` and let
the client pick; `Accept-Language` drives `app()->getLocale()`, and therefore
`__('custom.*')` messages and validation errors, rather than collapsing content
fields.

---

## 14. Common patterns and gotchas

### ✅ DO

- Extend `App\Base\BaseController` and name the methods `index`/`show`/`store`/
  `update`/`destroy` — the shape all 69 controllers use. (`BaseCRUDController` is
  where this is heading; nothing extends it yet. Section 4.)
- Wrap multi-step DB operations in `DB::transaction`.
- Pass every payload through an API Resource so the API shape doesn't leak DB
  columns — and expose `uuid`, never `id`.
- Eager-load relationships in `$with` on the service.
- Let route model binding produce the 404. Throw domain exceptions for rule
  violations, not for a key that does not resolve.
- Add an index when you add a `where` clause that runs frequently.
- Read `per_page` in the controller with `perPageParam($request)` and let
  `BaseService::resolvePerPage()` apply the default and the ceiling.
- Put new exceptions in `App\Exceptions\` and add translations.

### ❌ DON'T

- Don't return `response()->json(...)` from services. Return `['data' => ..., 'code' => 200]`.
- Don't call `request()` from inside services. Pass needed values explicitly.
- Don't query inside Resources. Eager-load in the service.
- Don't use `Model::all()` in production code. Always paginate.
- Don't catch `Exception` to swallow errors. Let them bubble to the global handler.
- Don't put business logic in controllers or models. Put it in services or actions.
- Don't hardcode English strings. Use `__('custom.key')`.
- Don't invent method names. `index`/`show`/`store`/`update`/`destroy` on
  controllers and services; `camelCase` for anything extra.

### Gotcha: `unique` validation on update

```php
'slug' => ['sometimes', 'string', Rule::unique('categories', 'slug')->ignore($this->route('category'))]
```

`->ignore()` exempts the current record. Without it, every update fails
validation. Pass the route-bound model (or `$this->route('category')?->id`) —
there is no `{id}` segment to read.

### Gotcha: soft deletes, cascades and natural keys

Content models use `SoftDeletes`, which changes three things that are easy to miss:

- **`ON DELETE CASCADE` never fires.** No row is removed, so the referential
  action does not run and the children stay live. A parent whose children must
  follow it uses `App\Traits\CascadesSoftDeletes` and lists the relations in
  `softDeleteCascades()` — one entry per cascading FK. Restoring walks the same
  edges back up, restoring only children deleted at or after the parent.
- **Media purging happens on `forceDeleted`, not `deleting`.** A recoverable
  delete keeps its images so a restore comes back whole; only emptying the bin
  unlinks files. See `App\Traits\PurgesMedia`.
- **A trashed row still occupies its natural key.** The unique indexes on `slug`,
  `rooms.number` and `site_settings (group, key)` are scoped to live rows
  (partial index on sqlite/pgsql, functional key parts on mysql), and
  `App\Validation\LiveRowPresenceVerifier` scopes `unique:`/`exists:` to match.
  Both halves are required — fixing only the rule turns a 422 into a 500.

Adding `softDeletes()` to a table with either a cascading FK pointing at it or a
natural-key unique index means doing all three.

### Gotcha: `whenLoaded` vs accessing relations

```php
// BAD: triggers N+1 if 'parent' wasn't eager-loaded
'parent' => new self($this->parent),

// GOOD: gracefully omits the field if not eager-loaded
'parent' => new self($this->whenLoaded('parent')),
```

### Gotcha: filter pagination + transactions

Don't wrap `index()` in a transaction. Long-running SELECTs holding transaction state cause replica lag.

### Gotcha: timezone

All timestamps are stored in UTC. Convert to local time on the **client**, never in the API.

---

## 15. Database design principles

These apply at **schema design time** — before you write the migration.

### Normalization & structure

- **Normalize to 3NF by default.** No repeating groups, no partial or transitive dependencies.
- **Denormalize deliberately, not accidentally.** Only for measured read-performance wins (e.g. a cached `orders.total`, counter columns). Document who keeps the copy in sync.
- **One table = one entity.** No god tables mixing concepts.
- **No multi-value columns.** A comma-separated list of IDs in a string column is always wrong. Use a pivot table.
- **Junction tables for many-to-many**, each with a composite unique index (e.g. `unique(['user_id', 'product_id'])`).

### Keys & identity

- **Every table has a primary key** — auto-increment `BIGINT` internally.
- **Surrogate keys over natural keys.** Emails, phones, and slugs change; IDs don't.
- **UUIDs for anything publicly exposed** (`HasUuid`). Never leak sequential IDs to clients.
- **Foreign keys constrained at the DB level**, with explicit `ON DELETE` behavior (`cascadeOnDelete()`, `nullOnDelete()`, `restrictOnDelete()`) chosen per relationship — never defaulted.

### Data types & integrity

- **Smallest correct type.** `boolean` for flags, `DECIMAL` for money (**never `FLOAT`**), enum-like values as strings backed by a PHP enum or a lookup table.
- **`NOT NULL` by default.** Nullable only when "unknown" is a real business state, not a lazy default.
- **Constraints at the DB, not just the app.** Unique indexes, checks, FKs. The FormRequest validates; the database guarantees.
- **Timestamps everywhere.** UTC always, `timestamps()` on every table, `softDeletes()` where audit or recovery matters.
- **Charset `utf8mb4`** — mandatory for Arabic content.

### Relationships & modeling

- **Status columns + history, not boolean flags.** A `status` enum beats `is_paid` / `is_shipped` / `is_cancelled` multiplying across the table. If transitions matter, add a `{model}_status_history` table.
- **Polymorphic relations sparingly.** They break FK constraints. Reserve them for genuinely generic attachments — which is exactly why `translations`, media, and `activity_logs` are the only polymorphic tables in this codebase.
- **Self-referencing hierarchies via `parent_id`**, indexed as `(parent_id, ...)` — see the categories table. Path or nested-set models only if deep-tree queries dominate.
- **Immutable ledger tables for money movements.** Wallet balances are derived or reconciled from ledger entries — never just overwritten.

### Indexing

- **Index every FK and every frequent `WHERE` / `ORDER BY` / `JOIN` column.**
- **Composite indexes in query order** (leftmost-prefix rule): `index(['parent_id', 'is_active'])` serves both `parent_id` alone and the pair.
- **Unique indexes encode business uniqueness** — slug, phone, `(user_id, product_id)` in carts.
- **Don't over-index.** Every index slows writes. Drop unused ones.

### Scale & safety

- **Design for pagination from day one.** No table should ever need an unbounded read.
- **JSON columns only for genuinely schemaless payloads** (gateway responses, metadata). Never for anything you filter on.
- **Additive migrations.** Prefer adding columns/tables over destructive changes. Destructive changes require a backfill plan and a deploy plan.
- **Seed reference data in code.** Countries, roles, statuses live in seeders, versioned in git.

---

## 16. Cross-cutting engineering principles

Rules that don't belong to a single layer but apply everywhere.

### SOLID

The layer rules in section 1 are SOLID in practice: SRP (one class, one job), Open/Closed (extend `Base*` classes, don't modify them), Liskov (any `BaseService` child works where the base is expected), Interface Segregation (controllers depend only on the service surface they use), Dependency Inversion (constructor injection everywhere — never `new`).

### Performance & reliability

- **Queues for slow work.** Emails, FCM push, image processing, report generation — dispatch a job, never block the request.
- **Caching needs an invalidation strategy.** There is no caching layer in this codebase today; anything you add must document when and how the cache is busted.
- **Idempotency for payment and webhook endpoints.** Gateways retry. Store a processed-callback key (transaction ref) and short-circuit duplicates inside the same transaction that applies the change.
- **Rate limiting on sensitive endpoints.** OTP request/verify, login, password reset — via Laravel's `throttle` middleware.

### Security

- **Verify payment callbacks server-side.** Check the gateway signature and re-fetch the transaction status from the gateway API. Never trust the client's word (or a bare HTTP 200) that a payment succeeded.
- **`$fillable` on every model.** Mass assignment protection is not optional.
- **Secrets live in `.env`, and `.env` is never committed.** Rotate immediately if it ever is.

### Testing

- **One feature test class per endpoint group**, covering at minimum: the happy path, unauthenticated access, wrong-role access, and validation failures.
- **Tests hit the real envelope.** Assert on `success`, `error_code`, and `data` shape — the same contract the Flutter team consumes.
- **Factories for every model.** No hand-built arrays in tests.

### API stability

- **All routes versioned under `/api/v1/`.** Breaking changes mean a new version, not an edit.
- **`error_code` strings and response field names are contracts.** Removing or renaming either is a breaking change.

---

## 17. PR checklist

Before opening a PR, verify:

- [ ] Migration has indexes on every foreign key and every `where`-clause column.
- [ ] Model uses `HasTranslations` if any user-facing text needs AR/EN, and `HasUuid` if a route names it.
- [ ] Model uses `LogsActivity` if changes need an audit trail.
- [ ] If the table is soft-deletable: cascading FKs handled via `CascadesSoftDeletes`, natural-key uniques scoped to live rows (section 14's soft-delete gotcha).
- [ ] Service extends `App\Base\BaseService` and declares `$model`, `$with`, optional `$filter`.
- [ ] Controller extends `App\Base\BaseController` with `index`/`show`/`store`/`update`/`destroy`.
- [ ] Every payload goes through a Resource; the response exposes `uuid`, not `id`.
- [ ] Requests extend `App\Base\BaseRequest` and live under `Http/Requests/{Domain}/`.
- [ ] Resource extends `App\Base\BaseResource` and returns `getTranslations()` maps for translatable fields.
- [ ] All user-facing strings use `__('custom.key')`; keys exist in both `en` and `ar`.
- [ ] Multi-write operations are wrapped in `DB::transaction`.
- [ ] Errors are thrown as domain exceptions, not returned as response arrays.
- [ ] Routes are grouped by guard (`auth:users`, `auth:guests`) and gated by `permission:` middleware; public reads under the `public` prefix call `indexPublic()`.
- [ ] Route parameters bind a model (`{category}`), never an `{id}`.
- [ ] No `Model::all()`, no `request()` inside services, no business logic in controllers.
- [ ] Eager loads cover every relationship used in the Resource.
- [ ] If you added a new exception type, the translation key exists.
- [ ] If you added a new error code, the Flutter team has been notified.
- [ ] Schema follows section 15: 3NF unless documented, no multi-value columns, FKs with explicit `ON DELETE`, `NOT NULL` by default, `DECIMAL` for money, composite unique indexes on pivots.
- [ ] Slow work (email, push, files) is queued, not inline.
- [ ] Payment/webhook endpoints are idempotent and verify signatures server-side.
- [ ] Sensitive endpoints (OTP, login) have `throttle` middleware.
- [ ] Feature tests cover happy path + unauthenticated + wrong role + validation failure, asserting on the envelope.

---

## Reference: file layout

**Every base class is in `app/Base`** — not scattered through the layer folders.
That is the single most common thing to get wrong when importing.

```
app/
  Base/                            ← ALL the base classes live here.
    │                                Note: there is no BaseAction — actions are
    │                                plain classes with a handle() method.
    BaseController.php             ← success, paginatedSuccess, respondFromService,
    │                                perPageParam, indexParams
    BaseIndexController.php        ← index, show   (no subclasses yet)
    BaseCRUDController.php         ← + store, update, destroy (no subclasses yet)
    BaseService.php                ← index, show, store, update, destroy,
    │                                trashed, restore, forceDestroy
    BaseRequest.php                ← authorize() + localized validation messages
    BaseResource.php               ← uuid() helper; subclasses write toArray()
    BaseFilter.php                 ← $safeParms whitelist, search, sort
    BaseCollection.php             ← paginator → {items, meta}
  Actions/
    {Domain}/{Verb}{Noun}Action.php ← plain class, handle(), DB::transaction
  Enums/
  Exceptions/
    DomainException.php            ← abstract: errorCode(), statusCode(), context()
    NotFoundException.php
    ... (one per error_code)
  Filters/
    CmsContentFilter.php           ← intermediate for content lists
    {Resource}Filter.php
  Http/
    Controllers/
      Controller.php               ← Laravel's empty stub; unused
      Admin/  Api/  Auth/  Staff/
    Middleware/
    Requests/
      {Domain}/{Action}{Resource}Request.php
    Resources/
      {Domain}/{Resource}Resource.php
  Jobs/
  Models/
    {Resource}.php
  Policies/
  Providers/
    AppServiceProvider.php         ← morph map, gates, presence-verifier override
  Services/
    {Domain}/{Resource}Service.php ← Cms, Booking, Auth, Folio, Payment, Review,
                                     Operations, Service, Events, Chat,
                                     Notification, Firebase
  Traits/
    CascadesSoftDeletes.php  FileTrait.php     HasReviews.php
    HasTranslations.php      HasUuid.php       LogsActivity.php
    MirrorsToFirestore.php   PurgesMedia.php
  Validation/
    LiveRowPresenceVerifier.php    ← unique:/exists: skip soft-deleted rows
bootstrap/
  app.php                          ← exception handler, middleware registration
  providers.php
lang/
  en/custom.php
  ar/custom.php
  es/  fr/  tr/
routes/
  api.php
```

Traits the guide has mentioned in the past that do **not** exist: `Cacheable`
(there is no memoised `show` layer), and any `Translation`/`ActivityLog` model of
our own — translations are locale maps stored in the column by Spatie, and the
activity log is Spatie's own table.

---

**Questions? Stuck? Read this guide first, then ping the backend lead.**
