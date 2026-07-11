<?php

use App\Http\Controllers\Admin\DiningVenueController as AdminDiningVenueController;
use App\Http\Controllers\Admin\EventInquiryController as AdminEventInquiryController;
use App\Http\Controllers\Admin\PaymentController;
use App\Http\Controllers\Api\EventInquiryController as ApiEventInquiryController;
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
