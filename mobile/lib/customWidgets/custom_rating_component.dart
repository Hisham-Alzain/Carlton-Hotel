import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:flutter_rating_bar/flutter_rating_bar.dart';
import 'package:flutter_svg/flutter_svg.dart';
import 'package:get/get.dart';

/// Star rating with optional numeric score and review count, used on room cards
/// and Room Details. Renders [starCount] glyphs via [RatingBarIndicator] using
/// the exported Figma star, which carries its own gold color; the unrated
/// portion is tinted grey via [RatingBarIndicator.unratedColor]. Being an
/// indicator it is read-only and supports fractional fill.
class CustomRatingComponent extends StatelessWidget {
  final double rating;
  final int starCount;
  final double starSize;
  final int? reviewCount;
  final bool showScore;

  const CustomRatingComponent({
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

    return Row(
      spacing: 10,
      children: [
        RatingBarIndicator(
          rating: rating,
          itemCount: starCount,
          itemSize: starSize,
          unratedColor: AppColors.pearlGrey,
          itemPadding: const EdgeInsets.only(right: 6),
          itemBuilder: (context, _) =>
              SvgPicture.asset('assets/icons/star.svg'),
        ),
        if (showScore) ...[
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
