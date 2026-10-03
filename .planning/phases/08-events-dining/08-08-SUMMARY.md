---
phase: 08-events-dining
plan: 08
status: complete
---

# 08-08 Summary — Venue menu file (staff)

## Built
- `UploadMenuFileRequest` (`file` required|file|mimes:pdf,jpg,jpeg,png,webp|max:10240, `title` nullable ≤ 255). `UploadMediaRequest` untouched.
- `MediaService`: `attach(..., string $collection = 'images', ?string $title = null)` (additive); PR-1 scopes — library `index()` lists `collection = images` only, `attachExisting()` treats a non-image source as missing (existing 404 + context), scopes its idempotency lookup to images and writes `collection = images`, nested `destroy()` 404s a non-image row.
- `App\Actions\Dining\ReplaceVenueMenuFileAction`: one transaction — new `menu` row, delete older menu rows (purge after commit via `Media::deleted`, shared-file guard kept).
- `DiningVenueService::removeMenuFile()` (none → `not_found`).
- `Admin\DiningVenueMenuFileController` `store` (201 `MediaResource`) / `destroy` (200 `data: null`); routes `POST|DELETE /cms/dining-venues/{diningVenue}/menu-file` in the `cms.edit` group.

## Tests
- `tests/Feature/Dining/VenueMenuFileTest.php` (12): pdf 201 + row + file, jpg accepted, replace purges old file, replace keeps a shared file, delete 200 then 404, docx/oversize/missing 422, isolation (venue `images`, nested images DELETE 404 + row survives, venue attach 404, room-type attach 404, `/cms/media` excludes it), force-delete purges the menu file, 401, 403.
- `tests/Feature/Cms` (456) green with **no test file changed since 0961153**.

## Deviations
- `store` responds via `success()` instead of `respondFromService()`, because the latter replaces every 201 message with the generic "Created successfully." and the plan's `menu_file_uploaded` message would never be shown.
