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
                  sendResponse() / global exception handler
                              ↓
                          JSON envelope
```

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

Inherit, declare, done:

- Service extends `BaseService`, declares `$model`, `$with` (eager loads), optional `$filter`.
- Controller extends `BaseCRUDController` (or `BaseIndexController` for read-only), declares `$resource`, `$createRequest`, `$updateRequest`, injects the service.
- Requests extend `BaseRequest`, live at `Http/Requests/{Role}/{Domain}/{Action}Request.php`, contain rules only.
- Resource extends `BaseResource`, uses `localized()` for AR/EN fields and `whenLoaded()` for every relation. Never query inside `toArray()`.
- Filter extends `BaseFilter` with `$safeParms` operator whitelist (`'name' => ['like']`, `'price' => ['gte','lte']`). Override `apply()` only for search/joins.
- Routes use the Laravel resource verbs `index`, `show`, `store`, `update`, `destroy`, grouped by role prefix + middleware (`admin`, `user`, `driver`, `seller`; public under `Api/`). Public read-only controllers add `indexPublic()` on the service, never a second controller verb.

Never duplicate `index`/`show` logic in a child — set `$resource` and let the base do it. See guide §3–§8 for the full Category walkthrough.

> **Current state of this codebase, so the next reader is not misled:** no
> controller extends `BaseCRUDController` or `BaseIndexController` yet. All 25+
> controllers extend `BaseController` and hand-copy the one-line index/show
> bodies. The base classes are real and tested, and migrating onto them is a
> known outstanding task (~1–1.5 days, mechanical) — two blockers first: their
> `show`/`update`/`destroy` type-hint the abstract `Model`, so implicit
> route-model binding cannot resolve, and `store`/`update` type-hint the abstract
> `BaseRequest`, so `$createRequest`/`$updateRequest` is not yet implemented.
> Write new controllers the way the existing 25 are written until that lands.

## When to use an Action instead of a service method

The operation is a **verb**, not a resource (`Checkout`, `AssignDriver`, `RefundOrder`); it spans multiple services/domains; or the service is approaching ~300 lines. Actions extend `BaseAction`, take dependencies via constructor, expose `execute(array $data): array`, and wrap writes in `$this->transaction()`. Guide §10.

## Response envelope and errors

Never hand-write the envelope — `sendResponse()` and the global handler produce it (`success`, `message`, `data`, `request_id`; paginated data as `items` + `meta`).

Errors: throw domain exceptions (`NotFoundException`, `OutOfStockException`, `BusinessRuleException`, ...) with a translated message and machine-readable `context` payload. One exception class per `error_code`. Clients branch on `error_code`, never on `message` — error codes are a stable contract; adding one means notifying the Flutter team, renaming one is a breaking change. Never catch `Exception` to swallow errors. Guide §9 has the full exception table.

## Localization (AR/EN)

- Every user-facing string: `__('custom.key')`, key present in both `lang/en/custom.php` and `lang/ar/custom.php`. Never hardcode.
- Translatable model fields: `HasTranslations` + `$translatable`; resources read via `localized()`. Locale comes from `Accept-Language`.
- New exception → its translation key exists in both languages.

## Model traits — use, don't reinvent

`HasTranslations` (AR/EN fields), `LogsActivity` (audit trail with `$logName`), `FileTrait` (uploads: extension whitelist, collision-safe names), `HasUuid` (public-facing routes — never expose sequential IDs), `Cacheable` (memoized `GetOne` with TTL). APIs in guide §11.

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
- No `Model::all()` in production — always paginate (`request()->integer('per_page', 20)`). Chunk/lazy for large sets. Batch instead of querying in loops.
- UTC timestamps only; the client converts.
- `unique` rule on update must exempt the current record: `unique:table,col,{$id}`.

## Security

- Role checks in route middleware; instance-level ownership in `FormRequest::authorize()`; never inline role checks in controllers.
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
