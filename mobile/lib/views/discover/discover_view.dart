import 'package:carlton/l10n/app_translations.dart';
import 'package:carlton/components/cards/custom_discover_card.dart';
import 'package:carlton/controllers/home/discover_controller.dart';
import 'package:carlton/customWidgets/custom_scaffold.dart';
import 'package:carlton/models/home_models.dart';
import 'package:carlton/models/promotion.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:carlton/customWidgets/custom_empty_placeholder.dart';
import 'package:carlton/customWidgets/custom_indicators.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:intl/intl.dart';

/// Shared "Discover All" listing screen. One view serves Rooms, Dining,
/// Experiences and Offers — [DiscoverController.section] (the route argument)
/// decides the title, the filter chips, and which card list is rendered.
class DiscoverView extends GetView<DiscoverController> {
  const DiscoverView({super.key});

  @override
  Widget build(BuildContext context) {
    return CustomScaffold(
      appBar: AppBar(
        iconTheme: const IconThemeData(color: AppColors.inkBlack),
        title: Text(controller.title),
      ),
      body: Obx(() {
        if (controller.loading.value) {
          return const Center(child: LogoLoadingIndicator(size: 50));
        }
        // A failed first page: offer Retry. Distinct from loaded-but-empty,
        // which renders the (empty) list with no button.
        if (controller.hasError.value) {
          return CustomEmptyPlaceholder.loadFailed(
            title: AppTranslations.checkConnectionShort,
            onRetry: controller.reload,
          );
        }
        return Column(
          children: [
            Expanded(child: _list()),
            // Next page in flight; the mixin fetches it near the bottom.
            if (controller.loadingMore.value)
              const Padding(
                padding: EdgeInsets.all(10),
                child: SpinningIconIndicator(size: 28),
              ),
          ],
        );
      }),
    );
  }

  Widget _list() {
    switch (controller.section) {
      case DiscoverSection.rooms:
        final rooms = controller.rooms;
        return ListView.builder(
          controller: controller.scrollController,
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
            primaryLabel: AppTranslations.bookNowLabel,
            onPrimary: () => controller.openRoom(rooms[i]),
            onTap: () => controller.openRoom(rooms[i]),
          ),
        );
      case DiscoverSection.dining:
        final restaurants = controller.restaurants;
        return ListView.builder(
          controller: controller.scrollController,
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
            secondaryLabel: AppTranslations.viewMenu,
            onSecondary: () => controller.openRestaurant(restaurants[i]),
            primaryLabel: AppTranslations.bookNowLabel,
            onPrimary: () => controller.openRestaurant(restaurants[i]),
            onTap: () => controller.openRestaurant(restaurants[i]),
          ),
        );
      case DiscoverSection.experiences:
        final experiences = controller.visibleExperiences;
        final categories = controller.experienceCategories;
        return Column(
          children: [
            // Only worth showing when there is more than one category to pick.
            if (categories.length > 1)
              _CategoryChips(
                categories: categories,
                selected: controller.categoryFilter.value,
                onSelected: controller.selectCategory,
              ),
            Expanded(
              child: ListView.builder(
                controller: controller.scrollController,
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
              ),
            ),
          ],
        );
      case DiscoverSection.offers:
        final offers = controller.offers;
        return ListView.builder(
          controller: controller.scrollController,
          itemCount: offers.length,
          itemBuilder: (context, i) => CustomDiscoverCard(
            // A promotion carries a bare banner URL; CustomImage renders the
            // placeholder when the CMS published none.
            imagePath: offers[i].banner ?? '',
            title: offers[i].title.value,
            // Promotions have no review system on the backend, so these are
            // honestly zeroed rather than faked.
            rating: 0,
            reviews: 0,
            meta: [('assets/icons/clock.svg', _validity(offers[i]))],
            primaryLabel: AppTranslations.viewOffer,
            onPrimary: () => controller.openOffer(offers[i]),
            onTap: () => controller.openOffer(offers[i]),
          ),
        );
    }
  }

  /// Compact validity for the card's single meta row; the sheet shows the full
  /// range. Empty for an offer with no dates, which hides the row.
  static String _validity(Promotion offer) {
    final until = offer.validUntil;
    return until == null
        ? ''
        : AppTranslations.validUntil(_shortDate.format(until));
  }

  static DateFormat get _shortDate => DateFormat('MMM d');
}

/// Experience category chips. Tapping the selected chip clears it (see
/// [DiscoverController.selectCategory]), so there is no separate "All" chip to
/// keep in sync.
class _CategoryChips extends StatelessWidget {
  final List<String> categories;
  final String selected;
  final ValueChanged<String> onSelected;

  const _CategoryChips({
    required this.categories,
    required this.selected,
    required this.onSelected,
  });

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      // Fixed height: a horizontal list in a Column needs a bounded cross axis.
      height: 50,
      child: ListView(
        scrollDirection: Axis.horizontal,
        padding: const EdgeInsets.symmetric(horizontal: 10),
        children: [
          for (final category in categories)
            Padding(
              padding: const EdgeInsetsDirectional.only(end: 10),
              child: ChoiceChip(
                label: Text(category),
                selected: selected == category,
                onSelected: (_) => onSelected(category),
                selectedColor: AppColors.primary,
                labelStyle: Get.textTheme.labelMedium?.copyWith(
                  color: selected == category
                      ? AppColors.white
                      : AppColors.inkBlack,
                ),
              ),
            ),
        ],
      ),
    );
  }
}
