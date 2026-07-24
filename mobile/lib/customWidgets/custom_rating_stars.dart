import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:flutter_svg/flutter_svg.dart';
import 'package:get/get.dart';

/// Star rating with optional numeric score and review count, used on room cards
/// and Room Details. Renders [starCount] glyphs — filled (gold) up to
/// `rating.floor()`, outline (grey) after — using the exported Figma stars,
/// which carry their own colors, so they are drawn untinted.
class CustomRatingStars extends StatelessWidget {
  final double rating;
  final int starCount;
  final double starSize;
  final int? reviewCount;
  final bool showScore;

  const CustomRatingStars({
    required this.rating,
    this.starCount = 5,
    this.starSize = 12,
    this.reviewCount,
    this.showScore = true,
    super.key,
  });

  @override
  Widget build(BuildContext context) {
    final TextTheme textStyle = Get.textTheme;
    final filled = rating.floor().clamp(0, starCount);

    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        for (var i = 0; i < starCount; i++)
          Padding(
            padding: EdgeInsets.only(right: i == starCount - 1 ? 0 : 6),
            child: SvgPicture.asset(
              i < filled
                  ? 'assets/icons/star.svg'
                  : 'assets/icons/star_outline.svg',
              width: starSize,
              height: starSize,
            ),
          ),
        if (showScore) ...[
          const SizedBox(width: 8),
          Text(
            rating.toStringAsFixed(1),
            style: textStyle.labelMedium?.copyWith(
              fontFamily: 'Plus Jakarta Sans',
              fontWeight: FontWeight.w600,
              color: AppColors.inkBlack,
            ),
          ),
        ],
        if (reviewCount != null) ...[
          const SizedBox(width: 6),
          Text(
            '($reviewCount reviews)',
            style: textStyle.labelMedium?.copyWith(
              fontFamily: 'DM Sans',
              color: AppColors.taupeBrown,
            ),
          ),
        ],
      ],
    );
  }
}
