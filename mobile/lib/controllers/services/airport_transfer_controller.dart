import 'package:carlton/constants/error_codes.dart';
import 'package:carlton/constants/hotel_time.dart';
import 'package:carlton/components/check_in/arrival_time_sheet.dart';
import 'package:carlton/customWidgets/custom_snackbar.dart';
import 'package:carlton/extensions/date_extension.dart';
import 'package:carlton/l10n/app_translations.dart';
import 'package:carlton/models/transfer.dart';
import 'package:carlton/services/api/api_service.dart';
import 'package:carlton/services/middleware_service.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';

/// Drives the three-step airport-transfer sheet (Figma frames "Airport
/// Transfer", steps 1–3).
///
/// The whole flow shares ONE controller rather than one per sheet so that
/// `Back` from Select Vehicle or Confirm returns to a step that still holds the
/// guest's entries — a per-sheet controller would be disposed on pop and lose
/// the flight number.
class AirportTransferController extends GetxController {
  static AirportTransferController get find => Get.find();

  /// Terminal options. Not localized — "T1" is signage at the airport, the same
  /// glyphs in every language.
  static const List<String> terminals = ['T1', 'T2', 'T3'];

  /// Cap from the design's "max 7 passengers". A vehicle with a smaller
  /// [Transfer.maxPassengers] tightens it further once the API sends one.
  static const int maxPassengers = 7;

  /// The instructions share `notes` (server max 1000 characters) with the
  /// flight, terminal and party-size prefix, so they are capped well below it
  /// — past the limit the booking would fail with a 422 the guest cannot read.
  static const int maxInstructionsLength = 500;

  final flightNumberController = TextEditingController();
  final notesController = TextEditingController();

  /// 0 = flight details, 1 = select vehicle, 2 = confirm.
  final RxInt step = 0.obs;

  final Rx<DateTime> date = Rx(DateTime.now().add(const Duration(days: 1)));
  final Rx<TimeOfDay> arrivalTime = Rx(const TimeOfDay(hour: 12, minute: 0));
  final RxInt terminalIndex = 0.obs;
  final RxInt passengers = 1.obs;

  final RxList<Transfer> transfers = <Transfer>[].obs;
  final Rx<Transfer?> selected = Rx<Transfer?>(null);
  final RxBool loading = false.obs;
  final RxBool loadFailed = false.obs;
  final RxBool submitting = false.obs;

  @override
  void onClose() {
    flightNumberController.dispose();
    notesController.dispose();
    super.onClose();
  }

  String? get notOpenReason => MiddlewareService.find.hasPendingBooking.value
      ? AppTranslations.transferAwaitingConfirmation
      : null;

  // ── Step 1 ────────────────────────────────────────────────────────────────

  void setTerminal(int index) {
    terminalIndex.value = index;
  }

  void setPassengers(int value) {
    passengers.value = value;
    // A vehicle chosen earlier may no longer seat the party; drop it rather
    // than carry a too-small car into the summary.
    if (selected.value != null && !_seats(selected.value!, value)) {
      selected.value = null;
    }
  }

  /// Branding for both pickers comes from `Themes.theme` — nothing to override
  /// here. Mirrors `RestaurantController.pickDate`, including the clamp that
  /// stops a stale date tripping the `initialDate` assertion.
  Future<void> pickDate() async {
    final now = DateTime.now();
    final picked = await showDatePicker(
      context: Get.context!,
      initialDate: date.value.isBefore(now) ? now : date.value,
      firstDate: now,
      lastDate: now.add(const Duration(days: 365)),
    );
    if (picked == null || isClosed) return;
    date.value = picked;
  }

  /// Every hour of the day — flights land around the clock, so unlike
  /// check-in's afternoon-to-late-night slots nothing is left out.
  static final List<int> arrivalHours = List.generate(24, (hour) => hour);

  /// The same picker check-in uses, so the guest meets one arrival-time
  /// control across the app, but offering the whole day.
  Future<void> pickArrivalTime() => showArrivalSlotPicker(
    hours: arrivalHours,
    initialHour: arrivalTime.value.minute == 0 ? arrivalTime.value.hour : null,
    // Check-in's own subtitle ("Check-in opens from 2:00 PM") is about the
    // room, not the flight.
    title: AppTranslations.arrivalTime,
    subtitle: AppTranslations.transferArrivalSubtitle,
    onConfirm: (hour) {
      if (isClosed) return;
      arrivalTime.value = TimeOfDay(hour: hour, minute: 0);
    },
  );

  /// `12:00 PM` — formatted through MaterialLocalizations so it follows the
  /// locale's 12/24-hour convention instead of being hardcoded to the design's.
  String get arrivalTimeLabel => arrivalTime.value.format(Get.context!);

  /// `2026-08-14 at 14:30` — the summary line on step 3.
  String get dateTimeLabel =>
      '${date.value.formatApiDate()} ${AppTranslations.at} $arrivalTimeLabel';

  String get terminal => terminals[terminalIndex.value];

  // Summary values, shared by step 3 and the booking notes the hotel reads.
  String get flightNumberLabel {
    final flight = flightNumberController.text.trim();
    return flight.isEmpty ? AppTranslations.notSpecified : flight;
  }

  String get terminalLabel => AppTranslations.terminalValue(terminal);

  String get passengersLabel =>
      AppTranslations.passengerCount(passengers.value);

  // ── Step 2 ────────────────────────────────────────────────────────────────

  /// Loads `GET /public/transfers` — the bookables list, no auth needed.
  ///
  /// The endpoint returns the paginated `{items, meta}` envelope;
  /// [ApiResponse] unwraps `items` for us, so `List<dynamic>` is the right
  /// type parameter here despite the wrapper.
  ///
  /// Errors surface as an inline retry state rather than a dialog — the sheet
  /// is already a modal, and stacking another on top of it is hostile.
  Future<void> loadTransfers() async {
    if (transfers.isNotEmpty || loading.value) return;
    loading.value = true;
    loadFailed.value = false;

    final res = await ApiService.find.get<List<dynamic>>(
      path: '/public/transfers',
      // Paged server-side (15 by default); one sheet shows them all.
      queryParameters: {'per_page': 100},
      showErrorDialog: false,
    );
    if (isClosed) return;

    loading.value = false;
    if (!res.hasData) {
      loadFailed.value = true;
    } else {
      transfers.assignAll(Transfer.listFromJson(res.data!));
    }
  }

  /// True when [t] can seat [count]. A transfer with no declared capacity is
  /// treated as able — the API does not send `max_passengers` yet, and hiding
  /// every vehicle would leave the step empty.
  bool _seats(Transfer t, int count) =>
      t.maxPassengers == null || t.maxPassengers! >= count;

  bool seatsParty(Transfer t) => _seats(t, passengers.value);

  /// Car artwork for a transfer (Figma vehicle images). The API has no image
  /// field, so the class is read off the name — "(SUV)", "Shuttle", "Van" —
  /// and anything unrecognised gets the saloon.
  static String vehicleImage(Transfer t) {
    final name = (t.name.values['en'] ?? t.name.value).toLowerCase();
    if (RegExp(r'van|shuttle|group|bus').hasMatch(name)) {
      return 'assets/images/transfer_van.png';
    }
    if (RegExp(r'suv|luxury|vip|limo').hasMatch(name)) {
      return 'assets/images/transfer_luxury.png';
    }
    if (RegExp(r'business|sedan|saloon').hasMatch(name)) {
      return 'assets/images/transfer_business.png';
    }
    return 'assets/images/transfer_economy.png';
  }

  void selectTransfer(Transfer t) {
    selected.value = t;
  }

  // ── Navigation ────────────────────────────────────────────────────────────

  void goTo(int next) {
    step.value = next;
  }

  void back() {
    if (step.value > 0) goTo(step.value - 1);
  }

  void toVehicles() {
    goTo(1);
    loadTransfers();
  }

  /// Whether step 2 is satisfied. A pure predicate so the guard is testable
  /// without a widget binding, and so the view can grey the CTA rather than
  /// only explaining the refusal after the tap.
  bool get canConfirm => selected.value != null;

  void toConfirm() {
    if (!canConfirm) {
      CustomSnackbars.showInfo(message: AppTranslations.selectVehicleFirst);
      return;
    }
    goTo(2);
  }

  /// Books the chosen car as a service booking (`POST /service-bookings`,
  /// `bookable_type: transfer`) scheduled for the flight's arrival. The flight,
  /// terminal and party size have no fields of their own on that endpoint, so
  /// they travel in `notes` ahead of the guest's own instructions — which is
  /// where the concierge team reads them.
  Future<void> confirm() async {
    final transfer = selected.value;
    if (transfer == null || submitting.value) return;

    final arrival = DateTime(
      date.value.year,
      date.value.month,
      date.value.day,
      arrivalTime.value.hour,
      arrivalTime.value.minute,
    );
    if (!arrival.isAfter(HotelTime.now())) {
      CustomSnackbars.showError(message: AppTranslations.transferTimeInPast);
      goTo(0);
      return;
    }

    final instructions = notesController.text.trim();
    final notes = [
      '${AppTranslations.flightLabel}: $flightNumberLabel',
      terminalLabel,
      passengersLabel,
      if (instructions.isNotEmpty) instructions,
    ].join(' · ');

    submitting.value = true;
    final res = await ApiService.find.post<Map<String, dynamic>>(
      path: '/service-bookings',
      data: {
        'bookable_type': 'transfer',
        'bookable_uuid': transfer.uuid,
        'scheduled_at': arrival.toApiDateTime(),
        'notes': notes,
      },
      showErrorDialog: false,
    );
    if (isClosed) return;
    submitting.value = false;
    // On failure the sheet stays open on step 3 so nothing entered is lost.
    if (!res.ok) {
      switch (res.error?.errorCode) {
        // Tier gate: transfers open once the hotel confirms the booking.
        case ErrorCodes.noActiveReservation:
          CustomSnackbars.showInfo(
            message: AppTranslations.transferNeedsConfirmed,
          );
        // The car was withdrawn meanwhile: reload the list.
        case ErrorCodes.notFound:
          CustomSnackbars.showInfo(
            message: AppTranslations.transferUnavailable,
          );
          transfers.clear();
          selected.value = null;
          goTo(1);
          loadTransfers();
        default:
          if (res.error != null) ApiService.find.dialogs.showError(res.error!);
      }
      return;
    }

    // The guest may have closed the sheet while the request was in flight;
    // popping then would close the page underneath instead.
    if (Get.isBottomSheetOpen ?? false) Get.back();
    CustomSnackbars.showSuccess(message: AppTranslations.transferBooked);
    _resetForm();
  }

  /// A fresh form for the next request once one has gone through.
  void _resetForm() {
    flightNumberController.clear();
    notesController.clear();
    terminalIndex.value = 0;
    passengers.value = 1;
    selected.value = null;
    step.value = 0;
  }
}
