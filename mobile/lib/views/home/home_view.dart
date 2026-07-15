import 'package:carlton/constants/demo_data.dart';
import 'package:carlton/controllers/home/home_controller.dart';
import 'package:carlton/components/cards/custom_hero_card.dart';
import 'package:carlton/customWidgets/custom_image.dart';
import 'package:carlton/components/cards/custom_listing_card.dart';
import 'package:carlton/components/custom_logo_avatar.dart';
import 'package:carlton/customWidgets/custom_scaffold.dart';
import 'package:carlton/customWidgets/custom_section_header.dart';
import 'package:carlton/customWidgets/custom_snackbar.dart';
import 'package:carlton/routes/routes.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:flutter_svg/flutter_svg.dart';
import 'package:get/get.dart';
import 'package:video_player/video_player.dart';

class HomeView extends GetView<HomeController> {
  const HomeView({super.key});

  @override
  Widget build(BuildContext context) {
    return CustomScaffold(
      body: SafeArea(
        bottom: false,
        child: GetBuilder<HomeController>(
          builder: (_) => SingleChildScrollView(
            padding: const EdgeInsets.only(bottom: 28),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                _header(),
                Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  spacing: 32,
                  children: [
                    _videoHero(),
                    _section(
                      title: 'Rooms & Suites',
                      onDiscoverAll: () => controller.discoverAll('Rooms'),
                      child: _roomsList(),
                    ),
                    _diningHero(),
                    _section(
                      title: 'Dining & Restaurants',
                      onDiscoverAll: () => controller.discoverAll('Dining'),
                      child: _restaurantsList(),
                    ),
                    _section(
                      title: 'Experiences',
                      onDiscoverAll: () =>
                          controller.discoverAll('Experiences'),
                      child: _experiencesHero(),
                    ),
                    _section(
                      title: 'Special Offers',
                      onDiscoverAll: () => controller.discoverAll('Offers'),
                      child: _offersHero(),
                    ),
                  ],
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }

  Widget _header() {
    return Padding(
      padding: const EdgeInsets.fromLTRB(20, 12, 20, 16),
      child: Row(
        children: [
          GestureDetector(
            onTap: () => CustomSnackbars.showInfo(message: 'Menu coming soon'),
            behavior: HitTestBehavior.opaque,
            child: SvgPicture.asset(
              'assets/icons/menu.svg',
              width: 26,
              colorFilter: const ColorFilter.mode(
                AppColors.primary,
                BlendMode.srcIn,
              ),
            ),
          ),
          Expanded(
            child: Center(
              child: SvgPicture.asset(
                'assets/images/carlton_wordmark.svg',
                width: 106,
                colorFilter: const ColorFilter.mode(
                  AppColors.primary,
                  BlendMode.srcIn,
                ),
              ),
            ),
          ),
          CustomLogoAvatar(
            logoSize: 25,
            onTap: () => Get.toNamed(Routes.aiConcierge),
          ),
        ],
      ),
    );
  }

  List<InlineSpan> _title(String pre, String italic, [String post = '']) => [
    TextSpan(text: pre),
    TextSpan(
      text: italic,
      style: const TextStyle(
        fontStyle: FontStyle.italic,
        fontWeight: FontWeight.w400,
      ),
    ),
    if (post.isNotEmpty) TextSpan(text: post),
  ];

  Widget _videoHero() {
    return CustomHeroCard(
      background: _videoBackground(),
      location: 'Damascus · Syria',
      titleSpans: _title('Where every\n', 'moment', ' is composed'),
      subtitle: 'A landmark of luxury in the heart of Damascus',
      onPrimary: controller.bookNow,
      onSecondary: controller.explore,
    );
  }

  Widget _diningHero() {
    return CustomHeroCard(
      background: _image(DemoData.heroDiningImagePath),
      location: 'Damascus · Syria',
      titleSpans: _title('Refined ', 'flavors', ',\ntimeless elegance.'),
      subtitle: 'A refined dining experience, timeless hospitality.',
      onPrimary: controller.bookNow,
      onSecondary: controller.explore,
    );
  }

  Widget _experiencesHero() {
    return CustomHeroCard(
      background: _image(DemoData.heroExperienceImagePath),
      location: 'Damascus · Syria',
      titleSpans: _title('A Quiet ', 'Luxury', '\nExperience'),
      subtitle: 'Explore authentic experiences, crafted just for you.',
      onPrimary: controller.bookNow,
      onSecondary: controller.explore,
    );
  }

  Widget _offersHero() {
    return CustomHeroCard(
      background: _image(DemoData.heroHomeImagePath),
      location: 'Damascus · Syria',
      titleSpans: _title('Seasonal ', 'Escapes'),
      subtitle: 'Curated packages for an unforgettable stay.',
      primaryLabel: 'View Offers',
      onPrimary: controller.bookNow,
      onSecondary: controller.explore,
    );
  }

  Widget _videoBackground() {
    if (!controller.isVideoReady) {
      return _image(DemoData.heroHomeImagePath);
    }
    final vc = controller.videoController;
    return FittedBox(
      fit: BoxFit.cover,
      child: SizedBox(
        width: vc.value.size.width,
        height: vc.value.size.height,
        child: VideoPlayer(vc),
      ),
    );
  }

  Widget _image(String path) => CustomImage(path: path, fit: BoxFit.cover);

  Widget _section({
    required String title,
    required VoidCallback onDiscoverAll,
    required Widget child,
  }) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      spacing: 16,
      children: [
        Padding(
          padding: const EdgeInsets.symmetric(horizontal: 20),
          child: CustomSectionHeader(title: title, onAction: onDiscoverAll),
        ),
        child,
      ],
    );
  }

  /// Horizontal card carousel. A scrollable Row (which supports `spacing:`)
  /// instead of ListView.separated — the demo lists are 3 items, so lazy
  /// building buys nothing.
  Widget _carousel({required double height, required List<Widget> cards}) {
    return SizedBox(
      height: height,
      child: SingleChildScrollView(
        scrollDirection: Axis.horizontal,
        padding: const EdgeInsets.symmetric(horizontal: 20),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          spacing: 20,
          children: cards,
        ),
      ),
    );
  }

  Widget _roomsList() {
    return _carousel(
      height: 368,
      cards: [
        for (final r in controller.rooms)
          CustomListingCard(
            imagePath: r.imagePath,
            title: r.name,
            subtitle: r.view,
            metaInRow: true,
            meta: [
              CardMeta('assets/icons/ruler.svg', r.area),
              CardMeta('assets/icons/guests.svg', r.guests),
              CardMeta('assets/icons/bed_outline.svg', r.bed),
            ],
            priceAmount: r.priceAmount,
            onTap: controller.explore,
          ),
      ],
    );
  }

  Widget _restaurantsList() {
    return _carousel(
      height: 348,
      cards: [
        for (final r in controller.restaurants)
          CustomListingCard(
            imagePath: r.imagePath,
            title: r.name,
            subtitle: r.cuisine,
            meta: [
              CardMeta('assets/icons/clock.svg', r.hours),
              CardMeta('assets/icons/location.svg', r.location),
            ],
            onTap: controller.explore,
          ),
      ],
    );
  }
}
