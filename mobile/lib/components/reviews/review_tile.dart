import 'package:carlton/l10n/app_translations.dart';
import 'package:carlton/customWidgets/custom_containers.dart';
import 'package:carlton/extensions/date_extension.dart';
import 'package:carlton/models/review.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:flutter_rating_bar/flutter_rating_bar.dart';
import 'package:flutter_svg/flutter_svg.dart';
import 'package:get/get.dart';

/// One published review row: author + date, read-only stars, an optional
/// "Verified stay" pill, and the comment. [Review.createdAt] is a raw ISO string
/// (matches the guide's shape) — parsed defensively here; the whole tile is
/// hidden when the timestamp is missing or unparseable.
class ReviewTile extends StatelessWidget {
  final Review review;

  const ReviewTile({required this.review, super.key});

  @override
  Widget build(BuildContext context) {
    final TextTheme textStyle = Get.textTheme;
    final created = _formatCreatedAt(review.createdAt);
    if (created == null) return const SizedBox.shrink();

    final name = review.authorName.isNotEmpty
        ? review.authorName
        : AppTranslations.guest;
    final comment = review.comment?.trim() ?? '';

    return Card(
      color: AppColors.white,
      shape: ContinuousRectangleBorder(
        borderRadius: BorderRadiusGeometry.circular(14),
        side: BorderSide(color: AppColors.black06),
      ),
      child: Padding(
        padding: const EdgeInsets.all(10),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          spacing: 10,
          children: [
            Row(
              children: [
                Expanded(
                  child: Text(
                    name,
                    style: textStyle.labelLarge?.copyWith(
                      fontWeight: FontWeight.w600,
                      color: AppColors.inkBlack,
                    ),
                  ),
                ),
                Text(
                  created,
                  style: textStyle.labelSmall?.copyWith(
                    fontFamily: 'DM Sans',
                    color: AppColors.taupeBrown,
                  ),
                ),
              ],
            ),
            Row(
              spacing: 10,
              children: [
                RatingBarIndicator(
                  rating: review.rating.toDouble(),
                  itemCount: 5,
                  itemSize: 15,
                  unratedColor: AppColors.pearlGrey,
                  itemBuilder: (context, _) => SvgPicture.asset(
                    'assets/icons/star.svg',
                    colorFilter: const ColorFilter.mode(
                      AppColors.antiqueGold,
                      BlendMode.srcIn,
                    ),
                  ),
                ),
                if (review.isVerifiedStay)
                  PillContainer(
                    backgroundColor: AppColors.cream,
                    child: Text(
                      AppTranslations.verifiedStay,
                      style: textStyle.labelSmall?.copyWith(
                        fontFamily: 'DM Sans',
                        fontWeight: FontWeight.w600,
                        color: AppColors.walnutGold,
                      ),
                    ),
                  ),
              ],
            ),
            if (comment.isNotEmpty)
              Text(
                comment,
                style: textStyle.labelMedium?.copyWith(
                  fontFamily: 'DM Sans',
                  height: 1.5,
                  color: AppColors.dimGrey,
                ),
              ),
          ],
        ),
      ),
    );
  }

  /// Parse the ISO `created_at` defensively; null tells [build] to hide the row.
  String? _formatCreatedAt(String? raw) {
    if (raw == null || raw.isEmpty) return null;
    final parsed = DateTime.tryParse(raw);
    return parsed?.formatDate();
  }
}
