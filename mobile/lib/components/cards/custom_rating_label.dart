import 'package:carlton/theme/app_colors.dart';
import 'package:carlton/customWidgets/custom_texts.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';

/// Gold star + rating value + "(reviews)" — the compact rating shown on every
/// Discover listing card (Figma 2195:2668). Shared so the three card types
/// don't each hand-roll it.
class CustomRatingLabel extends StatelessWidget {
  final double rating;
  final int reviews;

  const CustomRatingLabel({
    required this.rating,
    required this.reviews,
    super.key,
  });

  @override
  Widget build(BuildContext context) {
    final TextTheme textStyle = Get.textTheme;
    return Row(
      mainAxisSize: MainAxisSize.min,
      spacing: 10,
      children: [
        // spacing: 0 is deliberate — the star sits flush against the score.
        RowTextComponent(
          text: rating.toStringAsFixed(1),
          icon: Icons.star,
          iconSize: 15,
          iconColor: AppColors.antiqueGold,
          spacing: 0,
          textStyle: textStyle.labelMedium?.copyWith(
            fontFamily: 'DM Sans',
            fontWeight: FontWeight.w600,
            color: AppColors.inkBlack,
          ),
        ),

        Text(
          '($reviews)',
          style: textStyle.labelSmall?.copyWith(
            fontFamily: 'DM Sans',
            color: AppColors.taupeBrown,
          ),
        ),
      ],
    );
  }
}
