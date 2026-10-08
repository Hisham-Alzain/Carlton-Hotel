part of 'home_view.dart';

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
          padding: EdgeInsetsDirectional.only(end: 10),
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
      primaryLabel: onRetry != null ? AppTranslations.retry : null,
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
        title: AppTranslations.roomsSuites,
        onPressed: () => controller.discoverAll(DiscoverSection.rooms),
        child: SizedBox(
          height: 400,
          child: loading
              ? const _RailShimmer()
              : rooms.isEmpty
              ? _RailEmpty(
                  title: controller.contentError.value
                      ? AppTranslations.roomsLoadFailed
                      : AppTranslations.noRoomsAvailable,
                  subtitle: controller.contentError.value
                      ? AppTranslations.checkConnectionShort
                      : AppTranslations.roomsAppearWhenOpen,
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
        title: AppTranslations.diningRestaurants,
        onPressed: () => controller.discoverAll(DiscoverSection.dining),
        child: SizedBox(
          height: 400,
          child: loading
              ? const _RailShimmer()
              : restaurants.isEmpty
              ? _RailEmpty(
                  title: controller.contentError.value
                      ? AppTranslations.restaurantsLoadFailed
                      : AppTranslations.noRestaurantsYet,
                  subtitle: controller.contentError.value
                      ? AppTranslations.checkConnectionShort
                      : AppTranslations.venuesListedSoon,
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

/// Experiences come from `GET /public/experiences`, so this rail has
/// no reactive state and needs no Obx or loading branch.
class _ExperiencesCarousel extends GetView<HomeController> {
  const _ExperiencesCarousel();

  @override
  Widget build(BuildContext context) {
    return SectionContainer(
      title: AppTranslations.experiences,
      onPressed: () => controller.discoverAll(DiscoverSection.experiences),
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
