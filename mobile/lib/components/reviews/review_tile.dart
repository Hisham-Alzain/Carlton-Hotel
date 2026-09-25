import 'package:carlton/customWidgets/custom_containers.dart';
import 'package:carlton/extensions/date_extension.dart';
import 'package:carlton/models/review.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:flutter_rating_bar/flutter_rating_bar.dart';
import 'package:get/get.dart';

/// One published review row: author + date, read-only stars, an optional
/// "Verified stay" pill, and the comment. The whole tile is hidden when
/// [Review.createdAt] is absent — the model already parsed it, so an
/// unparseable timestamp arrives here as null.
class ReviewTile extends StatelessWidget {
  final Review review;

  const ReviewTile({required this.review, super.key});

  @override
  Widget build(BuildContext context) {
    final TextTheme textStyle = Get.textTheme;
    final createdAt = review.createdAt;
    if (createdAt == null) return const SizedBox.shrink();
    final created = createdAt.formatDatePicker();

    final name = review.authorName.isNotEmpty ? review.authorName : 'Guest';
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
                  itemBuilder: (context, _) =>
                      const Icon(Icons.star, color: AppColors.antiqueGold),
                ),
                if (review.isVerifiedStay)
                  PillContainer(
                    backgroundColor: AppColors.cream,
                    child: Text(
                      'Verified stay',
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

}
