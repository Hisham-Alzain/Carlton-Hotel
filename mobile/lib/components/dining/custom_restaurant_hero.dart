import 'package:carlton/l10n/app_translations.dart';
import 'package:carlton/components/cards/custom_rating_label.dart';
import 'package:carlton/customWidgets/custom_filled_button.dart';
import 'package:carlton/customWidgets/custom_image.dart';
import 'package:carlton/customWidgets/custom_texts.dart';
import 'package:carlton/models/home_models.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
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
            left: 10,
            right: 10,
            bottom: 10,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              spacing: 10,
              children: [
                CustomRatingLabel(
                  rating: restaurant.rating,
                  reviews: restaurant.reviews,
                ),
                Text(
                  restaurant.name,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: textStyle.headlineSmall?.copyWith(
                    fontWeight: FontWeight.w700,
                    color: AppColors.white,
                  ),
                ),
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    Text(
                      restaurant.cuisine,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: textStyle.labelMedium?.copyWith(
                        fontFamily: 'DM Sans',
                        color: AppColors.white73,
                      ),
                    ),
                    CustomFilledButton(
                      // Was padding-driven (the pill's default vertical: 10);
                      // pinned so it doesn't take CustomFilledButton's 50.
                      height: 40,
                      onPressed: onReserve,
                      backgroundColor: AppColors.white,
                      foregroundColor: AppColors.primary,
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(10),
                      ),

                      child: RowTextComponent(
                        spacing: 10,
                        text: AppTranslations.reserve,
                        iconPath: 'assets/icons/reserve.svg',
                        iconColor: AppColors.primary,
                      ),
                    ),
                  ],
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
