part of 'booking_flow_controller.dart';

extension BookingAddOnActions on BookingFlowController {
  /// Loads the Add-Ons step's catalogue from the three public bookable lists.
  ///
  /// Each is a separate endpoint with its own resource shape, so they are mapped
  /// into one [AddOn] list here rather than at three call sites. A list that
  /// fails is simply absent — one unreachable endpoint must not blank the
  /// other two, and the step is skippable by design.
  Future<void> loadAddOns() async {
    if (addOns.isNotEmpty || addOnsLoading.value) return;
    addOnsLoading.value = true;
    final results = await Future.wait([
      ApiService.find.get<List<dynamic>>(
        path: '/public/spa-services',
        queryParameters: {'per_page': 100},
        showErrorDialog: false,
      ),
      ApiService.find.get<List<dynamic>>(
        path: '/public/pool-cabanas',
        queryParameters: {'per_page': 100},
        showErrorDialog: false,
      ),
      ApiService.find.get<List<dynamic>>(
        path: '/public/transfers',
        queryParameters: {'per_page': 100},
        showErrorDialog: false,
      ),
    ]);
    if (isClosed) return;
    final next = <AddOn>[
      ..._mapBookables(
        results[0],
        type: 'spa_service',
        iconPath: 'assets/icons/jacuzzi.svg',
        subtitle: (json) {
          final minutes = (json['duration_minutes'] as num?)?.toInt();
          return minutes == null ? '' : AppTranslations.etaMinutes(minutes);
        },
      ),
      ..._mapBookables(
        results[1],
        type: 'pool_cabana',
        iconPath: 'assets/icons/view.svg',
        subtitle: (json) {
          final capacity = (json['capacity'] as num?)?.toInt();
          return capacity == null ? '' : AppTranslations.guestsCount(capacity);
        },
      ),
      ..._mapBookables(
        results[2],
        type: 'transfer',
        iconPath: 'assets/icons/location.svg',
        subtitle: (json) => '',
      ),
    ];
    addOns.assignAll(next);
    addOnsLoading.value = false;
  }

  /// Shared mapping for the three bookable lists, which differ only in their
  /// secondary field (duration / capacity / nothing).
  List<AddOn> _mapBookables(
    ApiResponse<List<dynamic>> response, {
    required String type,
    required String iconPath,
    required String Function(Map<String, dynamic> json) subtitle,
  }) {
    if (!response.hasData) return const [];
    return response.data!
        .whereType<Map<String, dynamic>>()
        .where((json) => json['is_active'] as bool? ?? true)
        .map(
          (json) => AddOn(
            id: json['uuid'] as String? ?? '',
            bookableType: type,
            iconPath: iconPath,
            title: Localized.fromJson(json['name']).value,
            subtitle: subtitle(json),
            priceUsd: json['price_usd']?.toString() ?? '0',
          ),
        )
        .where((addOn) => addOn.id.isNotEmpty)
        .toList();
  }

  /// Books every selected extra against the reservation that was just created.
  ///
  /// Deliberately runs *after* `POST /reservations` rather than on the Add-Ons
  /// step: `POST /service-bookings` sits behind the `has_booking` gate, so a
  /// guest with no reservation yet would get a 403 for every selection. Each
  /// booking is scheduled for the arrival date.
  ///
  /// Best-effort and silent: the room is already confirmed by this point, and
  /// failing the whole booking over an unavailable cabana would be worse than
  /// the guest re-requesting it from the Services tab. Returns the extras that
  /// did not go through so the caller can say so.
  Future<List<AddOn>> _bookSelectedAddOns() async {
    final selected = addOns
        .where((addOn) => selectedAddOnIds.contains(addOn.id))
        .toList();
    if (selected.isEmpty) return const [];
    final arrival = rangeStart.value;
    if (arrival == null) return selected;
    // The endpoint requires `after:now`; a same-day booking made this afternoon
    // would fail against midnight, so schedule for the hotel's check-in hour
    // and push to the next slot if that moment has already passed.
    var scheduled = DateTime(
      arrival.year,
      arrival.month,
      arrival.day,
      BookingFlowController._addOnScheduleHour,
    );
    // Hotel time: `scheduled` is a hotel wall-clock time, sent as such.
    final now = HotelTime.now();
    if (!scheduled.isAfter(now)) scheduled = now.add(const Duration(hours: 1));

    final failed = <AddOn>[];
    for (final addOn in selected) {
      final res = await ApiService.find.post<Map<String, dynamic>>(
        path: '/service-bookings',
        data: {
          'bookable_type': addOn.bookableType,
          'bookable_uuid': addOn.id,
          'scheduled_at': scheduled.toApiDateTime(),
        },
        showErrorDialog: false,
      );
      if (!res.ok) failed.add(addOn);
      if (isClosed) return failed;
    }
    return failed;
  }

  void toggleAddOn(String id) {
    // RxSet.remove/add notify on their own.
    if (!selectedAddOnIds.remove(id)) selectedAddOnIds.add(id);
  }

  void continueFromAddOns() {
    _prefillGuestFromProfile();
    Get.toNamed(Routes.guestDetails);
  }

  /// A signed-in guest's name, email and phone are already on their profile —
  /// fill them in rather than make them type it again. Only empty fields are
  /// filled, so going back and forward never overwrites what they edited.
  void _prefillGuestFromProfile() {
    final guest = MiddlewareService.find.guest.value;
    if (guest == null) return;
    void fill(TextEditingController field, String? value) {
      if (field.text.trim().isEmpty && (value ?? '').isNotEmpty) {
        field.text = value!;
      }
    }

    fill(firstNameCtrl, guest.firstName);
    fill(lastNameCtrl, guest.lastName);
    fill(emailCtrl, guest.email);
    final storedPhone = guest.phone ?? '';
    if (storedPhone.isNotEmpty && phone.nationalNumber.isEmpty) {
      phone.prefill(storedPhone, isoCountry: guest.phoneCountry);
    }
  }

  void continueFromGuest() {
    // if (!guestFormKey.currentState!.validate()) return;
    Get.toNamed(Routes.payment);
    // Price the stay for the Payment summary + Review breakdown.
    // Fire-and-forget: the summary repaints when the quote lands.
    _fetchQuote(promo: promoApplied.value ? promoCtrl.text.trim() : null);
  }
}
