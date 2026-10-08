part of 'restaurant_controller.dart';

extension RestaurantReservation on RestaurantController {
  void selectTimeSlot(String slot) {
    timeSlot.value = slot;
  }

  void setGuests(int value) {
    guests.value = value;
  }

  /// The picker's branding (cream surface, primary header, gold today ring)
  /// comes from `Themes.theme`'s datePickerTheme — nothing to override here.
  Future<void> pickDate() async {
    final now = DateTime.now();
    final picked = await showDatePicker(
      context: Get.context!,
      // Clamp: the stored date can fall behind `now` if the screen is left open
      // past midnight, which would trip the initialDate assertion.
      initialDate: reserveDate.value.isBefore(now) ? now : reserveDate.value,
      firstDate: now,
      lastDate: now.add(const Duration(days: 365)),
    );
    if (picked != null) {
      reserveDate.value = picked;
    }
  }

  /// Reserves a table (`POST /dining-venues/{uuid}/table-reservations`,
  /// tier-3a). A guest with no booking is prompted to book first. The backend picks the table — we send
  /// only date/time/party size.
  Future<void> confirmReservation() async {
    // `time` is required and validated as `H:i` server-side, so an unset slot
    // would come back as a 422 the guest cannot act on. Say what is missing.
    if (timeSlot.value.isEmpty) {
      CustomSnackbars.showInfo(message: AppTranslations.tablePickTime);
      return;
    }
    if (!MiddlewareService.find.hasBooking) {
      CustomSnackbars.showInfo(message: AppTranslations.tableNeedsBooking);
      return;
    }
    final res = await ApiService.find.post<Map<String, dynamic>>(
      path: '/dining-venues/${restaurant.uuid}/table-reservations',
      data: {
        'date': reserveDate.value.formatApiDate(),
        'time': _toTime24h(timeSlot.value),
        'guest_count': guests.value,
        if (specialRequests.text.trim().isNotEmpty)
          'special_request': specialRequests.text.trim(),
      },
      showErrorDialog: false,
    );
    if (isClosed) return;
    if (res.statusCode == 201 && res.data != null) {
      final booking = ServiceBooking.fromJson(res.data!);
      final at = booking.scheduledAt;
      final label = [
        booking.label.isNotEmpty ? booking.label : '${guests.value}',
        // Hotel time, matching the slot the guest picked.
        if (at != null) DateFormat.jm().format(HotelTime.fromInstant(at)),
      ].join(' · ');
      CustomSnackbars.showSuccess(
        message: AppTranslations.tableReservedFor(label),
      );
      return;
    }
    switch (res.error?.errorCode) {
      case ErrorCodes.noAvailability:
        CustomSnackbars.showError(message: AppTranslations.tableNoAvailability);
      case ErrorCodes.noActiveReservation:
        CustomSnackbars.showError(message: AppTranslations.tableNeedsBooking);
      case ErrorCodes.notFound:
        CustomSnackbars.showError(
          message: AppTranslations.tableVenueUnavailable,
        );
      case ErrorCodes.validationFailed:
        CustomSnackbars.showError(
          message: res.error?.message ?? AppTranslations.tableFailed,
        );
      default:
        CustomSnackbars.showError(message: AppTranslations.tableFailed);
    }
  }

  /// Converts a 12-hour slot label ("7:00 PM", "12:30 PM") to the `H:i` (24h)
  /// the reservation endpoint expects.
  String _toTime24h(String slot) {
    final trimmed = slot.trim().toUpperCase();
    final isPm = trimmed.endsWith('PM');
    final isAm = trimmed.endsWith('AM');
    final body = trimmed.replaceAll(RegExp(r'\s*[AP]M$'), '').trim();
    final parts = body.split(':');
    var hour = int.tryParse(parts[0]) ?? 0;
    final minute = parts.length > 1 ? int.tryParse(parts[1]) ?? 0 : 0;
    if (isPm && hour != 12) hour += 12;
    if (isAm && hour == 12) hour = 0;
    return '${hour.toString().padLeft(2, '0')}:'
        '${minute.toString().padLeft(2, '0')}';
  }

  /// Download Full Menu (`GET /public/dining-venues/{uuid}/menu/download`).
  /// The endpoint returns a link, not the file, so the PDF or image opens in
  /// the phone's own viewer. A venue with no menu file answers 204 with an
  /// empty body — `ok` with no data.
  Future<void> downloadMenu() async {
    if (openingMenu.value) return;
    if (restaurant.uuid.isEmpty) {
      CustomSnackbars.showInfo(message: AppTranslations.menuNotAvailable);
      return;
    }
    openingMenu.value = true;
    final res = await ApiService.find.get<Map<String, dynamic>?>(
      path: '/public/dining-venues/${restaurant.uuid}/menu/download',
      showErrorDialog: false,
    );
    if (isClosed) return;
    openingMenu.value = false;

    if (res.hasData) {
      final uri = Uri.tryParse(res.data!['url'] as String? ?? '');
      final opened =
          uri != null &&
          await launchUrl(uri, mode: LaunchMode.externalApplication);
      if (!opened) {
        CustomSnackbars.showError(message: AppTranslations.menuOpenFailed);
      }
      return;
    }
    if (res.ok) {
      CustomSnackbars.showInfo(message: AppTranslations.menuNotAvailable);
      return;
    }
    if (res.error?.errorCode == ErrorCodes.notFound) {
      CustomSnackbars.showError(message: AppTranslations.tableVenueUnavailable);
      return;
    }
    if (res.error != null) ApiService.find.dialogs.showError(res.error!);
  }
}
