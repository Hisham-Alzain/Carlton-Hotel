import 'package:carlton/l10n/app_translations.dart';
import 'package:carlton/components/custom_restaurant_metaline.dart';
import 'package:carlton/components/dining/custom_restaurant_hero.dart';
import 'package:carlton/components/dining/restaurant_info_tab.dart';
import 'package:carlton/components/dining/restaurant_menu_tab.dart';
import 'package:carlton/components/dining/restaurant_reserve_tab.dart';
import 'package:carlton/components/dining/restaurant_reviews_tab.dart';
import 'package:carlton/controllers/dining/restaurant_controller.dart';
import 'package:carlton/customWidgets/custom_containers.dart';
import 'package:carlton/customWidgets/custom_scaffold.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';

class RestaurantDetailView extends GetView<RestaurantController> {
  const RestaurantDetailView({super.key});

  @override
  Widget build(BuildContext context) {
    return CustomScaffold(
      appBar: AppBar(iconTheme: IconThemeData(color: AppColors.primary)),
      // The TabController lives here rather than on RestaurantController, so it
      // is created and disposed with this widget. It used to hang off the
      // controller — a type-keyed Get.lazyPut singleton shared by every
      // restaurant route — so popping one route disposed the TabController a
      // surviving route's TabBar still held, and TabBar's unguarded
      // `_controller!.animation!` threw on the disposed instance.
      body: DefaultTabController(
        length: 4,
        child: Builder(
          // Supplies a context *below* the DefaultTabController, which is what
          // DefaultTabController.of() needs in order to find it.
          builder: (context) {
            final c = controller;
            return Column(
              children: [
                // Hero with the hours/location card floating over its bottom
                // edge. The padded hero is the only non-positioned child, so it
                // sizes the Stack to hero + _metaCardDrop; the card then
                // bottom-aligns inside those bounds instead of overflowing them.
                Stack(
                  children: [
                    Padding(
                      padding: const EdgeInsets.only(bottom: 20),
                      child: CustomRestaurantHero(
                        restaurant: c.restaurant,
                        onReserve: () =>
                            DefaultTabController.of(context).animateTo(2),
                      ),
                    ),
                    Positioned(
                      left: 10,
                      right: 10,
                      bottom: -10,
                      child: PillContainer(
                        backgroundColor: AppColors.white,
                        radius: 12,
                        border: Border.all(color: AppColors.black06),
                        boxShadow: const [
                          BoxShadow(
                            color: AppColors.shadowGrey25,
                            blurRadius: 20,
                            offset: Offset(0, 8),
                          ),
                        ],
                        child: Row(
                          mainAxisAlignment: MainAxisAlignment.center,
                          spacing: 10,
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

                // No `controller:` on the TabBar/TabBarView — both resolve the
                // DefaultTabController from context.
                Padding(
                  padding: const EdgeInsets.all(10),
                  child: TabBar(
                    tabs: [
                      Tab(text: AppTranslations.tabMenu),
                      Tab(text: AppTranslations.tabInfo),
                      Tab(text: AppTranslations.reserve),
                      Tab(text: AppTranslations.reviews),
                    ],
                  ),
                ),

                Expanded(
                  child: TabBarView(
                    children: [
                      RestaurantMenuTab(c: c),
                      RestaurantInfoTab(c: c),
                      RestaurantReserveTab(c: c),
                      RestaurantReviewsTab(c: c),
                    ],
                  ),
                ),
              ],
            );
          },
        ),
      ),
    );
  }
}
