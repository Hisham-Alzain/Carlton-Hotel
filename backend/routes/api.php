<?php

use App\Http\Controllers\Admin\AmenityController as AdminAmenityController;
use App\Http\Controllers\Admin\CheckInApprovalController;
use App\Http\Controllers\Admin\HomeSliderController as AdminHomeSliderController;
use App\Http\Controllers\Admin\ReviewController as AdminReviewController;
use App\Http\Controllers\Api\HomeSliderController as ApiHomeSliderController;
use App\Http\Controllers\Admin\ServiceCategoryController as AdminServiceCategoryController;
use App\Http\Controllers\Admin\ServiceItemController as AdminServiceItemController;
use App\Http\Controllers\Api\MenuController as ApiMenuController;
use App\Http\Controllers\Api\ServiceCatalogController;
use App\Http\Controllers\Api\StayController;
use App\Http\Controllers\Api\TableReservationController;
use App\Http\Controllers\Api\AmenityController as ApiAmenityController;
use App\Http\Controllers\Api\BookableController;
use App\Http\Controllers\Api\ReviewController as ApiReviewController;
use App\Http\Controllers\Admin\ConversationController as AdminConversationController;
use App\Http\Controllers\Admin\FolioController as AdminFolioController;
use App\Http\Controllers\Api\ConversationController as ApiConversationController;
use App\Http\Controllers\Api\DeviceTokenController;
use App\Http\Controllers\Admin\DiningVenueController as AdminDiningVenueController;
use App\Http\Controllers\Admin\OperationsQueueController;
use App\Http\Controllers\Admin\EventInquiryController as AdminEventInquiryController;
use App\Http\Controllers\Admin\MenuCategoryController;
use App\Http\Controllers\Admin\MenuItemController;
use App\Http\Controllers\Admin\PaymentController;
use App\Http\Controllers\Admin\PoolCabanaController;
use App\Http\Controllers\Admin\RestaurantTableController;
use App\Http\Controllers\Admin\SpaServiceController;
use App\Http\Controllers\Admin\TransferController;
use App\Http\Controllers\Api\EventInquiryController as ApiEventInquiryController;
use App\Http\Controllers\Api\FolioController as ApiFolioController;
use App\Http\Controllers\Api\PreArrivalController;
use App\Http\Controllers\Api\ServiceBookingController;
use App\Http\Controllers\Api\ServiceRequestController;
use App\Http\Controllers\Api\TransportRequestController;
use App\Http\Controllers\Admin\ReservationController as AdminReservationController;
use App\Http\Controllers\Admin\EventSpaceController as AdminEventSpaceController;
use App\Http\Controllers\Admin\FacilityController as AdminFacilityController;
use App\Http\Controllers\Admin\MediaController;
use App\Http\Controllers\Admin\PageController as AdminPageController;
use App\Http\Controllers\Admin\PromotionController as AdminPromotionController;
use App\Http\Controllers\Admin\RoomController as AdminRoomController;
use App\Http\Controllers\Admin\FaqController as AdminFaqController;
use App\Http\Controllers\Admin\ExperienceController as AdminExperienceController;
use App\Http\Controllers\Admin\GalleryCategoryController as AdminGalleryCategoryController;
use App\Http\Controllers\Admin\GalleryItemController as AdminGalleryItemController;
use App\Http\Controllers\Admin\JournalPostController as AdminJournalPostController;
use App\Http\Controllers\Admin\SiteSettingController as AdminSiteSettingController;
use App\Http\Controllers\Admin\RoomTypeController as AdminRoomTypeController;
use App\Http\Controllers\Admin\TestimonialController as AdminTestimonialController;
use App\Http\Controllers\Api\AvailabilityController;
use App\Http\Controllers\Api\DiningVenueController as ApiDiningVenueController;
use App\Http\Controllers\Api\ReservationController;
use App\Http\Controllers\Api\EventSpaceController as ApiEventSpaceController;
use App\Http\Controllers\Api\FacilityController as ApiFacilityController;
use App\Http\Controllers\Api\PageController as ApiPageController;
use App\Http\Controllers\Api\PromotionController as ApiPromotionController;
use App\Http\Controllers\Api\RoomController as ApiRoomController;
use App\Http\Controllers\Api\FaqController as ApiFaqController;
use App\Http\Controllers\Api\ExperienceController as ApiExperienceController;
use App\Http\Controllers\Api\GalleryController as ApiGalleryController;
use App\Http\Controllers\Api\JournalPostController as ApiJournalPostController;
use App\Http\Controllers\Api\SiteSettingController as ApiSiteSettingController;
use App\Http\Controllers\Api\RoomTypeController as ApiRoomTypeController;
use App\Http\Controllers\Api\TestimonialController as ApiTestimonialController;
use App\Http\Controllers\Auth\GuestAuthController;
use App\Http\Controllers\Auth\StaffAuthController;
use App\Http\Controllers\Staff\PermissionController;
use App\Http\Controllers\Staff\RoleController;
use App\Http\Controllers\Staff\StaffController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Permanent liveness probe
Route::get('/health', function (Request $request) {
    return response()->json([
        'success'    => true,
        'message'    => __('custom.health.ok'),
        'data'       => ['status' => 'ok', 'time' => now()->toIso8601String()],
        'request_id' => $request->attributes->get('request_id', ''),
    ]);
});

// Staff auth
Route::prefix('auth')->group(function () {
    // Throttled at the same 10/minute as the guest OTP route below, and for the
    // same reason: this is an unauthenticated endpoint that accepts a secret and
    // tells the caller whether it was right. Left open it is unlimited
    // credential stuffing against accounts that hold `cms.edit`, the
    // reservations verbs and folio settlement — the highest-value accounts in
    // the system.
    //
    // 10/minute per IP, not lower: `throttle` keys on the client IP, and a hotel
    // back office is one NAT'd address, so a shift change puts reception,
    // concierge and housekeeping in the same bucket within the same minute. A
    // tighter cap would lock out a real shift handover, which is the failure
    // mode that gets throttles deleted again. 10/minute still turns an
    // unbounded attack into 14,400 guesses a day from one address — useless
    // against any real password, and loud in the logs long before it succeeds.
    Route::post('/login', [StaffAuthController::class, 'login'])->middleware('throttle:10,1');
    Route::middleware('auth:users')->group(function () {
        Route::post('/logout', [StaffAuthController::class, 'logout']);
        Route::get('/me', [StaffAuthController::class, 'me']);
    });

    // Guest auth
    Route::prefix('guest')->group(function () {
        Route::post('/request-otp',      [GuestAuthController::class, 'requestOtp'])->middleware('throttle:10,1');
        Route::post('/verify-otp',       [GuestAuthController::class, 'verifyOtp']);
        Route::post('/link-booking-code',[GuestAuthController::class, 'linkBookingCode']);
        Route::middleware('auth:guests')->group(function () {
            Route::post('/logout', [GuestAuthController::class, 'logout']);
            Route::get('/me',      [GuestAuthController::class, 'me']);
            Route::put('/profile', [GuestAuthController::class, 'updateProfile']);
        });
    });
});

// ──────────────────────────────────────────────────────────────────────
// P3 — CMS: Public read endpoints (no auth, is_active only)
// ──────────────────────────────────────────────────────────────────────
Route::prefix('public')->group(function () {
    Route::get('/home-sliders',           [ApiHomeSliderController::class, 'index']);
    Route::get('/room-types',             [ApiRoomTypeController::class,   'index']);
    Route::get('/room-types/{roomType}',  [ApiRoomTypeController::class,   'show']);
    Route::get('/rooms',                  [ApiRoomController::class,       'index']);
    Route::get('/rooms/{room}',           [ApiRoomController::class,       'show']);
    Route::get('/facilities',             [ApiFacilityController::class,   'index']);
    Route::get('/facilities/{facility}',  [ApiFacilityController::class,   'show']);
    Route::get('/dining-venues',              [ApiDiningVenueController::class, 'index']);
    Route::get('/dining-venues/{diningVenue}',[ApiDiningVenueController::class, 'show']);
    // Restaurant menu: filter chips + items, optionally narrowed by ?type=<slug>
    Route::get('/dining-venues/{diningVenue}/menu-categories', [ApiMenuController::class, 'categories']);
    Route::get('/dining-venues/{diningVenue}/menu',            [ApiMenuController::class, 'index']);
    Route::get('/event-spaces',               [ApiEventSpaceController::class,  'index']);
    Route::get('/event-spaces/{eventSpace}',  [ApiEventSpaceController::class,  'show']);
    Route::get('/amenities',              [ApiAmenityController::class,    'index']);
    // Guest service menu: 8 categories with their microservices. `kind` tells
    // the client how to render each one — see ServiceCategoryKind.
    Route::get('/service-catalog',        [ServiceCatalogController::class, 'index']);

    // Bookables — the uuids POST /service-bookings needs. Without these the
    // booking endpoint had no discoverable input.
    Route::get('/spa-services',   [BookableController::class, 'spaServices']);
    Route::get('/pool-cabanas',   [BookableController::class, 'poolCabanas']);
    Route::get('/transfers',      [BookableController::class, 'transfers']);
    Route::get('/dining-venues/{diningVenue}/tables', [BookableController::class, 'restaurantTables']);
    // {type} is a ReviewableType value (room_type|dining_venue) — see ReviewService.
    Route::get('/reviews/{type}/{uuid}',  [ApiReviewController::class,     'index']);
    Route::get('/pages/{slug}',           [ApiPageController::class,       'show']);
    Route::get('/promotions',             [ApiPromotionController::class,  'index']);
    Route::get('/promotions/{promotion}', [ApiPromotionController::class,  'show']);
    // Curated marketing quotes. Rendered as one section, so there is no `show`.
    Route::get('/testimonials',           [ApiTestimonialController::class, 'index']);
    // One accordion on the site, so likewise no `show`.
    Route::get('/faqs',                   [ApiFaqController::class,        'index']);
    // Concierge experiences. Unlike the two above these have detail pages, so
    // `show` exists — and 404s on a draft rather than previewing it.
    Route::get('/experiences',              [ApiExperienceController::class, 'index']);
    Route::get('/experiences/{experience}', [ApiExperienceController::class, 'show']);
    // Gallery: the chip row and the photographs. Two reads for one screen, and
    // no `show` — nothing deep-links to a single photo. `/gallery` returns only
    // photographs whose chip is published as well as themselves.
    Route::get('/gallery-categories',       [ApiGalleryController::class, 'categories']);
    Route::get('/gallery',                  [ApiGalleryController::class, 'index']);
    // Journal. Bound by `{journalPost:slug}`, not uuid: the article URL on the
    // website is the slug an editor chose. Ordered by `published_on` descending —
    // that column is a display date, NOT a schedule, so a future-dated active
    // post is returned here (see JournalPostService::indexPublic).
    Route::get('/journal',                    [ApiJournalPostController::class, 'index']);
    Route::get('/journal/{journalPost:slug}', [ApiJournalPostController::class, 'show']);
    // Global site copy — a flat {group: {key: value}} map, NOT paginated and NOT
    // {items, meta}. Deliberate exception; see Api\SiteSettingController::index().
    Route::get('/settings',                   [ApiSiteSettingController::class, 'index']);

    // P4 — Availability & pricing (public)
    Route::get('/availability', [AvailabilityController::class, 'check']);
    Route::get('/quote',        [AvailabilityController::class, 'quote']);
});

// ──────────────────────────────────────────────────────────────────────
// P3 — CMS: Admin CRUD
//
// Read verbs require cms.view, write verbs require cms.edit. Reads are gated
// on `cms.view|cms.edit` rather than `cms.view` alone because Spatie's
// PermissionMiddleware resolves a pipe-separated list through `canAny()` —
// ANY, not ALL (see vendor/spatie/laravel-permission PermissionMiddleware).
// That makes cms.edit imply read access, so an editor never needs both rows
// to use the CMS, while cms.view alone yields a genuine read-only reviewer.
//
// Before this split the whole block sat behind cms.edit and cms.view was
// enforced nowhere — a role granted only cms.view got 403 on every GET while
// the seeder advertised it as the read half of a read+write pair.
// ──────────────────────────────────────────────────────────────────────
Route::middleware('auth:users')->prefix('cms')->group(function () {

    // ── The recycle bin (cms.restore / cms.purge) ─────────────────────
    //
    // `DELETE /cms/{resource}/{uuid}` marks a row rather than removing it. Until
    // these routes existed nothing reached `BaseService::trashed/restore/
    // forceDestroy`, so an editor could not undo a delete and no row could ever
    // leave the table — which also meant `PurgesMedia` (now hooked on
    // `forceDeleted`) never fired and every deleted record's photography stayed
    // on disk forever.
    //
    // Declared FIRST inside the prefix, and deliberately so: routes match in
    // registration order, and `/room-types/{roomType}` registered above would
    // swallow `/room-types/trashed` and try to resolve "trashed" as a uuid.
    //
    // `->withTrashed()` on restore and force is load-bearing, not decoration.
    // Implicit binding resolves through the model's `SoftDeletes` global scope,
    // so without it the two verbs whose entire purpose is to address a deleted
    // record 404 on every record they can be given. It goes per route because
    // `withTrashed()` lives on `Route`, not on `RouteRegistrar` — there is no
    // group-level form of it.
    //
    // Permissions are split off `cms.edit` because these are not edits.
    // `cms.restore` is undo and belongs with editing (the `content_editor`
    // preset holds it); `cms.purge` destroys data no backup of the API can
    // return and belongs to `content_manager`. The bin listing admits either,
    // since it is only useful to someone who can act on a row in it.
    //
    // 17 of the 18 soft-deletable models are here. `SiteSetting` is not: it has
    // no per-row delete route at all (settings are one atomic bulk PUT through
    // `UpsertSiteSettingsAction`), so nothing an API client can do puts a
    // setting in the bin and the three verbs would address an empty set.
    Route::middleware('permission:cms.restore|cms.purge')->group(function () {
        Route::get('/room-types/trashed',         [AdminRoomTypeController::class, 'trashed']);
        Route::get('/rooms/trashed',              [AdminRoomController::class, 'trashed']);
        Route::get('/facilities/trashed',         [AdminFacilityController::class, 'trashed']);
        Route::get('/dining-venues/trashed',      [AdminDiningVenueController::class, 'trashed']);
        Route::get('/event-spaces/trashed',       [AdminEventSpaceController::class, 'trashed']);
        Route::get('/amenities/trashed',          [AdminAmenityController::class, 'trashed']);
        Route::get('/home-sliders/trashed',       [AdminHomeSliderController::class, 'trashed']);
        Route::get('/pages/trashed',              [AdminPageController::class, 'trashed']);
        Route::get('/promotions/trashed',         [AdminPromotionController::class, 'trashed']);
        Route::get('/testimonials/trashed',       [AdminTestimonialController::class, 'trashed']);
        Route::get('/faqs/trashed',               [AdminFaqController::class, 'trashed']);
        Route::get('/experiences/trashed',        [AdminExperienceController::class, 'trashed']);
        Route::get('/gallery-categories/trashed', [AdminGalleryCategoryController::class, 'trashed']);
        Route::get('/gallery-items/trashed',      [AdminGalleryItemController::class, 'trashed']);
        Route::get('/journal-posts/trashed',      [AdminJournalPostController::class, 'trashed']);
        Route::get('/menu-categories/trashed',    [MenuCategoryController::class, 'trashed']);
        Route::get('/menu-items/trashed',         [MenuItemController::class, 'trashed']);
    });

    Route::middleware('permission:cms.restore')->group(function () {
        Route::post('/room-types/{roomType}/restore',              [AdminRoomTypeController::class, 'restore'])->withTrashed();
        Route::post('/rooms/{room}/restore',                       [AdminRoomController::class, 'restore'])->withTrashed();
        Route::post('/facilities/{facility}/restore',              [AdminFacilityController::class, 'restore'])->withTrashed();
        Route::post('/dining-venues/{diningVenue}/restore',        [AdminDiningVenueController::class, 'restore'])->withTrashed();
        Route::post('/event-spaces/{eventSpace}/restore',          [AdminEventSpaceController::class, 'restore'])->withTrashed();
        Route::post('/amenities/{amenity}/restore',                [AdminAmenityController::class, 'restore'])->withTrashed();
        Route::post('/home-sliders/{homeSlider}/restore',          [AdminHomeSliderController::class, 'restore'])->withTrashed();
        Route::post('/pages/{page}/restore',                       [AdminPageController::class, 'restore'])->withTrashed();
        Route::post('/promotions/{promotion}/restore',             [AdminPromotionController::class, 'restore'])->withTrashed();
        Route::post('/testimonials/{testimonial}/restore',         [AdminTestimonialController::class, 'restore'])->withTrashed();
        Route::post('/faqs/{faq}/restore',                         [AdminFaqController::class, 'restore'])->withTrashed();
        Route::post('/experiences/{experience}/restore',           [AdminExperienceController::class, 'restore'])->withTrashed();
        Route::post('/gallery-categories/{galleryCategory}/restore',[AdminGalleryCategoryController::class, 'restore'])->withTrashed();
        Route::post('/gallery-items/{galleryItem}/restore',        [AdminGalleryItemController::class, 'restore'])->withTrashed();
        Route::post('/journal-posts/{journalPost}/restore',        [AdminJournalPostController::class, 'restore'])->withTrashed();
        Route::post('/menu-categories/{menuCategory}/restore',     [MenuCategoryController::class, 'restore'])->withTrashed();
        Route::post('/menu-items/{menuItem}/restore',              [MenuItemController::class, 'restore'])->withTrashed();
    });

    Route::middleware('permission:cms.purge')->group(function () {
        Route::delete('/room-types/{roomType}/force',              [AdminRoomTypeController::class, 'forceDestroy'])->withTrashed();
        Route::delete('/rooms/{room}/force',                       [AdminRoomController::class, 'forceDestroy'])->withTrashed();
        Route::delete('/facilities/{facility}/force',              [AdminFacilityController::class, 'forceDestroy'])->withTrashed();
        Route::delete('/dining-venues/{diningVenue}/force',        [AdminDiningVenueController::class, 'forceDestroy'])->withTrashed();
        Route::delete('/event-spaces/{eventSpace}/force',          [AdminEventSpaceController::class, 'forceDestroy'])->withTrashed();
        Route::delete('/amenities/{amenity}/force',                [AdminAmenityController::class, 'forceDestroy'])->withTrashed();
        Route::delete('/home-sliders/{homeSlider}/force',          [AdminHomeSliderController::class, 'forceDestroy'])->withTrashed();
        Route::delete('/pages/{page}/force',                       [AdminPageController::class, 'forceDestroy'])->withTrashed();
        Route::delete('/promotions/{promotion}/force',             [AdminPromotionController::class, 'forceDestroy'])->withTrashed();
        Route::delete('/testimonials/{testimonial}/force',         [AdminTestimonialController::class, 'forceDestroy'])->withTrashed();
        Route::delete('/faqs/{faq}/force',                         [AdminFaqController::class, 'forceDestroy'])->withTrashed();
        Route::delete('/experiences/{experience}/force',           [AdminExperienceController::class, 'forceDestroy'])->withTrashed();
        Route::delete('/gallery-categories/{galleryCategory}/force',[AdminGalleryCategoryController::class, 'forceDestroy'])->withTrashed();
        Route::delete('/gallery-items/{galleryItem}/force',        [AdminGalleryItemController::class, 'forceDestroy'])->withTrashed();
        Route::delete('/journal-posts/{journalPost}/force',        [AdminJournalPostController::class, 'forceDestroy'])->withTrashed();
        Route::delete('/menu-categories/{menuCategory}/force',     [MenuCategoryController::class, 'forceDestroy'])->withTrashed();
        Route::delete('/menu-items/{menuItem}/force',              [MenuItemController::class, 'forceDestroy'])->withTrashed();
    });

    // ── Reads (cms.view, or cms.edit which implies it) ────────────────
    Route::middleware('permission:cms.view|cms.edit')->group(function () {
        Route::get('/room-types',                   [AdminRoomTypeController::class, 'index']);
        Route::get('/room-types/{roomType}',        [AdminRoomTypeController::class, 'show']);

        Route::get('/rooms',                        [AdminRoomController::class, 'index']);
        Route::get('/rooms/{room}',                 [AdminRoomController::class, 'show']);

        Route::get('/facilities',                   [AdminFacilityController::class, 'index']);
        Route::get('/facilities/{facility}',        [AdminFacilityController::class, 'show']);

        Route::get('/dining-venues',                [AdminDiningVenueController::class, 'index']);
        Route::get('/dining-venues/{diningVenue}',  [AdminDiningVenueController::class, 'show']);

        Route::get('/event-spaces',                 [AdminEventSpaceController::class, 'index']);
        Route::get('/event-spaces/{eventSpace}',    [AdminEventSpaceController::class, 'show']);

        Route::get('/amenities',                    [AdminAmenityController::class, 'index']);
        Route::get('/amenities/{amenity}',          [AdminAmenityController::class, 'show']);

        Route::get('/home-sliders',                 [AdminHomeSliderController::class, 'index']);
        Route::get('/home-sliders/{homeSlider}',    [AdminHomeSliderController::class, 'show']);

        // Reviews — read; guests are the only authors. `show` answers for an
        // unpublished review too: the queue exists to moderate drafts, so a row
        // it lists has to be openable. The public projection keeps its
        // published-only rule (see ReviewService::indexFor).
        Route::get('/reviews',                      [AdminReviewController::class, 'index']);
        Route::get('/reviews/{review}',             [AdminReviewController::class, 'show']);

        Route::get('/pages',                        [AdminPageController::class, 'index']);
        Route::get('/pages/{page}',                 [AdminPageController::class, 'show']);

        Route::get('/promotions',                   [AdminPromotionController::class, 'index']);
        Route::get('/promotions/{promotion}',       [AdminPromotionController::class, 'show']);

        Route::get('/testimonials',                 [AdminTestimonialController::class, 'index']);
        Route::get('/testimonials/{testimonial}',   [AdminTestimonialController::class, 'show']);

        Route::get('/faqs',                         [AdminFaqController::class, 'index']);
        Route::get('/faqs/{faq}',                   [AdminFaqController::class, 'show']);

        Route::get('/experiences',                  [AdminExperienceController::class, 'index']);
        Route::get('/experiences/{experience}',     [AdminExperienceController::class, 'show']);

        // Gallery — chips and photographs are separate CRUD resources; the item
        // list narrows by chip slug via `?category=rooms`.
        Route::get('/gallery-categories',                    [AdminGalleryCategoryController::class, 'index']);
        Route::get('/gallery-categories/{galleryCategory}',  [AdminGalleryCategoryController::class, 'show']);
        Route::get('/gallery-items',                         [AdminGalleryItemController::class, 'index']);
        Route::get('/gallery-items/{galleryItem}',           [AdminGalleryItemController::class, 'show']);

        // Journal — the CMS addresses posts by uuid so re-slugging an article
        // never breaks an editor's bookmark; only the public route uses the slug.
        Route::get('/journal-posts',                 [AdminJournalPostController::class, 'index']);
        Route::get('/journal-posts/{journalPost}',   [AdminJournalPostController::class, 'show']);

        // Site settings — one grouped read, no per-row show. Not paginated;
        // see SiteSettingService::grouped().
        Route::get('/settings',                      [AdminSiteSettingController::class, 'index']);

        // Media library — every uploaded asset, attached or not. `?unattached=true`
        // is the "nothing points at this yet" view; `?mime=` and `?mediable_type=`
        // narrow by file type and by which entity holds it. Read-only here so a
        // cms.view reviewer can see what the site is built from.
        Route::get('/media',                         [MediaController::class, 'index']);
    });

    // ── Writes (cms.edit only) ────────────────────────────────────────
    Route::middleware('permission:cms.edit')->group(function () {
        // Media library — parentless upload, metadata edit, and delete. The
        // per-parent `/{parent}/{uuid}/images` routes below still exist and are
        // unchanged; these are the same asset store addressed on its own terms.
        // DELETE here is unscoped by design — it is the library's own route, so
        // there is no parent to check against, and the nested delete keeps its
        // cross-parent guard.
        Route::post  ('/media',                                        [MediaController::class, 'store']);
        Route::patch ('/media/{media}',                               [MediaController::class, 'update']);
        Route::delete('/media/{media}',                               [MediaController::class, 'destroy']);

        // Room types
        Route::post  ('/room-types',                                  [AdminRoomTypeController::class, 'store']);
        Route::put   ('/room-types/{roomType}',                       [AdminRoomTypeController::class, 'update']);
        Route::delete('/room-types/{roomType}',                       [AdminRoomTypeController::class, 'destroy']);
        Route::post  ('/room-types/{roomType}/images',                [MediaController::class, 'storeRoomType']);
        Route::delete('/room-types/{roomType}/images/{media}',        [MediaController::class, 'destroyRoomType']);
        // Attach assets that already exist, so one photograph can serve several
        // entities without being uploaded once per entity.
        Route::post  ('/room-types/{roomType}/images/attach',          [MediaController::class, 'attachRoomType']);

        // Rooms
        Route::post  ('/rooms',                                       [AdminRoomController::class, 'store']);
        Route::put   ('/rooms/{room}',                                [AdminRoomController::class, 'update']);
        Route::delete('/rooms/{room}',                                [AdminRoomController::class, 'destroy']);
        Route::post  ('/rooms/{room}/images',                         [MediaController::class, 'storeRoom']);
        Route::delete('/rooms/{room}/images/{media}',                 [MediaController::class, 'destroyRoom']);
        Route::post  ('/rooms/{room}/images/attach',                  [MediaController::class, 'attachRoom']);

        // Facilities
        Route::post  ('/facilities',                                  [AdminFacilityController::class, 'store']);
        Route::put   ('/facilities/{facility}',                       [AdminFacilityController::class, 'update']);
        Route::delete('/facilities/{facility}',                       [AdminFacilityController::class, 'destroy']);
        Route::post  ('/facilities/{facility}/images',                [MediaController::class, 'storeFacility']);
        Route::delete('/facilities/{facility}/images/{media}',        [MediaController::class, 'destroyFacility']);
        Route::post  ('/facilities/{facility}/images/attach',          [MediaController::class, 'attachFacility']);

        // Dining venues
        Route::post  ('/dining-venues',                               [AdminDiningVenueController::class, 'store']);
        Route::put   ('/dining-venues/{diningVenue}',                 [AdminDiningVenueController::class, 'update']);
        Route::delete('/dining-venues/{diningVenue}',                 [AdminDiningVenueController::class, 'destroy']);
        Route::post  ('/dining-venues/{diningVenue}/images',          [MediaController::class, 'storeDiningVenue']);
        Route::delete('/dining-venues/{diningVenue}/images/{media}',  [MediaController::class, 'destroyDiningVenue']);
        Route::post  ('/dining-venues/{diningVenue}/images/attach',    [MediaController::class, 'attachDiningVenue']);

        // Event spaces
        Route::post  ('/event-spaces',                                [AdminEventSpaceController::class, 'store']);
        Route::put   ('/event-spaces/{eventSpace}',                   [AdminEventSpaceController::class, 'update']);
        Route::delete('/event-spaces/{eventSpace}',                   [AdminEventSpaceController::class, 'destroy']);
        Route::post  ('/event-spaces/{eventSpace}/images',            [MediaController::class, 'storeEventSpace']);
        Route::delete('/event-spaces/{eventSpace}/images/{media}',    [MediaController::class, 'destroyEventSpace']);
        Route::post  ('/event-spaces/{eventSpace}/images/attach',      [MediaController::class, 'attachEventSpace']);

        // Amenities (in-room amenity catalog joined to room types)
        Route::post  ('/amenities',                                   [AdminAmenityController::class, 'store']);
        Route::put   ('/amenities/{amenity}',                         [AdminAmenityController::class, 'update']);
        Route::delete('/amenities/{amenity}',                         [AdminAmenityController::class, 'destroy']);

        // Home sliders
        Route::post  ('/home-sliders',                                [AdminHomeSliderController::class, 'store']);
        Route::put   ('/home-sliders/{homeSlider}',                   [AdminHomeSliderController::class, 'update']);
        Route::delete('/home-sliders/{homeSlider}',                   [AdminHomeSliderController::class, 'destroy']);
        Route::post  ('/home-sliders/{homeSlider}/images',            [MediaController::class, 'storeHomeSlider']);
        Route::delete('/home-sliders/{homeSlider}/images/{media}',    [MediaController::class, 'destroyHomeSlider']);
        Route::post  ('/home-sliders/{homeSlider}/images/attach',      [MediaController::class, 'attachHomeSlider']);

        // Reviews — moderation is a write.
        Route::patch ('/reviews/{review}/publish',                    [AdminReviewController::class, 'setPublished']);

        // Pages
        Route::post  ('/pages',                                       [AdminPageController::class, 'store']);
        Route::put   ('/pages/{page}',                                [AdminPageController::class, 'update']);
        Route::delete('/pages/{page}',                                [AdminPageController::class, 'destroy']);

        // Promotions
        Route::post  ('/promotions',                                  [AdminPromotionController::class, 'store']);
        Route::put   ('/promotions/{promotion}',                      [AdminPromotionController::class, 'update']);
        Route::delete('/promotions/{promotion}',                      [AdminPromotionController::class, 'destroy']);
        Route::post  ('/promotions/{promotion}/images',               [MediaController::class, 'storePromotion']);
        Route::delete('/promotions/{promotion}/images/{media}',       [MediaController::class, 'destroyPromotion']);
        Route::post  ('/promotions/{promotion}/images/attach',         [MediaController::class, 'attachPromotion']);

        // Testimonials
        Route::post  ('/testimonials',                                [AdminTestimonialController::class, 'store']);
        Route::put   ('/testimonials/{testimonial}',                  [AdminTestimonialController::class, 'update']);
        Route::delete('/testimonials/{testimonial}',                  [AdminTestimonialController::class, 'destroy']);
        Route::post  ('/testimonials/{testimonial}/images',           [MediaController::class, 'storeTestimonial']);
        Route::delete('/testimonials/{testimonial}/images/{media}',   [MediaController::class, 'destroyTestimonial']);
        Route::post  ('/testimonials/{testimonial}/images/attach',     [MediaController::class, 'attachTestimonial']);

        // FAQs — text only, no media
        Route::post  ('/faqs',                                        [AdminFaqController::class, 'store']);
        Route::put   ('/faqs/{faq}',                                  [AdminFaqController::class, 'update']);
        Route::delete('/faqs/{faq}',                                  [AdminFaqController::class, 'destroy']);

        // Experiences
        Route::post  ('/experiences',                                 [AdminExperienceController::class, 'store']);
        Route::put   ('/experiences/{experience}',                    [AdminExperienceController::class, 'update']);
        Route::delete('/experiences/{experience}',                    [AdminExperienceController::class, 'destroy']);
        Route::post  ('/experiences/{experience}/images',             [MediaController::class, 'storeExperience']);
        Route::delete('/experiences/{experience}/images/{media}',     [MediaController::class, 'destroyExperience']);
        Route::post  ('/experiences/{experience}/images/attach',       [MediaController::class, 'attachExperience']);

        // Gallery categories — deleting one cascades to its photographs.
        Route::post  ('/gallery-categories',                          [AdminGalleryCategoryController::class, 'store']);
        Route::put   ('/gallery-categories/{galleryCategory}',        [AdminGalleryCategoryController::class, 'update']);
        Route::delete('/gallery-categories/{galleryCategory}',        [AdminGalleryCategoryController::class, 'destroy']);

        // Gallery items — the photograph arrives through the media routes.
        Route::post  ('/gallery-items',                               [AdminGalleryItemController::class, 'store']);
        Route::put   ('/gallery-items/{galleryItem}',                 [AdminGalleryItemController::class, 'update']);
        Route::delete('/gallery-items/{galleryItem}',                 [AdminGalleryItemController::class, 'destroy']);
        Route::post  ('/gallery-items/{galleryItem}/images',          [MediaController::class, 'storeGalleryItem']);
        Route::delete('/gallery-items/{galleryItem}/images/{media}',  [MediaController::class, 'destroyGalleryItem']);
        Route::post  ('/gallery-items/{galleryItem}/images/attach',    [MediaController::class, 'attachGalleryItem']);

        // Journal posts — first image is the article cover.
        Route::post  ('/journal-posts',                                [AdminJournalPostController::class, 'store']);
        Route::put   ('/journal-posts/{journalPost}',                  [AdminJournalPostController::class, 'update']);
        Route::delete('/journal-posts/{journalPost}',                  [AdminJournalPostController::class, 'destroy']);
        Route::post  ('/journal-posts/{journalPost}/images',           [MediaController::class, 'storeJournalPost']);
        Route::delete('/journal-posts/{journalPost}/images/{media}',   [MediaController::class, 'destroyJournalPost']);
        Route::post  ('/journal-posts/{journalPost}/images/attach',     [MediaController::class, 'attachJournalPost']);

        // Site settings — a single bulk upsert, atomic, no per-row verbs. PUT
        // rather than PATCH: the CMS submits the whole settings form it holds.
        Route::put   ('/settings',                                     [AdminSiteSettingController::class, 'update']);
    });
});

// ──────────────────────────────────────────────────────────────────────
// P4 — Booking: Guest-facing reservation endpoints
// ──────────────────────────────────────────────────────────────────────

// ──────────────────────────────────────────────────────────────────────
// P6 — Events / RFP: Public submit (no auth)
// ──────────────────────────────────────────────────────────────────────
Route::post('/event-inquiries', [ApiEventInquiryController::class, 'submit']);

// P6 — Events / RFP: Admin triage
Route::middleware('auth:users')->prefix('cms/event-inquiries')->group(function () {
    Route::middleware('permission:tickets.view')->group(function () {
        Route::get('/',          [AdminEventInquiryController::class, 'index']);
        Route::get('/{inquiry}', [AdminEventInquiryController::class, 'show']);
    });
    Route::middleware('permission:tickets.assign')->group(function () {
        Route::patch('/{inquiry}/status', [AdminEventInquiryController::class, 'updateStatus']);
        Route::patch('/{inquiry}/assign', [AdminEventInquiryController::class, 'assign']);
    });
});

// ──────────────────────────────────────────────────────────────────────
// Public (no auth) — two-step guest booking
Route::post('/reservations/guest',        [ReservationController::class, 'storeAsGuest']);
Route::post('/reservations/guest/verify', [ReservationController::class, 'verifyGuestBooking']);

// Guest reviews — tier-2 (any guest token). Verified-stay is derived from the
// guest's reservation history inside SubmitReviewAction, not gated at the route.
Route::middleware('auth:guests')
    ->post('/reviews/{type}/{uuid}', [ApiReviewController::class, 'store']);

// ──────────────────────────────────────────────────────────────────────
// Stays — read projections over reservations for the mobile stay screens.
// Plain auth:guests on the three reads: "no active stay" is an empty state,
// not a 403. Checkout reuses POST /folio/approve (it already approves the bill
// and flips the reservation to checked_out); cancelling an upcoming stay reuses
// DELETE /reservations/{reservation}; "book again" is a client deep-link into
// the existing quote + POST /reservations flow, seeded by room_type_uuid on the
// past-stay payload.
// ──────────────────────────────────────────────────────────────────────
Route::middleware('auth:guests')->prefix('stays')->group(function () {
    // Cheap entitlement probe the app can poll on resume: is this token's guest
    // checked in? Declared before /{reservation} so "status" is never a UUID.
    Route::get('/status',   [StayController::class, 'status']);
    Route::post('/check-in', [StayController::class, 'checkIn']);
    Route::get('/active',   [StayController::class, 'active']);
    Route::get('/upcoming', [StayController::class, 'upcoming']);
    Route::get('/past',     [StayController::class, 'past']);
    Route::get('/{reservation}/receipt',     [StayController::class, 'receipt']);
    Route::get('/{reservation}/receipt/pdf', [StayController::class, 'receiptPdf']);
});

// DND needs an in-progress stay, so it keeps the in-room tier.
Route::middleware(['auth:guests', 'is_checked_in'])
    ->patch('/stays/active/dnd', [StayController::class, 'setDnd']);

// Authenticated guest — one-step booking + self-service
Route::middleware('auth:guests')->prefix('reservations')->group(function () {
    Route::post  ('/',               [ReservationController::class, 'store']);
    Route::get   ('/',               [ReservationController::class, 'index']);
    Route::get   ('/{reservation}',  [ReservationController::class, 'show']);
    Route::delete('/{reservation}',  [ReservationController::class, 'cancel']);
});

// ──────────────────────────────────────────────────────────────────────
// ──────────────────────────────────────────────────────────────────────
// P4 — Reservations: Admin management (per-action permissions)
// ──────────────────────────────────────────────────────────────────────
Route::middleware('auth:users')->prefix('cms/reservations')->group(function () {
    Route::middleware('permission:reservations.view')->group(function () {
        Route::get   ('/',              [AdminReservationController::class, 'index']);
        Route::get   ('/{reservation}', [AdminReservationController::class, 'show']);
    });
    Route::middleware('permission:reservations.create')->group(function () {
        // Front-desk booking — reception creating a reservation for a guest.
        Route::post  ('/',                          [AdminReservationController::class, 'store']);
        Route::post  ('/{reservation}/confirm',     [AdminReservationController::class, 'confirm']);
        Route::post  ('/{reservation}/assign-room', [AdminReservationController::class, 'assignRoom']);
    });
    Route::middleware('permission:reservations.cancel')->group(function () {
        Route::delete('/{reservation}',             [AdminReservationController::class, 'cancel']);
    });
    Route::middleware('permission:folios.settle')->group(function () {
        Route::post('/{reservation}/settle', [PaymentController::class, 'settleReservation']);
    });
});

Route::middleware('auth:users')->group(function () {
    // Staff management
    Route::get   ('/staff',                      [StaffController::class, 'index']);
    Route::post  ('/staff',                      [StaffController::class, 'store']);
    Route::get   ('/staff/{user}',               [StaffController::class, 'show']);
    Route::put   ('/staff/{user}',               [StaffController::class, 'update']);
    Route::post  ('/staff/{user}/permissions',   [StaffController::class, 'assignPermissions']);
    Route::patch ('/staff/{user}/deactivate',    [StaffController::class, 'deactivate']);

    // Reference lists
    Route::get('/permissions', [PermissionController::class, 'index']);
    Route::get('/roles',       [RoleController::class, 'index']);
});

// ──────────────────────────────────────────────────────────────────────
// P7 — Service layer: guest-side (tier-3, two-flag gate)
// ──────────────────────────────────────────────────────────────────────
Route::middleware(['auth:guests', 'has_booking'])->group(function () {
    Route::post('/service-bookings',     [ServiceBookingController::class, 'store']);
    Route::post('/pre-arrival/documents',[PreArrivalController::class, 'submitDocuments']);
    // Table reservation — same tier as any other service booking (ARCHITECTURE
    // §3.7): the backend picks the table from venue + party size + slot.
    Route::post('/dining-venues/{diningVenue}/table-reservations', [TableReservationController::class, 'store']);
});

Route::middleware(['auth:guests', 'is_checked_in'])->prefix('service-requests')->group(function () {
    Route::post('/', [ServiceRequestController::class, 'store']);
    Route::get ('/', [ServiceRequestController::class, 'index']);
});

// ──────────────────────────────────────────────────────────────────────
// P7 — Service layer: admin catalog CRUD (cms.edit) — no admin queue/assign
// routes here; the read+assign layer over service_requests/service_bookings
// belongs to P10 (OperationsQueueService), not P7.
// ──────────────────────────────────────────────────────────────────────
// Same read/write split as the P3 block above — index+show on cms.view (or
// cms.edit, which implies it), the mutating verbs on cms.edit.
Route::middleware('auth:users')->prefix('cms')->group(function () {

    Route::middleware('permission:cms.view|cms.edit')->group(function () {
        Route::apiResource('spa-services', SpaServiceController::class)->only(['index', 'show'])->parameters(['spa-services' => 'spaService']);
        Route::apiResource('restaurant-tables', RestaurantTableController::class)->only(['index', 'show'])->parameters(['restaurant-tables' => 'restaurantTable']);
        Route::apiResource('pool-cabanas', PoolCabanaController::class)->only(['index', 'show'])->parameters(['pool-cabanas' => 'poolCabana']);
        Route::apiResource('transfers', TransferController::class)->only(['index', 'show']);
        Route::apiResource('service-categories', AdminServiceCategoryController::class)->only(['index', 'show'])->parameters(['service-categories' => 'serviceCategory']);
        Route::apiResource('service-items', AdminServiceItemController::class)->only(['index', 'show'])->parameters(['service-items' => 'serviceItem']);
        Route::apiResource('menu-categories', MenuCategoryController::class)->only(['index', 'show'])->parameters(['menu-categories' => 'menuCategory']);
        Route::apiResource('menu-items', MenuItemController::class)->only(['index', 'show'])->parameters(['menu-items' => 'menuItem']);
    });

    Route::middleware('permission:cms.edit')->group(function () {
        Route::apiResource('spa-services', SpaServiceController::class)->except(['index', 'show'])->parameters(['spa-services' => 'spaService']);
        Route::apiResource('restaurant-tables', RestaurantTableController::class)->except(['index', 'show'])->parameters(['restaurant-tables' => 'restaurantTable']);
        Route::apiResource('pool-cabanas', PoolCabanaController::class)->except(['index', 'show'])->parameters(['pool-cabanas' => 'poolCabana']);
        Route::apiResource('transfers', TransferController::class)->except(['index', 'show']);
        Route::apiResource('service-categories', AdminServiceCategoryController::class)->except(['index', 'show'])->parameters(['service-categories' => 'serviceCategory']);
        Route::apiResource('service-items', AdminServiceItemController::class)->except(['index', 'show'])->parameters(['service-items' => 'serviceItem']);
        Route::apiResource('menu-categories', MenuCategoryController::class)->except(['index', 'show'])->parameters(['menu-categories' => 'menuCategory']);
        Route::apiResource('menu-items', MenuItemController::class)->except(['index', 'show'])->parameters(['menu-items' => 'menuItem']);
        Route::post  ('/menu-items/{menuItem}/images',         [MediaController::class, 'storeMenuItem']);
        Route::delete('/menu-items/{menuItem}/images/{media}', [MediaController::class, 'destroyMenuItem']);
        Route::post  ('/menu-items/{menuItem}/images/attach',   [MediaController::class, 'attachMenuItem']);
    });
});

// P7 — Pre-arrival check-in approvals (reservations.create — same tier as assign-room)
Route::middleware(['auth:users', 'permission:reservations.create'])->prefix('cms/check-in-approvals')->group(function () {
    Route::get  ('/',                        [CheckInApprovalController::class, 'index']);
    Route::patch('/{reservation}/approve',   [CheckInApprovalController::class, 'approve']);
});

// ──────────────────────────────────────────────────────────────────────
// P8 — Folios & Express Checkout: guest-side (is_checked_in — same tier as in-room services)
// ──────────────────────────────────────────────────────────────────────
Route::middleware(['auth:guests', 'is_checked_in'])->group(function () {
    Route::get ('/folio',                  [ApiFolioController::class, 'show']);
    Route::post('/folio/approve',          [ApiFolioController::class, 'approve']);
    Route::post('/transport-requests',     [TransportRequestController::class, 'store']);
});

// P8 — Folios: admin generate/settle (folios.view / folios.settle — seeded since P0, first consumed here)
Route::middleware('auth:users')->prefix('cms/folios')->group(function () {
    Route::middleware('permission:folios.view')->group(function () {
        Route::post('/{reservation}/generate', [AdminFolioController::class, 'generate']);
    });
    Route::middleware('permission:folios.settle')->group(function () {
        Route::post('/{folio}/settle', [AdminFolioController::class, 'settle']);
    });
});

// ──────────────────────────────────────────────────────────────────────
// P9 — Notifications & Chat: guest-side (tier-2, auth:guests only —
// ARCHITECTURE §3.7 places device registration + chat under any guest token)
// ──────────────────────────────────────────────────────────────────────
Route::middleware('auth:guests')->group(function () {
    Route::post('/device-tokens',                     [DeviceTokenController::class, 'store']);
    Route::get ('/conversations',                     [ApiConversationController::class, 'index']);
    Route::post('/conversations',                     [ApiConversationController::class, 'send']);
    Route::get ('/conversations/{conversation}/messages', [ApiConversationController::class, 'messages']);
});

// P9 — Chat: admin/staff side (tickets.view read, tickets.respond to reply — both seeded since P0)
Route::middleware('auth:users')->prefix('cms/conversations')->group(function () {
    Route::middleware('permission:tickets.view')->group(function () {
        Route::get('/',                         [AdminConversationController::class, 'index']);
        Route::get('/{conversation}/messages',  [AdminConversationController::class, 'messages']);
    });
    Route::middleware('permission:tickets.respond')->group(function () {
        Route::post('/{conversation}/messages', [AdminConversationController::class, 'reply']);
    });
});

// ──────────────────────────────────────────────────────────────────────
// P10 — Staff Ops Dashboard + Tickets: unified queue over service_requests
// + tickets. Assign/status permission is checked in-service per {type}
// (service-requests|tickets) since it differs per operation — see
// OperationsQueueService::requiredPermission().
// ──────────────────────────────────────────────────────────────────────
Route::middleware(['auth:users', 'permission:service_requests.view|tickets.view'])
    ->get('/operations/queue', [OperationsQueueController::class, 'index']);

Route::middleware('auth:users')->prefix('operations/queue/{type}/{uuid}')->group(function () {
    Route::patch('/assign', [OperationsQueueController::class, 'assign']);
    Route::patch('/status', [OperationsQueueController::class, 'updateStatus']);
});

Route::middleware('auth:users')->get('/dashboard/summary', [OperationsQueueController::class, 'summary']);
