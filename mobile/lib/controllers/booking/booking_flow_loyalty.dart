part of 'booking_flow_controller.dart';

extension BookingLoyaltyActions on BookingFlowController {
  /// Reads the balance and the programme switches for the Review screen's
  /// rewards panel. Silent: without it the panel simply does not show.
  Future<void> loadLoyaltyAccount() async {
    if (!MiddlewareService.find.isAuthenticated) return;
    final res = await ApiService.find.get<Map<String, dynamic>>(
      path: '/loyalty/account',
      showErrorDialog: false,
    );
    if (isClosed || !res.hasData) return;
    loyaltyAccount.value = LoyaltyAccount.fromJson(res.data!);
  }

  void setLoyaltyMode(String mode) {
    _previewTimer?.cancel();
    loyaltyMode.value = mode;
    pointsCtrl.clear();
    voucherCtrl.clear();
    _clearLoyaltyPreview();
  }

  /// Prices the rewards once typing pauses: the preview is throttled to 30 a
  /// minute, so it must not fire on every keystroke.
  void onLoyaltyInputChanged(String _) {
    _previewTimer?.cancel();
    _clearLoyaltyPreview();
    _previewTimer = Timer(const Duration(milliseconds: 500), previewLoyalty);
  }

  /// Drops the current preview. Also clears the spinner: a request still in
  /// flight is now stale and will discard its answer without touching it.
  void _clearLoyaltyPreview() {
    _previewSeq++;
    loyaltyPreview.value = null;
    loyaltyError.value = null;
    loyaltyPricing.value = false;
  }

  /// Something the price depends on (room, dates, promo) changed, so a preview
  /// priced for the old values is wrong: price the same rewards again.
  void _repriceLoyalty() {
    _previewTimer?.cancel();
    _clearLoyaltyPreview();
    if (hasLoyaltyInput) previewLoyalty();
  }

  /// The guest chose points or a voucher and typed something for it.
  bool get hasLoyaltyInput => switch (loyaltyMode.value) {
    'points' => pointsCtrl.text.trim().isNotEmpty,
    'voucher' => voucherCtrl.text.trim().isNotEmpty,
    _ => false,
  };

  /// The inputs the preview accepted, sent unchanged so the booking's total
  /// matches it. The server prices the booking; no discount or total is sent.
  Map<String, dynamic> get _loyaltyFields {
    final preview = loyaltyPreview.value;
    if (preview == null) return const {};
    if (preview.hasPointsDiscount) {
      return {'loyalty_points': preview.pointsRedeemed};
    }
    if (preview.hasVoucher) return {'voucher_code': voucherCtrl.text.trim()};
    return const {};
  }

  /// The inline message for a loyalty refusal, or null for any other error.
  String? _loyaltyMessage(ApiException? error) {
    if (error == null) return null;
    final context = error.context;
    return switch (error.errorCode) {
      ErrorCodes.loyaltyBelowMinimum => AppTranslations.loyaltyBelowMinimum(
        BookingFlowController._points(context['min_redeem_points']),
      ),
      ErrorCodes.loyaltyOverCap => AppTranslations.loyaltyOverCap(
        BookingFlowController._points(context['max_points']),
      ),
      ErrorCodes.loyaltyInsufficientPoints => AppTranslations.loyaltyOnlyHave(
        BookingFlowController._points(context['available_points']),
      ),
      ErrorCodes.loyaltyVoucherInvalid => AppTranslations.loyaltyVoucherInvalid,
      ErrorCodes.loyaltyProgramInactive => AppTranslations.loyaltyPointsOff,
      ErrorCodes.loyaltyDiscountConflict =>
        AppTranslations.loyaltyPointsOrVoucher,
      _ => null,
    };
  }

  void _resetLoyalty() {
    _previewTimer?.cancel();
    loyaltyAccount.value = null;
    loyaltyMode.value = 'none';
    pointsCtrl.clear();
    voucherCtrl.clear();
    _clearLoyaltyPreview();
    _bookingKey = null;
    _bookingKeyBody = null;
  }

  /// `GET /loyalty/preview`: what the points or voucher take off, and the net
  /// total `POST /reservations` will carry for the same inputs.
  Future<void> previewLoyalty() async {
    final uuid = selectedRoom.value?.uuid ?? '';
    if (uuid.isEmpty || !hasDates) return;
    final mode = loyaltyMode.value;
    final pointsText = pointsCtrl.text.trim();
    final points = int.tryParse(pointsText);
    final voucher = voucherCtrl.text.trim();
    final usePoints =
        loyaltyMode.value == 'points' && points != null && points > 0;
    final useVoucher =
        loyaltyMode.value == 'voucher' &&
        BookingFlowController.isCompleteVoucherCode(voucher);
    if (!usePoints && !useVoucher) return;

    final seq = ++_previewSeq;
    loyaltyPricing.value = true;
    final res = await ApiService.find.get<Map<String, dynamic>>(
      path: '/loyalty/preview',
      queryParameters: {
        'room_type_uuid': uuid,
        'check_in': _fmtDate(rangeStart.value!),
        'check_out': _fmtDate(rangeEnd.value!),
        if (promoApplied.value && promoCtrl.text.trim().isNotEmpty)
          'promo_code': promoCtrl.text.trim(),
        if (usePoints) 'loyalty_points': points,
        if (useVoucher) 'voucher_code': voucher,
      },
      showErrorDialog: false,
    );
    if (isClosed) return;
    // The guest changed the mode, the input, or the room/dates/promo (a newer
    // request or a clear bumped the sequence) while this was in flight: a
    // newer preview (or none) owns the panel, so this answer is dropped rather
    // than restoring a discount the booking would then send.
    if (seq != _previewSeq ||
        loyaltyMode.value != mode ||
        pointsCtrl.text.trim() != pointsText ||
        voucherCtrl.text.trim() != voucher) {
      return;
    }
    loyaltyPricing.value = false;
    if (res.hasData) {
      loyaltyPreview.value = LoyaltyPreview.fromJson(res.data!);
      loyaltyError.value = null;
      return;
    }
    loyaltyPreview.value = null;
    loyaltyError.value = _loyaltyMessage(res.error) ?? res.error?.message;
  }
}
