import 'package:carlton/components/custom_restaurant_metaline.dart';
import 'package:carlton/components/dining/custom_restaurant_hero.dart';
import 'package:carlton/components/dining/restaurant_info_tab.dart';
import 'package:carlton/components/dining/restaurant_menu_tab.dart';
import 'package:carlton/components/dining/restaurant_reserve_tab.dart';
import 'package:carlton/components/dining/restaurant_reviews_tab.dart';
import 'package:carlton/controllers/dining/restaurant_controller.dart';
import 'package:carlton/controllers/reviews/review_controller.dart';
import 'package:carlton/customWidgets/custom_containers.dart';
import 'package:carlton/customWidgets/custom_scaffold.dart';
import 'package:carlton/models/home_models.dart';
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
            // No observer around the whole column: `restaurant` is late final,
            // so the hero and meta card never need to repaint. Each tab that
            // owns mutable state carries its own Obx instead — picking a menu
            // category used to rebuild the hero and all 4 tabs.
            final c = Get.find<RestaurantController>();
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
                      //TODO: check if it could be transperant
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
                    tabs: const [
                      Tab(text: 'Menu'),
                      Tab(text: 'Info'),
                      Tab(text: 'Reserve'),
                      Tab(text: 'Reviews'),
                    ],
                  ),
                ),

                Expanded(
                  child: TabBarView(
                    children: [
                      Obx(
                        () => RestaurantMenuTab(
                          categories: c.menuCategories,
                          items: c.visibleMenuItems,
                          selectedCategory: c.categoryIndex.value,
                          loading: c.menuLoading.value,
                          onSelectCategory: c.selectCategory,
                          onDownloadMenu: c.downloadMenu,
                        ),
                      ),
                      Obx(
                        () => RestaurantInfoTab(
                          restaurant: c.restaurant,
                          about: c.about.value,
                          gallery: c.gallery,
                        ),
                      ),
                      Obx(
                        () => RestaurantReserveTab(
                          date: c.reserveDate.value,
                          timeSlot: c.timeSlot.value,
                          guests: c.guests.value,
                          specialRequests: c.specialRequests,
                          onPickDate: c.pickDate,
                          onSelectTimeSlot: c.selectTimeSlot,
                          onGuestsChanged: c.setGuests,
                          onConfirm: c.confirmReservation,
                        ),
                      ),
                      // TODO: check if should keep reviews
                      _ReviewsTab(restaurant: c.restaurant),
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

/// Holds the `Obx` over [ReviewController]'s paginated Rx state so the tab
/// itself takes plain values. Split out rather than inlined so the observer
/// rebuilds only the reviews tab, not the whole detail column.
class _ReviewsTab extends StatelessWidget {
  final RestaurantItem restaurant;

  const _ReviewsTab({required this.restaurant});

  @override
  Widget build(BuildContext context) {
    final reviews = Get.find<ReviewController>();

    return Obx(
      () => RestaurantReviewsTab(
        rating: restaurant.rating,
        reviewCount: restaurant.reviews,
        reviews: reviews.items,
        loading: reviews.loading.value,
        loadingMore: reviews.loadingMore.value,
        hasError: reviews.hasError.value,
        scrollController: reviews.scrollController,
        onWriteReview: Get.find<RestaurantController>().openReviewSheet,
        onRetry: reviews.reload,
      ),
    );
  }
}
