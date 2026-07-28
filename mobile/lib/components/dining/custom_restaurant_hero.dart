import 'package:carlton/customWidgets/custom_image.dart';
import 'package:carlton/customWidgets/custom_pill_button.dart';
import 'package:carlton/models/home_models.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:flutter_svg/flutter_svg.dart';
import 'package:get/get.dart';

/// The restaurant detail hero (Figma): image + dark gradient, floating back
/// button, and the Open-Now badge · rating · name · Reserve · tagline overlay.
class CustomRestaurantHero extends StatelessWidget {
  final RestaurantItem restaurant;
  final VoidCallback onReserve;

  const CustomRestaurantHero({
    required this.restaurant,
    required this.onReserve,
    super.key,
  });

  @override
  Widget build(BuildContext context) {
    final TextTheme textStyle = Get.textTheme;

    return SizedBox(
      height: 250,
      width: double.infinity,
      child: Stack(
        fit: StackFit.expand,
        children: [
          CustomImage(source: restaurant.imagePath, fit: BoxFit.cover),
          const DecoratedBox(
            decoration: BoxDecoration(
              gradient: LinearGradient(
                begin: Alignment.topCenter,
                end: Alignment.bottomCenter,
                colors: [Colors.transparent, AppColors.duskScrim83],
                stops: [0.4, 1],
              ),
            ),
          ),
          Positioned(
            top: MediaQuery.of(context).padding.top + 8,
            left: 16,
            child: Material(
              color: AppColors.black70,
              shape: const CircleBorder(),
              child: InkWell(
                onTap: () => Get.back(),
                customBorder: const CircleBorder(),
                child: const SizedBox(
                  width: 36,
                  height: 36,
                  child: Icon(
                    Icons.arrow_back,
                    size: 18,
                    color: AppColors.white,
                  ),
                ),
              ),
            ),
          ),
          Positioned(
            left: 16,
            right: 16,
            bottom: 16,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              spacing: 6,
              children: [
                _HeroRating(
                  rating: restaurant.rating,
                  reviews: restaurant.reviews,
                ),
                Row(
                  spacing: 10,
                  children: [
                    Expanded(
                      child: Text(
                        restaurant.name,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: textStyle.headlineSmall?.copyWith(
                          fontSize: 22,
                          fontWeight: FontWeight.w700,
                          color: AppColors.white,
                        ),
                      ),
                    ),
                    CustomPillButton(
                      label: 'Reserve',
                      onTap: onReserve,
                      iconAsset: 'assets/icons/reserve.svg',
                      fontSize: 13,
                    ),
                  ],
                ),
                Text(
                  restaurant.cuisine,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: textStyle.labelMedium?.copyWith(
                    fontFamily: 'DM Sans',
                    fontSize: 13,
                    color: AppColors.white73,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

/// A gold-tinted icon + hours/location line for the hero's floating meta card.
class RestaurantMetaLine extends StatelessWidget {
  final String icon;
  final String text;

  const RestaurantMetaLine({required this.icon, required this.text, super.key});

  @override
  Widget build(BuildContext context) {
    return Row(
      mainAxisSize: MainAxisSize.min,
      spacing: 6,
      children: [
        SvgPicture.asset(
          icon,
          width: 14,
          height: 14,
          colorFilter: const ColorFilter.mode(
            AppColors.antiqueGold,
            BlendMode.srcIn,
          ),
        ),
        Flexible(
          child: Text(
            text,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: Get.textTheme.labelMedium?.copyWith(
              fontFamily: 'DM Sans',
              fontSize: 11,
              color: AppColors.slateGrey,
            ),
          ),
        ),
      ],
    );
  }
}

class _HeroRating extends StatelessWidget {
  final double rating;
  final int reviews;

  const _HeroRating({required this.rating, required this.reviews});

  @override
  Widget build(BuildContext context) {
    final TextTheme textStyle = Get.textTheme;

    return Row(
      mainAxisSize: MainAxisSize.min,
      spacing: 4,
      children: [
        SvgPicture.asset('assets/icons/star_hero.svg', width: 14, height: 14),
        Text(
          rating.toString(),
          style: textStyle.labelMedium?.copyWith(
            fontWeight: FontWeight.w700,
            color: AppColors.white,
          ),
        ),
        Text(
          '($reviews)',
          style: textStyle.labelSmall?.copyWith(
            fontFamily: 'DM Sans',
            color: AppColors.white73,
          ),
        ),
      ],
    );
  }
}
