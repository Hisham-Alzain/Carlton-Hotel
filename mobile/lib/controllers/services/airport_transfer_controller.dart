import 'package:carlton/customWidgets/custom_snackbar.dart';
import 'package:carlton/extensions/date_extension.dart';
import 'package:carlton/l10n/app_translations.dart';
import 'package:carlton/models/transfer.dart';
import 'package:carlton/services/api/api_service.dart';
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

  @override
  void onClose() {
    flightNumberController.dispose();
    notesController.dispose();
    super.onClose();
  }

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

  Future<void> pickArrivalTime() async {
    final picked = await showTimePicker(
      context: Get.context!,
      initialTime: arrivalTime.value,
    );
    if (picked == null || isClosed) return;
    arrivalTime.value = picked;
  }

  /// `12:00 PM` — formatted through MaterialLocalizations so it follows the
  /// locale's 12/24-hour convention instead of being hardcoded to the design's.
  String get arrivalTimeLabel => arrivalTime.value.format(Get.context!);

  /// `2026-08-14 at 14:30` — the summary line on step 3.
  String get dateTimeLabel =>
      '${date.value.formatApiDate()} ${AppTranslations.at} $arrivalTimeLabel';

  String get terminal => terminals[terminalIndex.value];

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
      showErrorDialog: false,
    );
    if (isClosed) return;

    loading.value = false;
    if (res.statusCode != 200 || res.data == null) {
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

  /// Submission is not wired: `POST /transport-requests` accepts only a free
  /// text `notes` field, so none of the flight/terminal/vehicle data collected
  /// here could be stored. Kept as an explicit dead end rather than silently
  /// posting a summary string the hotel cannot act on.
  void confirm() {
    CustomSnackbars.showInfo(message: AppTranslations.transferComingSoon);
    Get.back();
  }
}
