<?php

use App\Http\Controllers\Admin\CheckInApprovalController;
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
use App\Http\Controllers\Admin\RoomTypeController as AdminRoomTypeController;
use App\Http\Controllers\Api\AvailabilityController;
use App\Http\Controllers\Api\DiningVenueController as ApiDiningVenueController;
use App\Http\Controllers\Api\ReservationController;
use App\Http\Controllers\Api\EventSpaceController as ApiEventSpaceController;
use App\Http\Controllers\Api\FacilityController as ApiFacilityController;
use App\Http\Controllers\Api\PageController as ApiPageController;
use App\Http\Controllers\Api\PromotionController as ApiPromotionController;
use App\Http\Controllers\Api\RoomController as ApiRoomController;
use App\Http\Controllers\Api\RoomTypeController as ApiRoomTypeController;
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
    Route::post('/login', [StaffAuthController::class, 'login']);
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
            Route::get('/me', [GuestAuthController::class, 'me']);
        });
    });
});

// ──────────────────────────────────────────────────────────────────────
// P3 — CMS: Public read endpoints (no auth, is_active only)
// ──────────────────────────────────────────────────────────────────────
Route::prefix('public')->group(function () {
    Route::get('/room-types',             [ApiRoomTypeController::class,   'index']);
    Route::get('/room-types/{roomType}',  [ApiRoomTypeController::class,   'show']);
    Route::get('/rooms',                  [ApiRoomController::class,       'index']);
    Route::get('/rooms/{room}',           [ApiRoomController::class,       'show']);
    Route::get('/facilities',             [ApiFacilityController::class,   'index']);
    Route::get('/facilities/{facility}',  [ApiFacilityController::class,   'show']);
    Route::get('/dining-venues',              [ApiDiningVenueController::class, 'index']);
    Route::get('/dining-venues/{diningVenue}',[ApiDiningVenueController::class, 'show']);
    Route::get('/event-spaces',               [ApiEventSpaceController::class,  'index']);
    Route::get('/event-spaces/{eventSpace}',  [ApiEventSpaceController::class,  'show']);
    Route::get('/pages/{slug}',           [ApiPageController::class,       'show']);
    Route::get('/promotions',             [ApiPromotionController::class,  'index']);
    Route::get('/promotions/{promotion}', [ApiPromotionController::class,  'show']);

    // P4 — Availability & pricing (public)
    Route::get('/availability', [AvailabilityController::class, 'check']);
    Route::get('/quote',        [AvailabilityController::class, 'quote']);
});

// ──────────────────────────────────────────────────────────────────────
// P3 — CMS: Admin CRUD (cms.edit permission)
// ──────────────────────────────────────────────────────────────────────
Route::middleware(['auth:users', 'permission:cms.edit'])->prefix('cms')->group(function () {
    // Room types
    Route::get   ('/room-types',                                  [AdminRoomTypeController::class, 'index']);
    Route::post  ('/room-types',                                  [AdminRoomTypeController::class, 'store']);
    Route::get   ('/room-types/{roomType}',                       [AdminRoomTypeController::class, 'show']);
    Route::put   ('/room-types/{roomType}',                       [AdminRoomTypeController::class, 'update']);
    Route::delete('/room-types/{roomType}',                       [AdminRoomTypeController::class, 'destroy']);
    Route::post  ('/room-types/{roomType}/images',                [MediaController::class, 'storeRoomType']);
    Route::delete('/room-types/{roomType}/images/{media}',        [MediaController::class, 'destroyRoomType']);

    // Rooms
    Route::get   ('/rooms',                                       [AdminRoomController::class, 'index']);
    Route::post  ('/rooms',                                       [AdminRoomController::class, 'store']);
    Route::get   ('/rooms/{room}',                                [AdminRoomController::class, 'show']);
    Route::put   ('/rooms/{room}',                                [AdminRoomController::class, 'update']);
    Route::delete('/rooms/{room}',                                [AdminRoomController::class, 'destroy']);
    Route::post  ('/rooms/{room}/images',                         [MediaController::class, 'storeRoom']);
    Route::delete('/rooms/{room}/images/{media}',                 [MediaController::class, 'destroyRoom']);

    // Facilities
    Route::get   ('/facilities',                                  [AdminFacilityController::class, 'index']);
    Route::post  ('/facilities',                                  [AdminFacilityController::class, 'store']);
    Route::get   ('/facilities/{facility}',                       [AdminFacilityController::class, 'show']);
    Route::put   ('/facilities/{facility}',                       [AdminFacilityController::class, 'update']);
    Route::delete('/facilities/{facility}',                       [AdminFacilityController::class, 'destroy']);
    Route::post  ('/facilities/{facility}/images',                [MediaController::class, 'storeFacility']);
    Route::delete('/facilities/{facility}/images/{media}',        [MediaController::class, 'destroyFacility']);

    // Dining venues
    Route::get   ('/dining-venues',                               [AdminDiningVenueController::class, 'index']);
    Route::post  ('/dining-venues',                               [AdminDiningVenueController::class, 'store']);
    Route::get   ('/dining-venues/{diningVenue}',                 [AdminDiningVenueController::class, 'show']);
    Route::put   ('/dining-venues/{diningVenue}',                 [AdminDiningVenueController::class, 'update']);
    Route::delete('/dining-venues/{diningVenue}',                 [AdminDiningVenueController::class, 'destroy']);
    Route::post  ('/dining-venues/{diningVenue}/images',          [MediaController::class, 'storeDiningVenue']);
    Route::delete('/dining-venues/{diningVenue}/images/{media}',  [MediaController::class, 'destroyDiningVenue']);

    // Event spaces
    Route::get   ('/event-spaces',                                [AdminEventSpaceController::class, 'index']);
    Route::post  ('/event-spaces',                                [AdminEventSpaceController::class, 'store']);
    Route::get   ('/event-spaces/{eventSpace}',                   [AdminEventSpaceController::class, 'show']);
    Route::put   ('/event-spaces/{eventSpace}',                   [AdminEventSpaceController::class, 'update']);
    Route::delete('/event-spaces/{eventSpace}',                   [AdminEventSpaceController::class, 'destroy']);
    Route::post  ('/event-spaces/{eventSpace}/images',            [MediaController::class, 'storeEventSpace']);
    Route::delete('/event-spaces/{eventSpace}/images/{media}',    [MediaController::class, 'destroyEventSpace']);

    // Pages
    Route::get   ('/pages',                                       [AdminPageController::class, 'index']);
    Route::post  ('/pages',                                       [AdminPageController::class, 'store']);
    Route::get   ('/pages/{page}',                                [AdminPageController::class, 'show']);
    Route::put   ('/pages/{page}',                                [AdminPageController::class, 'update']);
    Route::delete('/pages/{page}',                                [AdminPageController::class, 'destroy']);

    // Promotions
    Route::get   ('/promotions',                                  [AdminPromotionController::class, 'index']);
    Route::post  ('/promotions',                                  [AdminPromotionController::class, 'store']);
    Route::get   ('/promotions/{promotion}',                      [AdminPromotionController::class, 'show']);
    Route::put   ('/promotions/{promotion}',                      [AdminPromotionController::class, 'update']);
    Route::delete('/promotions/{promotion}',                      [AdminPromotionController::class, 'destroy']);
    Route::post  ('/promotions/{promotion}/images',               [MediaController::class, 'storePromotion']);
    Route::delete('/promotions/{promotion}/images/{media}',       [MediaController::class, 'destroyPromotion']);

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
Route::middleware(['auth:users', 'permission:cms.edit'])->prefix('cms')->group(function () {
    Route::apiResource('spa-services', SpaServiceController::class)->parameters(['spa-services' => 'spaService']);
    Route::apiResource('restaurant-tables', RestaurantTableController::class)->parameters(['restaurant-tables' => 'restaurantTable']);
    Route::apiResource('pool-cabanas', PoolCabanaController::class)->parameters(['pool-cabanas' => 'poolCabana']);
    Route::apiResource('transfers', TransferController::class);
    Route::apiResource('menu-categories', MenuCategoryController::class)->parameters(['menu-categories' => 'menuCategory']);
    Route::apiResource('menu-items', MenuItemController::class)->parameters(['menu-items' => 'menuItem']);
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
