---
phase: 08-events-dining
plan: 09
status: complete
---

# 08-09 Summary — Public menu download

## Built
- `DiningVenueService::menuFile()` (one query; `removeMenuFile()` reuses it).
- `Api\DiningVenueController::menuDownload()`: inactive → `NotFoundException`; no menu → `response()->noContent()` (204, empty body, the one documented envelope exception); else 200 envelope `{url, file_name, mime_type, size, updated_at}`.
- Route `GET /public/dining-venues/{diningVenue}/menu/download` in the public group (no auth middleware).

## Tests
- `tests/Feature/Dining/MenuDownloadTest.php` (8 incl. provider): 200 shape + URL, latest wins, 204 empty body, 404 unknown / inactive / trashed, no token and a bogus token both 200, budget 2 (binding + menu row).
- `DiningVenueTest`, `MenuCatalogTest`, `PublicIndexBoundaryTest` green.
