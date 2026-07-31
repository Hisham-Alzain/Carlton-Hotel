---
name: tupcode-laravel-backend
description: "TupCode's Laravel 12 backend conventions — layered architecture (Base* classes, services, actions, filters), response envelope, domain exceptions, AR/EN localization, database design rules, and the PR checklist. Use this skill whenever writing, reviewing, planning, or refactoring backend code in a TupCode Laravel project (Carlton, CartX, Offershi, VO, or any new project) — creating features, models, migrations, controllers, services, requests, resources, endpoints, or fixing backend bugs. Consult it even for small changes; a single endpoint or migration must still follow these conventions."
---

# TupCode Laravel Backend Conventions

Opinionated conventions for all TupCode Laravel 12 backends. A feature is built by *declaring* what it is; the base layer handles how it's shaped on the wire. Follow these rules exactly — consistency across projects is the point.

For full code examples, trait APIs, and the end-to-end feature walkthrough, read `references/developer-guide.md` (it has a table of contents; jump to the section you need). Read it before building any complete feature. The rules below are the contract; the guide is the implementation manual.

## Request flow and layer separation

```
Route → Controller → FormRequest (validation) → Service → Model
                              ↓
                          Resource (response shaping)
                              ↓
              BaseController helpers / global exception handler
                              ↓
                          JSON envelope
```

**All base classes live in `App\Base`** — `BaseController`, `BaseIndexController`,
`BaseCRUDController`, `BasePublicIndexController`, `BaseService`, `BaseRequest`,
`BaseResource`, `BaseFilter`, `BaseCollection`, plus the `HandlesRecycleBin`
trait. Not in the layer folders. There is no `BaseAction`.

Each layer knows only the layer below it:

| Layer | Knows about | Never touches |
|---|---|---|
| Controller | Service, Request, Resource | DB queries, business rules |
| Service | Models, other services, actions | HTTP, `request()`, responses |
| Action | Models, services | HTTP, `request()`, responses |
| Model | Other models, traits | Services, HTTP |
| Request | Validation rules only | Business logic, DB writes |
| Resource | Model fields, locale | DB queries, business rules |

The big rule: **services and actions never return HTTP responses, never throw HTTP exceptions, never read `request()`**. They return `['data' => ..., 'code' => 200]` or throw domain exceptions. The global handler converts exceptions to the envelope.

## Building a feature (declarative CRUD)

Declare what differs; the base layer does the wire shaping:

- Service extends `App\Base\BaseService`, declares `$model`, `$with` (eager loads), optional `$filter`. Verbs are `index`/`show`/`store`/`update`/`destroy`, plus `trashed`/`restore`/`forceDestroy` on soft-deletable models. They take a **route-bound model**, not an id — no service looks a record up by primary key, and none throws `NotFoundException` for a missing one. Public reads are a separate `indexPublic(?int $perPage)` that accepts no filter params, so no query string can widen a public list.
- Controller extends `App\Base\BaseCRUDController` (or `BaseIndexController` for a read-only staff list, `BasePublicIndexController` for a public one), declares `$resource`, returns its service from `service()`, and adds `use HandlesRecycleBin;` when the model is soft-deletable. `index()` and `trashed()` are inherited whole. `show`/`store`/`update`/`destroy`/`restore`/`forceDestroy` are one typed line each over the base's `showResponse()`/`storeResponse()`/`updateResponse()`/`destroyResponse()`/`restoreResponse()`/`forceDestroyResponse()` — the signature stays in the controller because implicit route-model binding and FormRequest resolution both read *it*, and PHP forbids narrowing a parameter type in an override. A controller whose verbs are not the plain service verbs stays on `App\Base\BaseController` and uses `paginatedSuccess()` / `respondFromService()` / `success(null, 'custom.messages.deleted', 204, $request)` / `perPageParam()` / `indexParams()` directly. There is no `sendResponse()`, `sendError()` or `transform()`.
- Requests extend `App\Base\BaseRequest`, live at `Http/Requests/{Domain}/{Action}{Resource}Request.php` (domain, not role+domain), contain rules only. `unique` on update uses `Rule::unique(...)->ignore($this->route('roomType'))` — the route parameter's own name (`roomType`, `journalPost`, `serviceCategory`, `user`, …), never a literal `'model'` and never an `{id}` segment.
- Resource extends `App\Base\BaseResource`, exposes `uuid` and never `id`, returns `getTranslations('field')` locale maps for translatable fields, and `whenLoaded()` for every relation. Never query inside `toArray()`. There is no `localized()` helper — it has never existed here.
- Filter extends `App\Base\BaseFilter`, or `App\Filters\CmsContentFilter` for a content list (it merges in the `is_active` whitelist entry and its cast). `$safeParms` is an operator whitelist (`'slug' => ['eq','like','in']`, `'price' => ['gte','lte']`). Override `apply()` only for search/joins.
- Routes are a flat one-line-per-endpoint list in `routes/api.php` using the Laravel resource verbs `index`, `show`, `store`, `update`, `destroy`, with a model-binding parameter (`{roomType}`, or `{journalPost:slug}` where the URL is editorial). Grouped by guard — `auth:users` (staff) and `auth:guests`; there is no guard named `sanctum` — and gated by `permission:` middleware, reads and writes separately. Public reads sit under the `public` prefix and are served from `app/Http/Controllers/Api`. Controllers are imported with `Admin`/`Api` aliases because both halves of a resource are declared in the same file. There is no `User/`, `Driver/` or `Seller/` folder and no `api/v1` prefix.

**Method names are Laravel's resource verbs, not PascalCase.** Earlier versions of this skill and of guide §3/§4 said routes call `GetAll`/`GetOne`/`Create`/`Update`/`Delete`, and put the base controllers in `App\Http\Controllers`. Neither has ever been true of this codebase, and the claim actively misled work on this project. Custom endpoints are `camelCase`. See guide §3–§8 for the full Category walkthrough and §4 for the base pair.

> **Current state of this codebase, so the next reader is not misled:** 35 of the
> 69 controllers are on the base pair — 23 on `BaseCRUDController` (17 of those
> with `HandlesRecycleBin`) and 12 on `BasePublicIndexController`. The remaining
> 34 stay on `BaseController` **on purpose**: their verbs are not the plain
> service verbs (a different service method such as `adminIndex()`, non-CRUD
> verbs like `confirm`/`settle`/`approve`, two collections instead of one
> paginator, the parent-scoped `MediaController`, the atomic bulk
> `SiteSettingController`). Forcing those onto the base would mean a hook per
> deviation, at which point the base stops being simpler than the line it
> replaces. Guide §4 lists the edges by name.
>
> The two blockers that used to make the pair unextendable are fixed: it no
> longer declares `show`/`update`/`destroy` with the abstract `Model`, nor
> `store`/`update` with the abstract `BaseRequest`. The base owns the body
> (`showResponse()`, `storeResponse()`, …) and the controller owns the signature,
> because implicit route-model binding and FormRequest resolution both read the
> controller's signature and PHP forbids narrowing a parameter type in an
> override. `$createRequest`/`$updateRequest` still do not exist and never did.
> `tests/Feature/BaseControllerRoutingTest.php` drives the pair through the real
> router; `tests/Feature/PublicIndexBoundaryTest.php` pins the public list
> boundary.
>
> **Write new controllers on the base pair.** Guide §3 step 7 has the shape.

## When to use an Action instead of a service method

The operation is a **verb**, not a resource (`CreateReservation`, `SettleFolio`, `RecalculateRating`); it spans multiple services/domains; or the service is approaching ~300 lines. Actions are **plain classes** in `app/Actions/{Domain}/`, take dependencies via constructor, expose `handle(...): array`, and wrap writes in `DB::transaction` directly. There is no `BaseAction` and the entry method is not `execute()` — earlier versions of this skill said both. Guide §10.

## Response envelope and errors

Never hand-write the envelope — `BaseController`'s `success()`, `paginatedSuccess()` and `respondFromService()`, plus the global handler, produce it (`success`, `message`, `data`, `request_id`; paginated data as `items` + `meta`).

Errors: throw domain exceptions (`NotFoundException`, `OutOfStockException`, `BusinessRuleException`, ...) with a translated message and machine-readable `context` payload. One exception class per `error_code`. Clients branch on `error_code`, never on `message` — error codes are a stable contract; adding one means notifying the Flutter team, renaming one is a breaking change. Never catch `Exception` to swallow errors. Guide §9 has the full exception table.

## Localization (AR/EN)

- Every user-facing string: `__('custom.key')`, key present in both `lang/en/custom.php` and `lang/ar/custom.php`. Never hardcode.
- Translatable model fields: `HasTranslations` + `$translatable` (Spatie — the locale map lives in the column, there is no `translations` table). Resources hand out the whole map via `getTranslations('field')`; content fields are **not** collapsed to one locale. `Accept-Language` sets `app()->getLocale()`, and so drives `__('custom.*')` and validation messages.
- New exception → its translation key exists in both languages.

## Model traits — use, don't reinvent

`HasTranslations` (Spatie locale maps), `LogsActivity` (Spatie audit trail; options fixed in the trait, no `$logName`), `FileTrait` (`storeFile`/`fileUrl`/`deleteFile` over the `Storage` facade), `HasUuid` (route key — never expose sequential IDs), `HasReviews` (morph reviews + `rating_avg`/`rating_count`), `PurgesMedia` (media + files go on **permanent** delete), `CascadesSoftDeletes` (carry a soft delete down the FK cascade edges and back on restore), `MirrorsToFirestore`. There is no `Cacheable` trait and no `localized()` helper. APIs in guide §11.

## Database rules

Design (guide §15 for full detail):
- 3NF by default; denormalize only deliberately and document who syncs the copy. No multi-value columns — pivot tables with composite unique indexes.
- Auto-increment `BIGINT` PK internally, UUID for anything public. FKs constrained with explicit `ON DELETE` per relationship.
- `DECIMAL` for money, never float; single base currency, convert on read. `NOT NULL` by default. `utf8mb4` always.
- `status` enum + history table beats multiplying boolean flags. Polymorphic relations only for genuinely generic attachments (translations, media, activity logs). Money movements go through immutable ledger tables.
- Index every FK and frequent `WHERE`/`ORDER BY` column; composite indexes follow the leftmost-prefix rule.
- Additive migrations; destructive changes need a backfill + deploy plan. Migrations are the only way schema changes.

Usage:
- Eager-load via `$with`; every relation used in a Resource must be covered. `whenLoaded()` guards the rest.
- `DB::transaction` around every multi-step write. Never wrap paginated `index` SELECTs in a transaction (replica lag).
- No `Model::all()` in production — always paginate. The controller reads `per_page` with `perPageParam($request)`; the default (15) and the ceiling (100) live in `BaseService::resolvePerPage()`. Chunk/lazy for large sets. Batch instead of querying in loops.
- UTC timestamps only; the client converts.
- `unique` rule on update must exempt the current record: `Rule::unique('table', 'col')->ignore($this->route('model'))`.
- Soft deletes change three things at once: `ON DELETE CASCADE` stops firing (use `CascadesSoftDeletes`), media must be purged on `forceDeleted` rather than `deleting`, and natural-key unique indexes must be scoped to live rows — with `unique:`/`exists:` scoped to match by `App\Validation\LiveRowPresenceVerifier`. Never hand-write `whereNull('deleted_at')` on a validation rule. Guide §14.

## Security

- Access checks in route middleware — `permission:cms.edit`, not `role:`, because roles are only presets and permissions are per-account. Instance-level ownership goes in `FormRequest::authorize()`. Never inline a role check in a controller.
- `$fillable` on every model.
- Payment callbacks: verify the gateway signature and re-fetch transaction status server-side. Never trust a client-reported success. Payment/webhook endpoints are idempotent (store processed transaction refs, short-circuit duplicates inside the same transaction).
- `throttle` middleware on OTP, login, password reset.
- Secrets in `.env`, never committed.

## Performance & reliability

- Slow work (email, FCM, images, reports) → queued jobs, never inline in the request.
- Caching requires a documented invalidation strategy.

## Testing

One feature test class per endpoint group: happy path + unauthenticated + wrong role + validation failures. Assert on the envelope (`success`, `error_code`, `data` shape) — the same contract clients consume. Factories for every model.

## Before finishing any backend change

Run through the PR checklist in guide §17 — indexes on FKs and `where` columns, translations in both languages, transactions on multi-writes, eager loads covering the Resource, no `request()` in services, no business logic in controllers, domain exceptions not response arrays, conventions on file placement. Treat it as a gate, not a suggestion.
