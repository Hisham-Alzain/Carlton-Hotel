part of 'home_controller.dart';

extension HomeBookingLoader on HomeController {
  Future<void> _loadActiveBooking() async {
    // Nothing to load while signed out — and hitting /stays/active without a
    // token 401s, which ErrorInterceptor escalates to signOut() plus a redirect
    // to Sign In. `showErrorDialog: false` silences the dialog, not the
    // interceptor, so this guard is what keeps a browsing guest on Home.
    if (!MiddlewareService.find.isAuthenticated) {
      _clearActiveBooking();
      bookingLoading.value = false;
      return;
    }

    final activeRes = await ApiService.find.get<Map<String, dynamic>?>(
      path: '/stays/active',
      showErrorDialog: false,
    );
    if (isClosed) return;
    if (activeRes.ok) {
      final active = activeRes.data != null
          ? ActiveStay.fromJson(activeRes.data!)
          : null;
      activeStay.value = active == null ? null : _toStay(active);
      // The server's DND state, so the switch never starts off while the
      // room is marked do-not-disturb.
      doNotDisturb.value = active?.dndEnabled ?? false;
    }
    // Not checked in: surface the upcoming reservation. A confirmed one drives
    // the pre-arrival dashboard; a `pending` one (every guest-made booking
    // until the hotel confirms it — the server does not count it toward
    // has_booking) is still shown on the explore Home, so a guest who just
    // booked sees their stay instead of an unchanged page.
    if (activeStay.value == null) {
      final upRes = await ApiService.find.get<List<dynamic>>(
        path: '/stays/upcoming',
        showErrorDialog: false,
      );
      if (isClosed) return;
      if (upRes.hasData) {
        final primary = UpcomingStay.primary(
          UpcomingStay.listFromJson(upRes.data),
        );
        upcomingStay.value = primary == null ? null : _upcomingToStay(primary);
        // Pending only when no upcoming booking is confirmed — a confirmed one
        // behind an earlier pending one still opens check-in and transfers.
        upcomingPending.value = primary?.isAwaitingHotel ?? false;
        MiddlewareService.find.hasPendingBooking.value = upcomingPending.value;
        // The pre-arrival hero reads the booking from CheckInService, which
        // otherwise loads it only when the check-in wizard opens.
        if (primary != null && Get.isRegistered<CheckInService>()) {
          CheckInService.find.loadReservation();
        }
      }
    } else {
      upcomingStay.value = null;
      upcomingPending.value = false;
      MiddlewareService.find.hasPendingBooking.value = false;
    }
    if (MiddlewareService.find.isCheckedIn) {
      final folioF = ApiService.find.get<Map<String, dynamic>>(
        path: '/folio',
        showErrorDialog: false,
      );
      final reqF = ApiService.find.get<List<dynamic>>(
        path: '/service-requests',
        showErrorDialog: false,
      );
      final folioRes = await folioF;
      final reqRes = await reqF;
      if (isClosed) return;
      if (folioRes.hasData) {
        final folio = Folio.fromJson(folioRes.data!);
        billLines.assignAll(
          folio.items.map(
            (i) => (i.description, HomeController._usd(i.amountUsd)),
          ),
        );
        billTotal.value = HomeController._usd(folio.totalUsd);
      }
      if (reqRes.hasData) {
        // The endpoint returns every request the guest ever made, latest
        // first; Home's "active" card keeps only the ones still open.
        activeRequests.assignAll(
          ServiceRequest.listFromJson(reqRes.data!).where(
            (r) => r.statusCode == 'new' || r.statusCode == 'in_progress',
          ),
        );
      }
    } else {
      // Checked out (or never checked in): the bill and in-stay requests are
      // no longer this guest's, so drop them rather than leaving them stale.
      billLines.clear();
      billTotal.value = HomeController._emptyBillTotal();
      activeRequests.clear();
    }
    bookingLoading.value = false;
  }

  /// Drops every stay-scoped value, so a signed-out guest — or the next guest
  /// to sign in on this device — never sees the previous one's cards.
  void _clearActiveBooking() {
    activeStay.value = null;
    upcomingStay.value = null;
    activeRequests.clear();
    billLines.clear();
    billTotal.value = HomeController._emptyBillTotal();
    doNotDisturb.value = false;
  }

  Stay _toStay(ActiveStay s) => Stay(
    id: s.uuid,
    uuid: s.uuid,
    roomName: s.roomName.value,
    status: StayStatus.active,
    subtitle: (s.roomNumber != null && s.roomNumber!.isNotEmpty)
        ? AppTranslations.stayRoomNumber('${s.roomNumber}')
        : null,
    imagePath: 'assets/images/stay_room.png',
    checkInLabel: s.checkIn != null
        ? HomeController._fullDate.format(s.checkIn!)
        : '',
    checkOutLabel: s.checkOut != null
        ? HomeController._fullDate.format(s.checkOut!)
        : '',
    nightsRemaining: s.nightsRemaining,
  );

  /// Maps an `/stays/upcoming` entry to the [Stay] the (pre-arrival) active-stay
  /// card renders: a short "Room N" badge, the room name, the date range, and
  /// the total nights shown in place of "nights left".
  Stay _upcomingToStay(UpcomingStay s) {
    final total = double.tryParse(s.priceUsd) ?? 0;
    final perNight = s.nights > 0 ? total / s.nights : total;
    return Stay(
      id: s.uuid,
      uuid: s.uuid,
      roomName: s.roomName.value,
      status: StayStatus.upcoming,
      // CustomUpcomingStayCard (the pending-booking card) force-unwraps
      // subtitle and pricePerNight, so neither may be left null.
      subtitle: (s.roomNumber != null && s.roomNumber!.isNotEmpty)
          ? AppTranslations.stayRoomNumber('${s.roomNumber}')
          : AppTranslations.receiptHotelName,
      imagePath: 'assets/images/stay_room.png',
      checkInLabel: s.checkIn != null
          ? HomeController._fullDate.format(s.checkIn!)
          : '',
      checkOutLabel: s.checkOut != null
          ? HomeController._fullDate.format(s.checkOut!)
          : '',
      nightsRemaining: s.nights,
      resCode: s.bookingCode,
      pricePerNight: AppTranslations.perNight(
        HomeController._usd(perNight.toString()),
      ),
      isCancellable: s.isCancellable,
    );
  }
}
