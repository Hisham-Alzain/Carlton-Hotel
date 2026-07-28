import 'package:carlton/components/dining/custom_restaurant_hero.dart';
import 'package:carlton/components/dining/restaurant_info_tab.dart';
import 'package:carlton/components/dining/restaurant_menu_tab.dart';
import 'package:carlton/components/dining/restaurant_reserve_tab.dart';
import 'package:carlton/components/dining/restaurant_reviews_tab.dart';
import 'package:carlton/controllers/dining/restaurant_controller.dart';
import 'package:carlton/customWidgets/custom_scaffold.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';

class RestaurantDetailView extends GetView<RestaurantController> {
  const RestaurantDetailView({super.key});

  @override
  Widget build(BuildContext context) {
    // Pin to the design's text scale so a large device font setting can't
    // push the hero title onto two lines or truncate the meta row.
    return MediaQuery.withClampedTextScaling(
      maxScaleFactor: 1.0,
      child: _body(),
    );
  }

  Widget _body() {
    return CustomScaffold(
      body: GetBuilder<RestaurantController>(
        builder: (c) => Column(
          children: [
            // Hero with the hours/location card floating over its bottom edge.
            Stack(
              clipBehavior: Clip.none,
              children: [
                CustomRestaurantHero(
                  restaurant: c.restaurant,
                  onReserve: c.goToReserveTab,
                ),
                Positioned(
                  left: 16,
                  right: 16,
                  bottom: -34,
                  child: Container(
                    padding: const EdgeInsets.symmetric(
                      horizontal: 14,
                      vertical: 12,
                    ),
                    decoration: BoxDecoration(
                      color: AppColors.white,
                      borderRadius: BorderRadius.circular(12),
                      border: Border.all(color: AppColors.black06),
                      boxShadow: const [
                        BoxShadow(
                          color: AppColors.shadowGrey25,
                          blurRadius: 20,
                          offset: Offset(0, 8),
                        ),
                      ],
                    ),
                    child: Row(
                      mainAxisAlignment: MainAxisAlignment.center,
                      spacing: 14,
                      children: [
                        Flexible(
                          child: RestaurantMetaLine(
                            icon: 'assets/icons/clock.svg',
                            text: c.restaurant.hours,
                          ),
                        ),
                        Flexible(
                          child: RestaurantMetaLine(
                            icon: 'assets/icons/location.svg',
                            text: c.restaurant.location,
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 46),
            TabBar(
              controller: c.tabController,
              labelColor: AppColors.primary,
              unselectedLabelColor: AppColors.graphite,
              indicatorColor: AppColors.primary,
              indicatorWeight: 2,
              dividerColor: AppColors.black06,
              labelStyle: Get.textTheme.labelLarge?.copyWith(
                fontWeight: FontWeight.w600,
              ),
              unselectedLabelStyle: Get.textTheme.labelLarge?.copyWith(
                fontWeight: FontWeight.w500,
              ),
              tabs: const [
                Tab(text: 'Menu'),
                Tab(text: 'Info'),
                Tab(text: 'Reserve'),
                Tab(text: 'Reviews'),
              ],
            ),
            const SizedBox(height: 6),
            Expanded(
              child: TabBarView(
                controller: c.tabController,
                children: [
                  RestaurantMenuTab(c: c),
                  RestaurantInfoTab(c: c),
                  RestaurantReserveTab(c: c),
                  RestaurantReviewsTab(c: c),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}
