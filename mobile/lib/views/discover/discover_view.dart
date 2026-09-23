import 'package:carlton/components/cards/custom_discover_card.dart';
import 'package:carlton/controllers/home/discover_controller.dart';
import 'package:carlton/customWidgets/custom_scaffold.dart';
import 'package:carlton/models/home_models.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';

/// Shared "Discover All" listing screen. One view serves Rooms, Dining, and
/// Experiences — [DiscoverController.section] (the route argument) decides the
/// title, the filter chips, and which card list is rendered.
class DiscoverView extends GetView<DiscoverController> {
  const DiscoverView({super.key});

  @override
  Widget build(BuildContext context) {
    return CustomScaffold(
      appBar: AppBar(
        iconTheme: const IconThemeData(color: AppColors.inkBlack),
        title: Text(controller.title),
      ),
      //TODO: needs filter
      body: Obx(
        () => controller.loading.value
            ? const Center(child: CircularProgressIndicator())
            : _list(),
      ),
    );
  }

  Widget _list() {
    switch (controller.section) {
      case DiscoverSection.rooms:
        final rooms = controller.rooms;
        return ListView.builder(
          itemCount: rooms.length,
          itemBuilder: (context, i) => CustomDiscoverCard(
            imagePath: rooms[i].imagePath,
            title: rooms[i].name,
            rating: rooms[i].rating,
            reviews: rooms[i].reviews,
            meta: [
              ('assets/icons/ruler.svg', rooms[i].area),
              ('assets/icons/view.svg', rooms[i].view),
              ('assets/icons/king_bed.svg', rooms[i].bed),
            ],
            chips: rooms[i].amenities,
            priceLabel: rooms[i].priceAmount,
            primaryLabel: 'Book Now',
            onPrimary: () => controller.openRoom(rooms[i]),
            onTap: () => controller.openRoom(rooms[i]),
          ),
        );
      case DiscoverSection.dining:
        final restaurants = controller.restaurants;
        return ListView.builder(
          itemCount: restaurants.length,
          itemBuilder: (context, i) => CustomDiscoverCard(
            imagePath: restaurants[i].imagePath,
            title: restaurants[i].name,
            rating: restaurants[i].rating,
            reviews: restaurants[i].reviews,
            meta: [
              ('assets/icons/cuisine.svg', restaurants[i].cuisine),
              ('assets/icons/clock.svg', restaurants[i].hours),
              ('assets/icons/location.svg', restaurants[i].location),
            ],
            secondaryLabel: 'View Menu',
            onSecondary: () => controller.openRestaurant(restaurants[i]),
            primaryLabel: 'Book Now',
            onPrimary: () => controller.openRestaurant(restaurants[i]),
            onTap: () => controller.openRestaurant(restaurants[i]),
          ),
        );
      case DiscoverSection.experiences:
        final experiences = controller.experiences;
        return ListView.builder(
          itemCount: experiences.length,
          itemBuilder: (context, i) => CustomDiscoverCard(
            imagePath: experiences[i].imagePath,
            title: experiences[i].name,
            rating: experiences[i].rating,
            reviews: experiences[i].reviews,
            badge: experiences[i].badge,
            meta: [
              ('assets/icons/guests.svg', experiences[i].subtitle),
              ('assets/icons/clock.svg', experiences[i].hours),
            ],
            onTap: () => controller.openExperience(experiences[i]),
          ),
        );
    }
  }
}
