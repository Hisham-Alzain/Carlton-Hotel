import 'package:carlton/constants/demo_data.dart';
import 'package:carlton/controllers/home/home_controller.dart';
import 'package:carlton/components/custom_home_container.dart';
import 'package:carlton/components/cards/custom_listing_card.dart';
import 'package:carlton/customWidgets/custom_containers.dart';
import 'package:carlton/models/card_meta.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';

class HomeView extends GetView<HomeController> {
  const HomeView({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: GetBuilder<HomeController>(
        builder: (_) {
          final sections = _sections();
          return CustomScrollView(
            slivers: [
              SliverPadding(
                padding: const EdgeInsetsGeometry.all(20),
                sliver: SliverList.builder(
                  itemCount: sections.length,
                  itemBuilder: (context, index) => sections[index],
                ),
              ),
            ],
          );
        },
      ),
    );
  }

  List<Widget> _sections() => [
    CustomHomeContainer(
      imagePath: DemoData.heroHomeImagePath,
      videoController: controller.videoController,
      videoReady: controller.isVideoReady,
      location: 'Damascus · Syria',
      title: 'Where every\n*moment* is composed',
      subtitle: 'A landmark of luxury in the heart of Damascus',
      onPrimary: controller.bookNow,
      onSecondary: controller.explore,
    ),
    SectionContainer(
      title: 'Rooms & Suites',
      onPressed: () => controller.discoverAll('Rooms'),
      child: SizedBox(
        height: 400,
        child: ListView.builder(
          scrollDirection: Axis.horizontal,
          itemCount: controller.rooms.length,
          itemBuilder: (context, index) => CustomListingCard(
            imagePath: controller.rooms[index].imagePath,
            title: controller.rooms[index].name,
            subtitle: controller.rooms[index].view,
            metaInRow: true,
            meta: [
              CardMeta('assets/icons/ruler.svg', controller.rooms[index].area),
              CardMeta(
                'assets/icons/guests.svg',
                controller.rooms[index].guests,
              ),
              CardMeta(
                'assets/icons/bed_outline.svg',
                controller.rooms[index].bed,
              ),
            ],
            priceAmount: controller.rooms[index].priceAmount,
            onTap: controller.explore,
          ),
        ),
      ),
    ),
    CustomHomeContainer(
      imagePath: DemoData.heroDiningImagePath,
      location: 'Damascus · Syria',
      title: 'Refined *flavors*,\ntimeless elegance.',
      subtitle: 'A refined dining experience, timeless hospitality.',
      onPrimary: controller.bookNow,
      onSecondary: controller.explore,
    ),
    SectionContainer(
      title: 'Dining & Restaurants',
      onPressed: () => controller.discoverAll('Dining'),
      child: SizedBox(
        height: 400,
        child: ListView.builder(
          scrollDirection: Axis.horizontal,
          itemCount: controller.restaurants.length,
          itemBuilder: (context, index) => CustomListingCard(
            imagePath: controller.restaurants[index].imagePath,
            title: controller.restaurants[index].name,
            subtitle: controller.restaurants[index].cuisine,
            meta: [
              CardMeta(
                'assets/icons/clock.svg',
                controller.restaurants[index].hours,
              ),
              CardMeta(
                'assets/icons/location.svg',
                controller.restaurants[index].location,
              ),
            ],
            onTap: controller.explore,
          ),
        ),
      ),
    ),
    SectionContainer(
      title: 'Experiences',
      onPressed: () => controller.discoverAll('Experiences'),
      child: CustomHomeContainer(
        imagePath: DemoData.heroExperienceImagePath,
        location: 'Damascus · Syria',
        title: 'A Quiet *Luxury*\nExperience',
        subtitle: 'Explore authentic experiences, crafted just for you.',
        onPrimary: controller.bookNow,
        onSecondary: controller.explore,
      ),
    ),
    SectionContainer(
      title: 'Special Offers',
      onPressed: () => controller.discoverAll('Offers'),
      child: CustomHomeContainer(
        imagePath: DemoData.heroHomeImagePath,
        location: 'Damascus · Syria',
        title: 'Seasonal *Escapes*',
        subtitle: 'Curated packages for an unforgettable stay.',
        primaryLabel: 'View Offers',
        onPrimary: controller.bookNow,
        onSecondary: controller.explore,
      ),
    ),
  ];
}
