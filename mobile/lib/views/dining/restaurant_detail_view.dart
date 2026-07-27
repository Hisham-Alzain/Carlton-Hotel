import 'package:carlton/components/dining/custom_restaurant_hero.dart';
import 'package:carlton/components/dining/custom_underline_tabs.dart';
import 'package:carlton/components/dining/restaurant_info_tab.dart';
import 'package:carlton/components/dining/restaurant_menu_tab.dart';
import 'package:carlton/components/dining/restaurant_reserve_tab.dart';
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
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 16),
              child: CustomUnderlineTabs(
                labels: const ['Menu', 'Info', 'Reserve'],
                selectedIndex: c.tabIndex,
                onChanged: c.switchTab,
              ),
            ),
            const SizedBox(height: 6),
            Expanded(
              child: switch (c.tabIndex) {
                0 => RestaurantMenuTab(c: c),
                1 => RestaurantInfoTab(c: c),
                _ => RestaurantReserveTab(c: c),
              },
            ),
          ],
        ),
      ),
    );
  }
}
