part of 'booking_flow_controller.dart';

extension BookingConfirmActions on BookingFlowController {
  /// Confirms via `POST /reservations` (tier-2, token identity). Card/wallet
  /// methods have no gateway yet, so they're blocked here with a clear message
  /// rather than submitting. On success the real `booking_code` shows on the
  /// Confirmed screen.
  Future<void> confirmBooking() async {
    if (isConfirming.value) return;
    // Already enforced at the start of the flow; kept for a session that
    // expired while the guest was filling it in.
    if (!_requireAccount()) return;
    final apiMethod = paymentApiValue;
    if (apiMethod == null) {
      CustomSnackbars.showInfo(message: AppTranslations.cardWalletUnavailable);
      return;
    }
    // Points or a voucher were entered but not (yet) accepted by a preview:
    // booking now would silently charge the full price.
    if (hasLoyaltyInput && loyaltyPreview.value == null) {
      final pricing =
          loyaltyPricing.value || (_previewTimer?.isActive ?? false);
      CustomSnackbars.showInfo(
        message: pricing
            ? AppTranslations.loyaltyStillPricing
            : (loyaltyError.value ?? AppTranslations.loyaltyFixRewards),
      );
      return;
    }
    final uuid = selectedRoom.value?.uuid ?? '';
    if (uuid.isEmpty || !hasDates) {
      CustomSnackbars.showError(message: AppTranslations.selectRoomAndDates);
      return;
    }

    isConfirming.value = true;
    final body = <String, dynamic>{
      'room_type_uuid': uuid,
      'check_in': _fmtDate(rangeStart.value!),
      'check_out': _fmtDate(rangeEnd.value!),
      'payment_method': apiMethod,
      if (promoApplied.value && promoCtrl.text.trim().isNotEmpty)
        'promo_code': promoCtrl.text.trim(),
      ..._loyaltyFields,
    };
    // Only a booking that spends points or a voucher is replay-guarded, and the
    // server requires the key exactly then.
    String? idempotencyKey;
    if (_loyaltyFields.isNotEmpty) {
      final fingerprint = jsonEncode(body);
      if (_bookingKey == null || _bookingKeyBody != fingerprint) {
        _bookingKey = const Uuid().v4();
        _bookingKeyBody = fingerprint;
      }
      idempotencyKey = _bookingKey;
    }
    final res = await ApiService.find.post<Map<String, dynamic>>(
      path: '/reservations',
      data: body,
      idempotencyKey: idempotencyKey,
      showErrorDialog: false,
    );
    if (isClosed) return;
    isConfirming.value = false;
    // Whatever the server answered closes this attempt; only a lost connection
    // keeps the key, so the guest's retry is the same intent.
    if (res.ok || (res.error != null && !res.error!.isNetworkError)) {
      _bookingKey = null;
      _bookingKeyBody = null;
    }

    // 201 the first time, 200 when the same key is replayed.
    if (res.hasData) {
      final reservation = Reservation.fromJson(res.data!);
      lastReservation.value = reservation;
      confirmationCode.value = reservation.bookingCode;
      // A guest's own booking is created `pending`: the server counts it toward
      // `has_booking` (which unlocks the booked Home and `POST
      // /service-bookings`) only once the hotel confirms it. So the local
      // entitlement flips only for a reservation that is already confirmed.
      if (!awaitingConfirmation) {
        final guest = MiddlewareService.find.guest.value;
        if (guest != null) {
          MiddlewareService.find.updateGuest(guest.copyWith(hasBooking: true));
        }
        // Extras are booked here, not on the Add-Ons step: `POST
        // /service-bookings` is gated on `has_booking`.
        final failed = await _bookSelectedAddOns();
        if (isClosed) return;
        if (failed.isNotEmpty) {
          CustomSnackbars.showWarning(
            message: AppTranslations.addOnsNotBooked(
              failed.map((addOn) => addOn.title).join(', '),
            ),
          );
        }
      } else {
        // Home switches to the pre-arrival layout straight away; it reloads
        // the stay itself when the guest lands there.
        MiddlewareService.find.hasPendingBooking.value = true;
        // Posting now would only collect a 403 per extra; say when they can be
        // booked instead.
        final chosen = addOns
            .where((addOn) => selectedAddOnIds.contains(addOn.id))
            .map((addOn) => addOn.title)
            .toList();
        if (chosen.isNotEmpty) {
          CustomSnackbars.showInfo(
            message: AppTranslations.addOnsAfterConfirmation(chosen.join(', ')),
          );
        }
      }
      // The Stays list and Home were loaded before this booking existed.
      // Only a Stays controller that already exists: Stays is `lazyPut`, so
      // `Get.find` here would create it tied to this booking route, and popping
      // the route back to Main would delete it — TabController included —
      // while the Stays tab still holds it. One that doesn't exist yet loads
      // fresh when its tab first builds.
      if (Get.isRegistered<StaysController>() &&
          !Get.isPrepared<StaysController>()) {
        Get.find<StaysController>().reloadUpcoming();
      }
      if (Get.isRegistered<HomeController>()) {
        Get.find<HomeController>().reloadBooking();
      }
      Get.toNamed(Routes.bookingConfirmed);
      return;
    }

    final message = switch (res.error?.errorCode) {
      ErrorCodes.noAvailability => AppTranslations.datesSoldOut,
      ErrorCodes.invalidPromo => AppTranslations.promoInvalid,
      _ =>
        _loyaltyMessage(res.error) ??
            res.error?.message ??
            AppTranslations.bookingFailed,
    };
    CustomSnackbars.showError(message: message);
  }

  /// Copies the confirmation code to the clipboard (from the confirmed screen).
  void copyConfirmationCode() {
    final code = confirmationCode.value;
    if (code == null) return;
    Clipboard.setData(ClipboardData(text: code));
    CustomSnackbars.showSuccess(message: AppTranslations.copied);
  }

  /// Close on the confirmation screen — back to the shell. Going back one
  /// page would land on Review for a booking already made.
  void closeConfirmation() => Get.until((r) => r.isFirst);

  /// "View My Stays" from the confirmation screen — back to the shell on the
  /// Stays tab.
  void viewMyStays() {
    Get.until((r) => r.isFirst);
    Get.find<MainController>().changeTab(1);
    // A new booking is never "active" (that is a checked-in stay) — open the
    // Upcoming tab, where it is listed.
    if (Get.isRegistered<StaysController>() &&
        !Get.isPrepared<StaysController>()) {
      Get.find<StaysController>().tabController.animateTo(1);
    }
  }
}
