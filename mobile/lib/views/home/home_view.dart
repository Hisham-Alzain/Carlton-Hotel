import 'package:carlton/components/cards/custom_discover_card.dart';
import 'package:carlton/components/sheets/airport_transfer_sheet.dart';
import 'package:carlton/components/cards/custom_home_card.dart';
import 'package:carlton/components/custom_info_banner.dart';
import 'package:carlton/components/custom_home_container.dart';
import 'package:carlton/components/home/custom_active_booking_card.dart';
import 'package:carlton/components/home/custom_active_requests_card.dart';
import 'package:carlton/components/home/custom_ai_concierge_banner.dart';
import 'package:carlton/components/home/custom_current_bill_card.dart';
import 'package:carlton/components/home/pre_arrival_sections.dart';
import 'package:carlton/constants/app_assets.dart';
import 'package:carlton/enums/enums.dart';
import 'package:carlton/l10n/app_translations.dart';
import 'package:carlton/models/home_models.dart';
import 'package:carlton/controllers/home/home_controller.dart';
import 'package:carlton/customWidgets/custom_containers.dart';
import 'package:carlton/customWidgets/custom_empty_placeholder.dart';
import 'package:carlton/models/card_meta.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:shimmer/shimmer.dart';

part 'home_rails.dart';

part 'home_sections.dart';

/// The single Home body for both guest states.
///
/// * **With a reservation** (Figma "Home Active Booking" 2197:3178) — the
///   active-stay hero, in-stay requests, running bill, AI concierge, then the
///   Dining/Experiences rails.
/// * **Without one** (Figma "homepage" 2089:861) — the looping video hero, the
///   Rooms rail, the dining hero, then the Dining/Experiences/Offers sections.
///
/// Each section is its own const widget owning an Obx scoped to just the state
/// it renders, so a DND toggle rebuilds one card and a content load rebuilds
/// two rails — never the whole page. Anything absent from the
/// section→subscription list below reads nothing reactive and needs no Obx.
class HomeView extends GetView<HomeController> {
  const HomeView({super.key});

  /// Reservation-state sections. `gap: 20` reproduces the dashboard's spacing;
  /// a hidden section contributes none because the gap lives inside it.
  static const reservationSections = <Widget>[
    _ActiveStaySection(),
    _ActiveRequestsSection(),
    _CurrentBillSection(),
    _AiConciergeSection(),
    _DiningCarousel(),
    _ExperiencesCarousel(),
  ];

  /// Explore-state sections. No gaps — these carry their own internal padding
  /// and butt up against each other by design.
  static const exploreSections = <Widget>[
    _VideoHeroSection(),
    _RoomsCarousel(),
    _DiningHeroSection(),
    _DiningCarousel(),
    _ExperiencesHeroSection(),
  ];

  /// Pre-arrival sections (Figma `75:133`) — booked but not checked in. Half
  /// the list is existing widgets; only the top three are new.
  static const preArrivalSections = <Widget>[
    _PendingBookingSection(),
    PreArrivalStaySection(),
    PreArrivalChecklistSection(),
    _AirportTransferSection(),
    _AiConciergeSection(),
    _DiningCarousel(),
    _ExperiencesCarousel(),
  ];

  /// Pure selector, extracted so it is testable without pumping a widget.
  ///
  /// MUST keep returning the same const list instances: `Element.updateChild`
  /// short-circuits the whole subtree when the identical const list comes
  /// back, which is what stops an unrelated profile edit from rebuilding every
  /// carousel. Do not replace these with computed lists.
  static List<Widget> sectionsFor(HomeViewState state) => switch (state) {
    HomeViewState.defaultHome => exploreSections,
    HomeViewState.preCheckIn => preArrivalSections,
    HomeViewState.activeBooking => reservationSections,
  };

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      // This Obx subscribes only to the guest (currentState reads
      // MiddlewareService.guest.value through a getter). When it fires without
      // the state actually flipping — a profile edit reassigns guest — it hands
      // back the same const section instances and Element.updateChild
      // short-circuits the whole subtree.
      body: Obx(() {
        final sections = sectionsFor(controller.currentState);
        return RefreshIndicator(
          onRefresh: controller.refreshHome,
          color: AppColors.primary,
          child: CustomScrollView(
            // AlwaysScrollable so the pull gesture works even when the sections
            // are shorter than the viewport (the explore state on a tall
            // screen), which a default ScrollPhysics would swallow.
            physics: const AlwaysScrollableScrollPhysics(),
            slivers: [
              SliverPadding(
                padding: const EdgeInsets.all(20),
                // Lazy: an off-screen section's Obx never runs its builder and
                // holds no subscription until it scrolls into view.
                sliver: SliverList.builder(
                  itemCount: sections.length,
                  itemBuilder: (context, index) => sections[index],
                ),
              ),
            ],
          ),
        );
      }),
    );
  }
}

// ═══════════════════════════════════════════════════════════════════════════
// Reservation-state sections
// ═══════════════════════════════════════════════════════════════════════════

// ═══════════════════════════════════════════════════════════════════════════
// Explore-state sections
// ═══════════════════════════════════════════════════════════════════════════

// The explore-state sections below take no `gap` — that state renders its
// sections flush, so there is nothing to space.

// ═══════════════════════════════════════════════════════════════════════════
// Rails
// ═══════════════════════════════════════════════════════════════════════════
