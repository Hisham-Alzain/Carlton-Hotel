import 'package:carlton/constants/demo_data.dart';
import 'package:carlton/customWidgets/custom_dialogs.dart';
import 'package:carlton/customWidgets/custom_snackbar.dart';
import 'package:carlton/enums/enums.dart';
import 'package:carlton/models/reservation.dart';
import 'package:carlton/models/service_item.dart';
import 'package:carlton/models/service_request.dart';
import 'package:carlton/services/session_service.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';

class ServicesController extends GetxController
    with GetSingleTickerProviderStateMixin {
  // Drives the themed Material TabBar (All Services / Active Requests).
  late final TabController tabController;
  int tabIndex = 0;

  // Reflects the real session on entry; tap the avatar in the app bar to
  // preview the other state on top of it (demo-only).
  bool isLoggedIn = SessionService.isSignedIn;

  // Set from the pending stash once the user is signed in; null for guests
  // and signed-in users with no current booking.
  Reservation? reservation;
  bool get hasReservation => reservation != null;

  /// The three distinct home states the screen renders. A booking always wins
  /// (it implies a signed-in account → stay card); otherwise a signed-in user
  /// sees the Explore & Book prompt and a guest sees the sign-in banner.
  ServicesHomeState get homeState {
    if (hasReservation) return ServicesHomeState.activeStay;
    if (isLoggedIn) return ServicesHomeState.exploreAndBook;
    return ServicesHomeState.guestBrowse;
  }

  // Demo data (see constants/demo_data.dart) until a real API exists.
  final String room = DemoData.room;
  final String checkedInTime = DemoData.checkedInTime;
  final int nightsRemaining = DemoData.nightsRemaining;
  final String stayImagePath = DemoData.stayImagePath;
  final List<ServiceItem> services = DemoData.services;
  final List<ServiceRequest> activeRequests = DemoData.initialActiveRequests();

  @override
  void onInit() {
    super.onInit();
    final pending = SessionService.pendingReservation;
    if (isLoggedIn && pending != null) {
      // The user verified the phone on their reservation and is now signed in;
      // attach that booking and drop the pending stash. This is the only way a
      // reservation reaches Services, so a booking always implies a signed-in
      // account (never a "guest with a reservation").
      reservation = pending;
      SessionService.markHasReservation();
      SessionService.clearPendingReservation();
    }
    tabController = TabController(length: 2, vsync: this);
    tabController.addListener(() {
      if (tabIndex != tabController.index) {
        tabIndex = tabController.index;
        update();
      }
    });
  }

  @override
  void onClose() {
    tabController.dispose();
    super.onClose();
  }

  void switchTab(int index) => tabController.animateTo(index);

  void quickRequest(String label) =>
      CustomSnackbars.showSuccess(message: '$label requested');

  void toggleLogin() {
    isLoggedIn = !isLoggedIn;
    update();
  }

  void editRequest(ServiceRequest request) {
    CustomSnackbars.showInfo(message: 'Editing "${request.title}"');
  }

  void cancelRequest(ServiceRequest request) {
    CustomDialogs.showCancelReasonDialog(
      title: 'Cancel Request',
      hintText: 'Reason (optional)',
      onConfirm: (reason) {
        activeRequests.remove(request);
        update();
        CustomSnackbars.showSuccess(message: 'Request cancelled');
      },
    );
  }
}
