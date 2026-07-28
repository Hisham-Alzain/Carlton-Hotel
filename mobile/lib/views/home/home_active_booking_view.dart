import 'package:carlton/components/cards/custom_discover_card.dart';
import 'package:carlton/components/cards/custom_listing_card.dart';
import 'package:carlton/components/home/custom_active_booking_card.dart';
import 'package:carlton/components/home/custom_active_requests_card.dart';
import 'package:carlton/components/home/custom_ai_concierge_banner.dart';
import 'package:carlton/components/home/custom_current_bill_card.dart';
import 'package:carlton/controllers/home/home_controller.dart';
import 'package:carlton/customWidgets/custom_containers.dart';
import 'package:carlton/models/card_meta.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';

/// The reservation-state Home body (Figma "Home Active Booking" 2197:3178):
/// active-stay hero, active requests, current bill, AI concierge, and the
/// Dining/Experiences carousels. Shown by [HomeView] when
/// `controller.hasReservation` is true.
class HomeActiveBookingBody extends GetView<HomeController> {
  const HomeActiveBookingBody({super.key});

  @override
  Widget build(BuildContext context) {
    return SingleChildScrollView(
      padding: const EdgeInsets.all(20),
      child: Column(
        // Stretch so every card/section is the same full width. Without this the
        // Card/ClipRRect-rooted cards size to their own content and render at
        // ragged, uneven widths next to the full-bleed carousels.
        crossAxisAlignment: CrossAxisAlignment.stretch,
        spacing: 20,
        children: [
          // The stay hero, in-stay requests, and running bill only exist once
          // checked in (activeStay != null). A booked-but-not-checked-in guest
          // sees the concierge + explore rails until arrival.
          if (controller.activeStay != null) ...[
            CustomActiveBookingCard(
              stay: controller.activeStay!,
              doNotDisturb: controller.doNotDisturb,
              onDndChanged: controller.toggleDoNotDisturb,
              onRequest: controller.quickRequest,
              onConcierge: controller.openConcierge,
              onBill: controller.openBill,
              onCheckout: controller.checkout,
            ),
            CustomActiveRequestsCard(
              requests: controller.activeRequests,
              onOpen: controller.openRequest,
              onNewRequest: controller.newRequest,
            ),
            CustomCurrentBillCard(
              lines: controller.billLines,
              total: controller.billTotal,
              onFullStatement: controller.fullStatement,
            ),
          ] else if (controller.upcomingStay != null)
            // Booked but not checked in: the same active-stay card, in read-only
            // mode (interactive: false) — header shows the reservation; the
            // in-stay controls are greyed until check-in.
            CustomActiveBookingCard(
              stay: controller.upcomingStay!,
              interactive: false,
              doNotDisturb: false,
              onDndChanged: (_) {},
              onRequest: () {},
              onConcierge: () {},
              onBill: () {},
              onCheckout: () {},
            ),
          CustomAiConciergeBanner(onTap: controller.openConcierge),
          _DiningCarousel(controller: controller),
          _ExperiencesCarousel(controller: controller),
        ],
      ),
    );
  }
}

class _DiningCarousel extends StatelessWidget {
  final HomeController controller;

  const _DiningCarousel({required this.controller});

  @override
  Widget build(BuildContext context) {
    return SectionContainer(
      title: 'Dining & Restaurants',
      onPressed: () => controller.discoverAll('Dining'),
      child: SizedBox(
        height: 400,
        child: ListView.builder(
          scrollDirection: Axis.horizontal,
          itemCount: controller.restaurants.length,
          itemBuilder: (context, index) {
            final restaurant = controller.restaurants[index];
            return CustomListingCard(
              imagePath: restaurant.imagePath,
              title: restaurant.name,
              subtitle: restaurant.cuisine,
              meta: [
                CardMeta('assets/icons/clock.svg', restaurant.hours),
                CardMeta('assets/icons/location.svg', restaurant.location),
              ],
              onTap: () => controller.openRestaurant(restaurant),
            );
          },
        ),
      ),
    );
  }
}

class _ExperiencesCarousel extends StatelessWidget {
  final HomeController controller;

  const _ExperiencesCarousel({required this.controller});

  @override
  Widget build(BuildContext context) {
    return SectionContainer(
      title: 'Experiences',
      onPressed: () => controller.discoverAll('Experiences'),
      child: SizedBox(
        height: 300,
        child: ListView.separated(
          scrollDirection: Axis.horizontal,
          itemCount: controller.experiences.length,
          separatorBuilder: (_, _) => const SizedBox(width: 12),
          itemBuilder: (context, index) {
            final experience = controller.experiences[index];
            return SizedBox(
              width: 320,
              child: CustomDiscoverCard(
                imagePath: experience.imagePath,
                title: experience.name,
                rating: experience.rating,
                reviews: experience.reviews,
                badge: experience.badge,
                meta: [
                  ('assets/icons/guests.svg', experience.subtitle),
                  ('assets/icons/clock.svg', experience.hours),
                ],
                margin: const EdgeInsets.symmetric(vertical: 8),
                onTap: () => controller.openExperience(experience),
              ),
            );
          },
        ),
      ),
    );
  }
}
