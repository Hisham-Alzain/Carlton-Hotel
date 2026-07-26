# Backend — Developer Guide

> **Audience:** Laravel developers joining the backend.
> **Prerequisites:** Working knowledge of Laravel 12, Eloquent, and FormRequests.
> **Goal:** After reading this, you can build a complete feature (model → migration → service → controller → routes) in under an hour and have it match the conventions used everywhere else in the codebase.

The  backend is built on a thin, opinionated base layer that handles 80% of the boilerplate for you: pagination, error handling, response envelopes, validation, localization, audit logging, and request tracing. Your job as a feature developer is to declare *what* you're building; the base layer handles *how* it's shaped on the wire.

This guide walks through everything you need.

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
                  sendResponse() / Exception handler
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
    $table->string('name');
    $table->string('slug')->unique();
    $table->foreignId('parent_id')->nullable()->constrained('categories')->nullOnDelete();
    $table->boolean('is_active')->default(true);
    $table->unsignedInteger('sort_order')->default(0);
    $table->timestamps();

    $table->index(['parent_id', 'is_active']);
});
```

### Step 2: Model

```php
// app/Models/Category.php
namespace App\Models;

use App\Traits\HasTranslations;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    use HasTranslations, LogsActivity;

    protected $fillable = ['name', 'slug', 'parent_id', 'is_active', 'sort_order'];

    /** Fields available in Arabic via the translations table. */
    protected $translatable = ['name'];

    /** Audit log scope — appears in admin filters. */
    protected static string $logName = 'categories';

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function parent()   { return $this->belongsTo(self::class, 'parent_id'); }
    public function children() { return $this->hasMany(self::class, 'parent_id'); }

    public function scopeActive($q) { return $q->where('is_active', true); }
}
```

### Step 3: Service

```php
// app/Services/Admin/CategoryService.php
namespace App\Services\Admin;

use App\Filters\CategoryFilter;
use App\Models\Category;
use App\Services\BaseService;

class CategoryService extends BaseService
{
    protected string $model = Category::class;
    protected ?string $filter = CategoryFilter::class;
    protected array $with = ['parent'];
}
```

That's the whole service for basic CRUD. No methods needed — the base class handles it.

### Step 4: Filter

```php
// app/Filters/CategoryFilter.php
namespace App\Filters;

class CategoryFilter extends BaseFilter
{
    protected $safeParms = [
        'name'       => ['like'],
        'is_active'  => ['eq'],
        'parent_id'  => ['eq'],
    ];
}
```

Now `?name[like]=elec&is_active[eq]=1` works automatically.

### Step 5: Requests

```php
// app/Http/Requests/Admin/Category/CreateCategoryRequest.php
namespace App\Http\Requests\Admin\Category;

use App\Http\Requests\BaseRequest;

class CreateCategoryRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'name'       => ['required', 'string', 'max:255'],
            'name_ar'    => ['nullable', 'string', 'max:255'],
            'slug'       => ['required', 'string', 'max:255', 'unique:categories,slug'],
            'parent_id'  => ['nullable', 'exists:categories,id'],
            'is_active'  => ['boolean'],
            'sort_order' => ['integer', 'min:0'],
        ];
    }
}
```

```php
// app/Http/Requests/Admin/Category/UpdateCategoryRequest.php
class UpdateCategoryRequest extends BaseRequest
{
    public function rules(): array
    {
        $id = $this->route('id');
        return [
            'name'       => ['sometimes', 'string', 'max:255'],
            'name_ar'    => ['nullable', 'string', 'max:255'],
            'slug'       => ['sometimes', 'string', 'max:255', "unique:categories,slug,{$id}"],
            'parent_id'  => ['nullable', 'exists:categories,id'],
            'is_active'  => ['boolean'],
            'sort_order' => ['integer', 'min:0'],
        ];
    }
}
```

### Step 6: Resource

```php
// app/Http/Resources/CategoryResource.php
namespace App\Http\Resources;

class CategoryResource extends BaseResource
{
    public function toArray($request): array
    {
        return [
            'id'         => $this->id,
            'name'       => $this->localized('name'),
            'slug'       => $this->slug,
            'parent_id'  => $this->parent_id,
            'is_active'  => $this->is_active,
            'sort_order' => $this->sort_order,
            'parent'     => new self($this->whenLoaded('parent')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
```

`localized()` reads from the translations table when locale is `ar`, otherwise returns the English column.

### Step 7: Controller

```php
// app/Http/Controllers/Admin/CategoryController.php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\BaseCRUDController;
use App\Http\Requests\Admin\Category\CreateCategoryRequest;
use App\Http\Requests\Admin\Category\UpdateCategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Services\Admin\CategoryService;

class CategoryController extends BaseCRUDController
{
    protected ?string $resource = CategoryResource::class;
    protected ?string $createRequest = CreateCategoryRequest::class;
    protected ?string $updateRequest = UpdateCategoryRequest::class;

    public function __construct(CategoryService $service)
    {
        $this->service = $service;
    }
}
```

**That's it.** Five lines of body. You get pagination, filtering, validation, localization, audit logging, request tracing, and structured error responses for free.

### Step 8: Routes

```php
// routes/api.php
Route::middleware(['auth:sanctum', 'role:admin'])->prefix('admin')->group(function () {
    Route::prefix('categories')->controller(CategoryController::class)->group(function () {
        Route::get('/',      'GetAll');
        Route::get('/{id}',  'GetOne');
        Route::post('/',     'Create');
        Route::put('/{id}',  'Update');
        Route::delete('/{id}', 'Delete');
    });
});
```

Done. The endpoint is live.

---

## 4. Controllers

### Hierarchy

```
Controller (base — sendResponse, sendError, paginatedResponse, transform)
  └── BaseIndexController  (GetAll, GetOne — read-only)
        └── BaseCRUDController  (+ Create, Update, Delete)
```

### Properties to set on a child controller

| Property | Type | Purpose |
|---|---|---|
| `$service` | `BaseService` | Injected via constructor |
| `$resource` | `?string` (FQCN) | Resource class for response shaping |
| `$createRequest` | `?string` (FQCN) | FormRequest for `Create` |
| `$updateRequest` | `?string` (FQCN) | FormRequest for `Update` |

### Adding a custom endpoint

When the base CRUD isn't enough (e.g. a `toggleActive` endpoint), add a method:

```php
public function ToggleActive($id)
{
    $result = $this->service->ToggleActive($id);
    return $this->sendResponse(
        data: $this->transform($result['data']),
        message: __('custom.Success'),
        code: $result['code'],
    );
}
```

Then add the matching route:

```php
Route::patch('/categories/{id}/toggle', [CategoryController::class, 'ToggleActive']);
```

**Never put business logic in the controller.** Controllers wire requests to services and shape responses. That's it.

### Folder structure by role

```
app/Http/Controllers/
  Admin/       ← admin dashboard endpoints
  User/        ← customer (mobile + web) endpoints
  Driver/      ← driver app endpoints
  Seller/      ← vendor portal endpoints
  Api/         ← public/unauthenticated endpoints
```

---

## 5. Services

### Base contract

Services return arrays of shape `['data' => ..., 'code' => 200]`. They throw domain exceptions for errors. **They do not return HTTP responses.**

### Hierarchy

```php
class BaseService
{
    protected string $model;
    protected ?string $filter = null;
    protected array $with = [];
    protected int $perPage = 20;

    public function GetAll() { /* paginated, filtered, eager-loaded */ }
    public function GetOne($id) { /* throws NotFoundException */ }
    public function Create($data) { /* DB::transaction */ }
    public function Update($id, $data) { /* DB::transaction */ }
    public function Delete($id) { /* DB::transaction */ }
}
```

### Adding custom service methods

```php
class CategoryService extends BaseService
{
    protected string $model = Category::class;

    public function ToggleActive($id): array
    {
        $category = $this->model::find($id);
        if (!$category) throw new NotFoundException();

        $category->update(['is_active' => !$category->is_active]);

        return ['data' => $category, 'code' => 200];
    }

    public function Reorder(array $orderedIds): array
    {
        return DB::transaction(function () use ($orderedIds) {
            foreach ($orderedIds as $index => $id) {
                $this->model::where('id', $id)->update(['sort_order' => $index]);
            }
            return ['data' => null, 'code' => 200];
        });
    }
}
```

### When to call another service

Inject it through the constructor — never instantiate with `new`:

```php
public function __construct(
    protected OrderService $orders,
    protected NotificationService $notifications,
) {}

public function MarkPaid($id): array
{
    $order = $this->orders->GetOne($id)['data'];
    // ...
    $this->notifications->sendOrderPaid($order);
    return ['data' => $order, 'code' => 200];
}
```

### When NOT to put logic in a service

If the operation is a **verb** that doesn't naturally belong to one resource (e.g. `Checkout`, `AssignDriver`, `RefundOrder`), use an **Action** instead. See [section 10](#10-actions).

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
  Admin/
    Product/
      CreateProductRequest.php
      UpdateProductRequest.php
  User/
    Order/
      CheckoutRequest.php
```

`{Role}/{Domain}/{Action}Request.php`. Stick to this — it makes the project navigable.

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

All resources extend `BaseResource`, which provides `localized()` for AR/EN fields.

### Basic resource

```php
namespace App\Http\Resources;

class ProductResource extends BaseResource
{
    public function toArray($request): array
    {
        return [
            'id'          => $this->id,
            'name'        => $this->localized('name'),
            'description' => $this->localized('description'),
            'price'       => (float) $this->price,
            'price_syp'   => $this->priceInSyp(),
            'category'    => new CategoryResource($this->whenLoaded('category')),
            'images'      => $this->whenLoaded('images', fn() => ImageResource::collection($this->images)),
            'created_at'  => $this->created_at?->toIso8601String(),
        ];
    }
}
```

### Resource collections

Only create a dedicated `ResourceCollection` class when you need custom collection-level metadata. Otherwise use `ProductResource::collection($paginator)` — the base controller's `transform()` does this automatically.

### Common pitfalls

- **Don't query inside `toArray`.** Eager-load relationships in the service. Resources that query lead to N+1 storms.
- **Use `whenLoaded`** for any relationship — gracefully omits the field if the relation wasn't loaded, instead of triggering a lazy query.
- **Currency:** values stored in USD in DB. Use a model method like `priceInSyp()` to convert on read.

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

`BaseService::GetAll` calls `apply()` when present, falling back to `transform()` otherwise.

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

- The operation is a **verb**, not a resource (`Checkout`, `AssignDriver`, `RefundOrder`).
- The operation spans multiple services or domains.
- A service file is approaching 300+ lines.

### Anatomy

```php
namespace App\Actions\Orders;

use App\Actions\BaseAction;
use App\Exceptions\OutOfStockException;
use App\Models\Order;
use App\Models\Product;

class CheckoutAction extends BaseAction
{
    public function __construct(
        protected CalculateOrderTotalAction $calculator,
    ) {}

    public function execute(array $data): array
    {
        return $this->transaction(function () use ($data) {
            $items = collect($data['items'])->map(function ($item) {
                $product = Product::find($item['product_id']);
                if ($product->stock < $item['quantity']) {
                    throw new OutOfStockException(__('custom.out_of_stock'), [
                        'product_id' => $product->id,
                    ]);
                }
                return ['product' => $product, 'quantity' => $item['quantity']];
            });

            $total = $this->calculator->execute(['items' => $items->toArray()])['data'];

            $order = Order::create([
                'user_id' => $data['user_id'],
                'total'   => $total,
                'status'  => 'pending',
            ]);

            // ...attach items, decrement stock, fire events...

            return ['data' => $order, 'code' => 201];
        });
    }
}
```

### Calling from a controller

```php
public function Checkout(CheckoutAction $action, CheckoutRequest $request)
{
    $result = $action->execute([
        ...$request->validated(),
        'user_id' => auth()->id(),
    ]);

    return $this->sendResponse(
        data: new OrderResource($result['data']),
        message: __('custom.Success'),
        code: $result['code'],
    );
}
```

### Folder convention

```
app/Actions/
  Orders/
    CheckoutAction.php
    AssignDriverAction.php
    CalculateOrderTotalAction.php
  Payments/
    RefundAction.php
```

---

## 11. Traits

Available traits on models. Use them; don't reinvent.

### `HasTranslations`

Manages AR/EN translations via a polymorphic `translations` table.

```php
class Product extends Model
{
    use HasTranslations;
    protected $translatable = ['name', 'description'];
}

// usage:
$product->setArabic('name', 'هاتف');
$product->arabic('name');               // → 'هاتف'
$product->localized('name', 'ar');      // → 'هاتف' or English fallback
$product->setArabicAll(['name' => 'هاتف', 'description' => '...']);

// query Arabic content:
Product::whereArabic('name', 'هاتف')->get();
Product::withLocale('ar')->get();       // eager-loads ar translations
```

### `LogsActivity`

Auto-logs create/update/delete to the `activity_logs` table.

```php
class Product extends Model
{
    use LogsActivity;
    protected static string $logName = 'products';
    protected static array $logExcept = ['updated_at', 'views_count'];
}
```

Audit entries are queryable through the `ActivityLog` model:

```php
ActivityLog::forModel($product)->latest()->get();
ActivityLog::forRequest($requestId)->get();   // every change made in one API call
ActivityLog::byCauser($admin)->between($from, $to)->get();
```

### `FileTrait`

Handles uploads with extension whitelisting and collision-safe naming.

```php
class ProductService extends BaseService
{
    use FileTrait;

    public function Create($data): array
    {
        if (isset($data['image'])) {
            $data['image_path'] = $this->StoreFile('public', 'products', $data['image']);
        }
        return parent::Create($data);
    }
}
```

### `HasUuid`

Adds a UUID for public-facing routes (so clients can't enumerate by integer ID):

```php
class Product extends Model { use HasUuid; }
// route key automatically becomes uuid, not id
```

### `Cacheable`

Adds memoized `GetOne` caching on services:

```php
class CategoryService extends BaseService
{
    use Cacheable;
    protected int $cacheTtl = 600;  // 10 min
}
```

---

## 12. Routing conventions

### Method names

Routes call PascalCase controller methods to match our internal style:

```php
Route::get('/', 'GetAll');
Route::get('/{id}', 'GetOne');
Route::post('/', 'Create');
Route::put('/{id}', 'Update');
Route::delete('/{id}', 'Delete');
```

### Prefix by role

```php
Route::middleware(['auth:sanctum', 'role:admin'])
    ->prefix('admin')
    ->group(function () {
        Route::prefix('categories')->controller(CategoryController::class)->group(function () {
            Route::get('/',      'GetAll');
            Route::get('/{id}',  'GetOne');
            // ...
        });
    });

Route::middleware('auth:sanctum')
    ->prefix('user')
    ->group(function () {
        Route::get('/orders', [UserOrderController::class, 'GetAll']);
        Route::post('/checkout', [UserOrderController::class, 'Checkout']);
    });
```

### Public routes

Endpoints that don't require auth go under `Api/`:

```php
Route::prefix('api/v1')->group(function () {
    Route::get('/products', [ApiProductController::class, 'GetAll']);
});
```

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

Models with `HasTranslations` store English in the main table, Arabic in the `translations` table. Resources call `$this->localized('field')` to pick the right one.

---

## 14. Common patterns and gotchas

### ✅ DO

- Inherit from `BaseCRUDController` or `BaseIndexController` for new controllers.
- Wrap multi-step DB operations in `DB::transaction`.
- Use API Resources (`$resource = ...`) so the API shape doesn't leak DB columns.
- Eager-load relationships in `$with` on the service.
- Throw `NotFoundException` instead of returning 404 manually.
- Add an index when you add a `where` clause that runs frequently.
- Use `request()->integer('per_page', 20)` for client-controlled page size.
- Put new exceptions in `App\Exceptions\` and add translations.

### ❌ DON'T

- Don't return `response()->json(...)` from services. Return `['data' => ..., 'code' => 200]`.
- Don't call `request()` from inside services. Pass needed values explicitly.
- Don't query inside Resources. Eager-load in the service.
- Don't use `Model::all()` in production code. Always paginate.
- Don't catch `Exception` to swallow errors. Let them bubble to the global handler.
- Don't put business logic in controllers or models. Put it in services or actions.
- Don't hardcode English strings. Use `__('custom.key')`.
- Don't duplicate `GetAll`/`GetOne` logic in a child controller — set `$resource` and let the base do it.

### Gotcha: `unique` validation on update

```php
'slug' => ['sometimes', 'string', "unique:categories,slug,{$id}"]
```

The `{$id}` exempts the current record. Without it, every update will fail validation.

### Gotcha: `whenLoaded` vs accessing relations

```php
// BAD: triggers N+1 if 'parent' wasn't eager-loaded
'parent' => new self($this->parent),

// GOOD: gracefully omits the field if not eager-loaded
'parent' => new self($this->whenLoaded('parent')),
```

### Gotcha: filter pagination + transactions

Don't wrap `GetAll` in a transaction. Long-running SELECTs holding transaction state cause replica lag.

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
- **Caching needs an invalidation strategy.** `Cacheable` gives memoized `GetOne` with TTL; anything beyond that must document when and how the cache is busted.
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
- [ ] Model uses `HasTranslations` if any user-facing text needs AR/EN.
- [ ] Model uses `LogsActivity` if changes need an audit trail.
- [ ] Service extends `BaseService` and declares `$model`, `$with`, optional `$filter`.
- [ ] Controller extends `BaseCRUDController` or `BaseIndexController`.
- [ ] Controller declares `$resource`, `$createRequest`, `$updateRequest` as needed.
- [ ] Requests extend `BaseRequest` and live under `Http/Requests/{Role}/{Domain}/`.
- [ ] Resource extends `BaseResource` and uses `localized()` for AR/EN fields.
- [ ] All user-facing strings use `__('custom.key')`; keys exist in both `en` and `ar`.
- [ ] Multi-write operations are wrapped in `DB::transaction`.
- [ ] Errors are thrown as domain exceptions, not returned as response arrays.
- [ ] Routes are grouped by role middleware (`admin`, `user`, `driver`, `seller`).
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

```
app/
  Actions/
    BaseAction.php
    {Domain}/{Verb}Action.php
  Exceptions/
    BaseException.php
    NotFoundException.php
    ... (one per error_code)
  Filters/
    BaseFilter.php
    {Resource}Filter.php
  Http/
    Controllers/
      Controller.php              ← sendResponse, sendError, paginatedResponse, transform
      BaseIndexController.php     ← GetAll, GetOne
      BaseCRUDController.php      ← + Create, Update, Delete
      Admin/  User/  Driver/  Seller/  Api/
    Middleware/
      AssignRequestId.php
    Requests/
      BaseRequest.php
      {Role}/{Domain}/{Action}Request.php
    Resources/
      BaseResource.php
      {Resource}Resource.php
  Models/
    ActivityLog.php
    Translation.php
    {Resource}.php
  Services/
    BaseService.php
    {Role}/{Resource}Service.php
  Traits/
    HasTranslations.php
    LogsActivity.php
    FileTrait.php
    HasUuid.php
    Cacheable.php
bootstrap/
  app.php                          ← exception handler, middleware registration
lang/
  en/custom.php
  ar/custom.php
routes/
  api.php
```

---

**Questions? Stuck? Read this guide first, then ping the backend lead.**
