import 'package:carlton/components/cards/custom_discover_card.dart';
import 'package:carlton/components/cards/custom_home_card.dart';
import 'package:carlton/components/custom_home_container.dart';
import 'package:carlton/components/home/custom_active_booking_card.dart';
import 'package:carlton/components/home/custom_active_requests_card.dart';
import 'package:carlton/components/home/custom_ai_concierge_banner.dart';
import 'package:carlton/components/home/custom_current_bill_card.dart';
import 'package:carlton/components/home/pre_arrival_sections.dart';
import 'package:carlton/services/check_in_service.dart';
import 'package:carlton/constants/demo_data.dart';
import 'package:carlton/controllers/home/home_controller.dart';
import 'package:carlton/customWidgets/custom_containers.dart';
import 'package:carlton/customWidgets/custom_empty_placeholder.dart';
import 'package:carlton/models/card_meta.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:shimmer/shimmer.dart';

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
  static List<Widget> sectionsFor({
    required bool hasReservation,
    required bool isPreArrival,
  }) {
    if (!hasReservation) return exploreSections;
    return isPreArrival ? preArrivalSections : reservationSections;
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      // This Obx subscribes only to the guest (hasReservation reads
      // MiddlewareService.guest.value through a getter) and the pre-arrival
      // flag. When it fires without either actually flipping — a profile edit
      // reassigns guest — it hands back the same const section instances and
      // Element.updateChild short-circuits the whole subtree.
      body: Obx(() {
        final sections = sectionsFor(
          hasReservation: controller.hasReservation,
          isPreArrival: CheckInService.find.isPreArrival.value,
        );
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

/// The stay hero. Checked in → the interactive card; booked but not yet
/// arrived → the same card read-only; neither → nothing.
class _ActiveStaySection extends GetView<HomeController> {
  const _ActiveStaySection();

  @override
  Widget build(BuildContext context) {
    return Obx(() {
      // Placeholder while the first /stays/active fetch is in flight —
      // otherwise this collapses to nothing and the card pops in.
      if (controller.bookingLoading.value) return const _CardShimmer(height: 220);

      final stay = controller.activeStay.value;
      if (stay != null) {
        return CustomActiveBookingCard(
          stay: stay,
          doNotDisturb: controller.doNotDisturb.value,
          onDndChanged: controller.toggleDoNotDisturb,
          onRequest: controller.quickRequest,
          onConcierge: controller.openConcierge,
          onBill: controller.openBill,
          onCheckout: controller.checkout,
        );
      }

      final upcoming = controller.upcomingStay.value;
      if (upcoming == null) return const SizedBox.shrink();

      // Booked but not checked in: the header shows the reservation and the
      // in-stay controls are greyed until arrival.
      return CustomActiveBookingCard(
        stay: upcoming,
        interactive: false,
        doNotDisturb: false,
        onDndChanged: (_) {},
        onRequest: () {},
        onConcierge: () {},
        onBill: () {},
        onCheckout: () {},
      );
    });
  }
}

/// In-stay service requests — only exist once checked in.
class _ActiveRequestsSection extends GetView<HomeController> {
  const _ActiveRequestsSection();

  @override
  Widget build(BuildContext context) {
    return Obx(() {
      if (controller.bookingLoading.value) return const _CardShimmer(height: 140);
      if (controller.activeStay.value == null) return const SizedBox.shrink();
      return CustomActiveRequestsCard(
        // toList() both snapshots the list and registers the read — passing
        // the RxList itself would subscribe to nothing, since the card's
        // build runs outside this closure.
        requests: controller.activeRequests.toList(),
        onOpen: controller.openRequest,
        onNewRequest: controller.newRequest,
      );
    });
  }
}

/// Running bill — only exists once checked in.
class _CurrentBillSection extends GetView<HomeController> {
  const _CurrentBillSection();

  @override
  Widget build(BuildContext context) {
    return Obx(() {
      if (controller.bookingLoading.value) return const _CardShimmer(height: 160);
      if (controller.activeStay.value == null) return const SizedBox.shrink();
      return CustomCurrentBillCard(
        lines: controller.billLines.toList(),
        total: controller.billTotal.value,
        onFullStatement: controller.fullStatement,
      );
    });
  }
}

/// Static content — no Obx, only needs the controller for its callback.
class _AirportTransferSection extends GetView<HomeController> {
  const _AirportTransferSection();

  @override
  Widget build(BuildContext context) {
    return AirportTransferSection(onRequest: controller.goToServices);
  }
}

class _AiConciergeSection extends GetView<HomeController> {
  const _AiConciergeSection();

  @override
  Widget build(BuildContext context) {
    return CustomAiConciergeBanner(onTap: controller.openConcierge);
  }
}

// ═══════════════════════════════════════════════════════════════════════════
// Explore-state sections
// ═══════════════════════════════════════════════════════════════════════════

// The explore-state sections below take no `gap` — that state renders its
// sections flush, so there is nothing to space.

class _VideoHeroSection extends GetView<HomeController> {
  const _VideoHeroSection();

  @override
  Widget build(BuildContext context) {
    return Obx(
      () => CustomHomeContainer(
        assetPath: DemoData.heroHomeImagePath,
        videoController: controller.videoController,
        videoReady: controller.isVideoReady.value,
        location: 'Damascus · Syria',
        title: 'Where every\n*moment* is composed',
        subtitle: 'A landmark of luxury in the heart of Damascus',
        onPrimary: controller.bookNow,
        onSecondary: controller.explore,
      ),
    );
  }
}

class _DiningHeroSection extends GetView<HomeController> {
  const _DiningHeroSection();

  @override
  Widget build(BuildContext context) {
    return CustomHomeContainer(
      assetPath: DemoData.heroDiningImagePath,
      location: 'Damascus · Syria',
      title: 'Refined *flavors*,\ntimeless elegance.',
      subtitle: 'A refined dining experience, timeless hospitality.',
      onPrimary: controller.bookNow,
      onSecondary: controller.explore,
    );
  }
}

/// Experiences in the explore state is still a placeholder hero, not the real
/// carousel — kept deliberately distinct from [_ExperiencesCarousel].
class _ExperiencesHeroSection extends GetView<HomeController> {
  const _ExperiencesHeroSection();

  @override
  Widget build(BuildContext context) {
    return SectionContainer(
      title: 'Experiences',
      onPressed: () => controller.discoverAll('Experiences'),
      child: CustomHomeContainer(
        assetPath: DemoData.heroExperienceImagePath,
        location: 'Damascus · Syria',
        title: 'A Quiet *Luxury*\nExperience',
        subtitle: 'Explore authentic experiences, crafted just for you.',
        onPrimary: controller.bookNow,
        onSecondary: controller.explore,
      ),
    );
  }
}

// ═══════════════════════════════════════════════════════════════════════════
// Rails
// ═══════════════════════════════════════════════════════════════════════════

/// Shown while a rail's content is still in flight, in place of the bare 400px
/// of blank space the headers used to sit above.
/// Card-shaped shimmer placeholders for a horizontal rail. Mirrors
/// [CustomHomeCard]'s 300×200 photo block plus two text lines, so the rail
/// keeps its height and the content doesn't jump when the real cards land.
class _RailShimmer extends StatelessWidget {
  const _RailShimmer();

  @override
  Widget build(BuildContext context) {
    return Shimmer.fromColors(
      baseColor: AppColors.pearlGrey,
      highlightColor: AppColors.whisperGrey,
      // Non-scrollable: these are a placeholder, not content to explore, and a
      // scrollable here would fight the RefreshIndicator's pull.
      child: ListView.builder(
        scrollDirection: Axis.horizontal,
        physics: const NeverScrollableScrollPhysics(),
        itemCount: 3,
        itemBuilder: (context, _) => const Padding(
          padding: EdgeInsets.only(right: 10),
          child: _ShimmerCard(),
        ),
      ),
    );
  }
}

/// Full-width shimmer block for the stacked reservation-state cards, which are
/// single cards rather than rails.
class _CardShimmer extends StatelessWidget {
  final double height;

  const _CardShimmer({required this.height});

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 20),
      child: Shimmer.fromColors(
        baseColor: AppColors.pearlGrey,
        highlightColor: AppColors.whisperGrey,
        child: Container(
          height: height,
          width: double.infinity,
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(12),
          ),
        ),
      ),
    );
  }
}

class _ShimmerCard extends StatelessWidget {
  const _ShimmerCard();

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      mainAxisSize: MainAxisSize.min,
      spacing: 10,
      children: [
        Container(
          width: 300,
          height: 200,
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(12),
          ),
        ),
        for (final width in const [180.0, 120.0])
          Container(
            width: width,
            height: 14,
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(4),
            ),
          ),
      ],
    );
  }
}

/// Rail fallback for "loaded, but nothing to show". [onRetry] is supplied only
/// when the fetch actually failed — a genuinely empty result gets no Retry
/// button, since retrying would return the same empty list.
class _RailEmpty extends StatelessWidget {
  final String title;
  final String subtitle;
  final VoidCallback? onRetry;

  const _RailEmpty({required this.title, required this.subtitle, this.onRetry});

  @override
  Widget build(BuildContext context) {
    return CustomEmptyPlaceholder(
      iconWidget: Icon(
        onRetry != null ? Icons.cloud_off_outlined : Icons.inbox_outlined,
        size: 40,
        color: AppColors.primary,
      ),
      title: title,
      subtitle: subtitle,
      primaryLabel: onRetry != null ? 'Retry' : null,
      onPrimary: onRetry,
    );
  }
}

/// Explore-state only, so no `gap`.
class _RoomsCarousel extends GetView<HomeController> {
  const _RoomsCarousel();

  @override
  Widget build(BuildContext context) {
    return Obx(() {
      final loading = controller.contentLoading.value;
      // Snapshot inside the closure: this is what registers the subscription,
      // and it leaves itemBuilder closing over a plain list.
      final rooms = controller.rooms.toList();
      return SectionContainer(
        title: 'Rooms & Suites',
        onPressed: () => controller.discoverAll('Rooms'),
        child: SizedBox(
          height: 400,
          child: loading
              ? const _RailShimmer()
              : rooms.isEmpty
              ? _RailEmpty(
                  title: controller.contentError.value
                      ? "Couldn't load rooms"
                      : 'No rooms available',
                  subtitle: controller.contentError.value
                      ? 'Check your connection and try again.'
                      : 'New rooms will appear here as they open up.',
                  onRetry: controller.contentError.value
                      ? controller.refreshHome
                      : null,
                )
              : ListView.builder(
                  scrollDirection: Axis.horizontal,
                  itemCount: rooms.length,
                  itemBuilder: (context, index) {
                    final room = rooms[index];
                    return CustomHomeCard(
                      imagePath: room.imagePath,
                      title: room.name,
                      subtitle: room.view,
                      metaInRow: true,
                      meta: [
                        CardMeta('assets/icons/ruler.svg', room.area),
                        CardMeta('assets/icons/guests.svg', room.guests),
                        CardMeta('assets/icons/bed_outline.svg', room.bed),
                      ],
                      priceAmount: room.priceAmount,
                      onTap: () => controller.openRoomDetails(room),
                    );
                  },
                ),
        ),
      );
    });
  }
}

class _DiningCarousel extends GetView<HomeController> {
  const _DiningCarousel();

  @override
  Widget build(BuildContext context) {
    return Obx(() {
      final loading = controller.contentLoading.value;
      final restaurants = controller.restaurants.toList();
      return SectionContainer(
        title: 'Dining & Restaurants',
        onPressed: () => controller.discoverAll('Dining'),
        child: SizedBox(
          height: 400,
          child: loading
              ? const _RailShimmer()
              : restaurants.isEmpty
              ? _RailEmpty(
                  title: controller.contentError.value
                      ? "Couldn't load restaurants"
                      : 'No restaurants yet',
                  subtitle: controller.contentError.value
                      ? 'Check your connection and try again.'
                      : 'Our venues will be listed here soon.',
                  onRetry: controller.contentError.value
                      ? controller.refreshHome
                      : null,
                )
              : ListView.builder(
                  scrollDirection: Axis.horizontal,
                  itemCount: restaurants.length,
                  itemBuilder: (context, index) {
                    final restaurant = restaurants[index];
                    return CustomHomeCard(
                      imagePath: restaurant.imagePath,
                      title: restaurant.name,
                      subtitle: restaurant.cuisine,
                      meta: [
                        CardMeta('assets/icons/clock.svg', restaurant.hours),
                        CardMeta(
                          'assets/icons/location.svg',
                          restaurant.location,
                        ),
                      ],
                      onTap: () => controller.openRestaurant(restaurant),
                    );
                  },
                ),
        ),
      );
    });
  }
}

/// Experiences are seeded from [DemoData] and never mutate, so this rail has
/// no reactive state and needs no Obx or loading branch.
class _ExperiencesCarousel extends GetView<HomeController> {
  const _ExperiencesCarousel();

  @override
  Widget build(BuildContext context) {
    return SectionContainer(
      title: 'Experiences',
      onPressed: () => controller.discoverAll('Experiences'),
      child: SizedBox(
        height: 350,
        child: ListView.builder(
          scrollDirection: Axis.horizontal,
          itemCount: controller.experiences.length,
          itemBuilder: (context, index) {
            final experience = controller.experiences[index];
            return CustomDiscoverCard(
              imagePath: experience.imagePath,
              title: experience.name,
              rating: experience.rating,
              reviews: experience.reviews,
              badge: experience.badge,
              meta: [
                ('assets/icons/guests.svg', experience.subtitle),
                ('assets/icons/clock.svg', experience.hours),
              ],
              onTap: () => controller.openExperience(experience),
            );
          },
        ),
      ),
    );
  }
}
