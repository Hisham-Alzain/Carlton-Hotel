import 'package:carlton/constants/demo_data.dart';
import 'package:carlton/customWidgets/custom_bottom_sheet.dart';
import 'package:carlton/customWidgets/custom_snackbar.dart';
import 'package:carlton/models/booking_models.dart';
import 'package:carlton/routes/routes.dart';
import 'package:carlton/views/book/room_details_sheet.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:intl/intl.dart';

/// Shared controller for the whole 5-step booking flow (Plan → Choose Room →
/// Add-Ons → Guest → Payment) plus the Room Details sheet. Registered once,
/// permanently, at app boot (see main.dart) instead of through a per-route
/// binding — every booking screen reads/writes and rebuilds off this same
/// instance via GetBuilder&lt;BookingFlowController&gt;. Stays a GetxController
/// (not GetxService) because GetxService doesn't support update()/GetBuilder
/// in this GetX version; the permanent, no-binding registration is what makes
/// it behave like a service. Being permanent (not tied to a route's fenix
/// lifecycle) also means [reset] is the only thing that clears it — callers
/// starting a new booking must call it explicitly (see
/// BookView/StaysController.startBooking), otherwise a completed booking's
/// guest/card details would carry into the next one.
class BookingFlowController extends GetxController {
  // Dates + guests
  final DateTime firstDay = DemoData.bookingFirstDay;
  final DateTime lastDay = DemoData.bookingLastDay;
  DateTime focusedDay = DemoData.bookingCheckIn;
  DateTime? rangeStart = DemoData.bookingCheckIn;
  DateTime? rangeEnd = DemoData.bookingCheckOut;
  int adults = DemoData.bookingAdults;
  int children = DemoData.bookingChildren;

  // Room + add-ons
  final List<RoomOption> rooms = DemoData.roomOptions;
  final List<AddOn> addOns = DemoData.addOns;
  RoomOption? selectedRoom;
  final Set<String> selectedAddOnIds = {};

  /// Which photo of [selectedRoom] the Room Details carousel is showing.
  int roomImageIndex = 0;

  // Guest
  final firstNameCtrl = TextEditingController();
  final lastNameCtrl = TextEditingController();
  final emailCtrl = TextEditingController();
  final phoneCtrl = TextEditingController();
  final specialRequestsCtrl = TextEditingController();
  String dialCode = '+963';

  // Payment
  PaymentMethod paymentMethod = PaymentMethod.card;
  final cardNumberCtrl = TextEditingController();
  final cardExpiryCtrl = TextEditingController();
  final cardCvvCtrl = TextEditingController();
  final cardNameCtrl = TextEditingController();
  final promoCtrl = TextEditingController();

  // ── Cross-screen derived values ─────────────────────────────────────────
  int get nights {
    if (rangeStart == null || rangeEnd == null) return 2;
    final n = rangeEnd!.difference(rangeStart!).inDays;
    return n < 1 ? 1 : n;
  }

  bool get hasDates => rangeStart != null && rangeEnd != null;

  /// "Aug 14 → Aug 16" — the date range without the nights suffix.
  String get dateRange {
    if (rangeStart == null || rangeEnd == null) return 'Select your dates';
    final f = DateFormat('MMM d');
    return '${f.format(rangeStart!)} → ${f.format(rangeEnd!)}';
  }

  /// "Aug 14 → Aug 16 · 2 nights" — used by the Plan footer and Choose Room
  /// context bar (Figma shows the nights there).
  String get dateSummary =>
      hasDates ? '$dateRange · $nights nights' : dateRange;

  String get guestSummary {
    final a = '$adults Adult${adults == 1 ? '' : 's'}';
    if (children == 0) return a;
    return '$a, $children Child${children == 1 ? '' : 'ren'}';
  }

  /// "Aug 14 → Aug 16 · \$280/night" — the Add-Ons summary tile (Figma omits
  /// the nights here, unlike [dateSummary]).
  String get roomDetailSummary {
    if (selectedRoom == null) return dateSummary;
    return '$dateRange · \$${selectedRoom!.pricePerNight}/night';
  }

  int get roomTotal => (selectedRoom?.pricePerNight ?? 0) * nights;

  int get extrasTotal => addOns
      .where((a) => selectedAddOnIds.contains(a.id))
      .fold(0, (sum, a) => sum + a.price);

  int get grandTotal => roomTotal + extrasTotal;

  // ── Step 1 — Plan Your Stay ──────────────────────────────────────────────
  void onRangeSelected(DateTime? start, DateTime? end, DateTime focused) {
    rangeStart = start;
    rangeEnd = end;
    focusedDay = focused;
    update();
  }

  void onPageChanged(DateTime focused) => focusedDay = focused;

  void setAdults(int v) {
    adults = v;
    update();
  }

  void setChildren(int v) {
    children = v;
    update();
  }

  /// Tapping a check-in/check-out box clears the range so the calendar is
  /// ready for a fresh pick.
  void restartDateSelection() {
    rangeStart = null;
    rangeEnd = null;
    update();
  }

  void searchRooms() {
    if (!hasDates) {
      CustomSnackbars.showInfo(message: 'Select your dates first');
      return;
    }
    Get.toNamed(Routes.chooseRoom);
  }

  // ── Step 2 — Choose Your Room + Room Details sheet ──────────────────────
  void setRoomImage(int index) {
    roomImageIndex = index;
    update();
  }

  void openRoomDetails(RoomOption room) {
    roomImageIndex = 0;
    CustomBottomSheet.show(content: RoomDetailsSheet(room: room));
  }

  /// Entry from the Home room list: open the full-screen details page.
  void openRoomDetailsScreen(RoomOption room) {
    roomImageIndex = 0;
    update();
    Get.toNamed(Routes.roomDetails, arguments: room);
  }

  /// "Select This Room" from the details screen — start a fresh booking with
  /// this room preselected. Resets first (like every other booking entry) so a
  /// prior booking's guest/card/add-on data never carries over.
  void beginBooking(RoomOption room) {
    reset();
    selectedRoom = room;
    update();
    Get.toNamed(Routes.planStay);
  }

  void selectRoom(RoomOption room) {
    selectedRoom = room;
    update();
    Get.toNamed(Routes.addOns);
  }

  // ── Step 3 — Add-Ons ─────────────────────────────────────────────────────
  String get addOnsCtaLabel {
    final n = selectedAddOnIds.length;
    return n == 0
        ? 'Skip — No Extras'
        : 'Continue with $n extra${n == 1 ? '' : 's'}';
  }

  void toggleAddOn(String id) {
    if (!selectedAddOnIds.remove(id)) selectedAddOnIds.add(id);
    update();
  }

  void continueFromAddOns() => Get.toNamed(Routes.guestDetails);

  // ── Step 4 — Guest Details ───────────────────────────────────────────────
  void continueFromGuest() => Get.toNamed(Routes.payment);

  // ── Step 5 — Payment ─────────────────────────────────────────────────────
  bool get isCardComplete =>
      cardNumberCtrl.text.trim().isNotEmpty &&
      cardExpiryCtrl.text.trim().isNotEmpty &&
      cardCvvCtrl.text.trim().isNotEmpty &&
      cardNameCtrl.text.trim().isNotEmpty;

  /// The Review Booking CTA is only enabled once payment details are valid.
  bool get canReviewBooking =>
      paymentMethod != PaymentMethod.card || isCardComplete;

  void selectPaymentMethod(PaymentMethod m) {
    paymentMethod = m;
    update();
  }

  void onPaymentFieldChanged() => update();

  void applyPromo() {
    if (promoCtrl.text.trim().isEmpty) {
      CustomSnackbars.showInfo(message: 'Enter a promo code first');
      return;
    }
    CustomSnackbars.showSuccess(
      message: 'Promo code "${promoCtrl.text.trim()}" applied',
    );
  }

  void reviewBooking() {
    if (!canReviewBooking) return;
    Get.until((r) => r.isFirst);
    CustomSnackbars.showSuccess(
      message: 'Booking confirmed for ${selectedRoom?.name ?? 'your stay'}',
    );
  }

  /// Clears every field back to its starting value — call before entering the
  /// flow for a new booking (not after finishing one), so "Book Again" never
  /// shows a previous attempt's guest/card details.
  void reset() {
    focusedDay = DemoData.bookingCheckIn;
    rangeStart = DemoData.bookingCheckIn;
    rangeEnd = DemoData.bookingCheckOut;
    adults = DemoData.bookingAdults;
    children = DemoData.bookingChildren;
    selectedRoom = null;
    roomImageIndex = 0;
    selectedAddOnIds.clear();
    firstNameCtrl.clear();
    lastNameCtrl.clear();
    emailCtrl.clear();
    phoneCtrl.clear();
    specialRequestsCtrl.clear();
    dialCode = '+963';
    paymentMethod = PaymentMethod.card;
    cardNumberCtrl.clear();
    cardExpiryCtrl.clear();
    cardCvvCtrl.clear();
    cardNameCtrl.clear();
    promoCtrl.clear();
  }

  @override
  void onClose() {
    for (final c in [
      firstNameCtrl,
      lastNameCtrl,
      emailCtrl,
      phoneCtrl,
      specialRequestsCtrl,
      cardNumberCtrl,
      cardExpiryCtrl,
      cardCvvCtrl,
      cardNameCtrl,
      promoCtrl,
    ]) {
      c.dispose();
    }
    super.onClose();
  }
}
