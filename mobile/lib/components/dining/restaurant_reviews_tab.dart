import 'package:carlton/components/cards/custom_rating_label.dart';
import 'package:carlton/components/reviews/review_tile.dart';
import 'package:carlton/customWidgets/custom_empty_placeholder.dart';
import 'package:carlton/customWidgets/custom_filled_button.dart';
import 'package:carlton/models/review.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';

/// The 4th restaurant-detail tab: overall-rating header + a "Write a Review" CTA
/// pinned above the paginated reviews list. The header rating stays on the
/// venue's demo defaults because the venue-detail endpoint is unwired (a Phase 2
/// deferral).
///
/// The list state arrives already resolved — the caller owns the `Obx` over
/// `ReviewController`, so this tab renders one frame's worth of values.
class RestaurantReviewsTab extends StatelessWidget {
  final double rating;
  final int reviewCount;

  final List<Review> reviews;
  final bool loading;
  final bool loadingMore;
  final bool hasError;

  /// From `PaginatedControllerMixin` — the list must attach the caller's
  /// controller for scroll-triggered paging to fire.
  final ScrollController scrollController;

  final VoidCallback onWriteReview;

  /// Retry for the error state. The empty state deliberately gets no button —
  /// re-fetching an genuinely empty list returns the same thing.
  final VoidCallback onRetry;

  const RestaurantReviewsTab({
    required this.rating,
    required this.reviewCount,
    required this.reviews,
    required this.loading,
    required this.loadingMore,
    required this.hasError,
    required this.scrollController,
    required this.onWriteReview,
    required this.onRetry,
    super.key,
  });

  @override
  Widget build(BuildContext context) {
    final TextTheme textStyle = Get.textTheme;

    return Column(
      children: [
        Padding(
          padding: const EdgeInsets.all(10),
          child: Container(
            padding: const EdgeInsets.all(10),
            decoration: BoxDecoration(
              color: AppColors.white,
              borderRadius: BorderRadius.circular(14),
              border: Border.all(color: AppColors.black06),
            ),
            child: Column(
              spacing: 10,
              children: [
                CustomRatingLabel(rating: rating, reviews: reviewCount),
                CustomFilledButton(
                  width: double.infinity,
                  backgroundColor: AppColors.primary,
                  onPressed: onWriteReview,
                  child: const Text('Write a Review'),
                ),
              ],
            ),
          ),
        ),
        Expanded(child: _list(textStyle)),
      ],
    );
  }

  Widget _list(TextTheme textStyle) {
    if (loading) return const Center(child: CircularProgressIndicator());

    if (hasError) {
      return CustomEmptyPlaceholder(
        iconWidget: const Icon(
          Icons.cloud_off_outlined,
          size: 50,
          color: AppColors.primary,
        ),
        title: "Couldn't load reviews",
        subtitle: 'Please check your connection and try again.',
        primaryLabel: 'Retry',
        onPrimary: onRetry,
      );
    }
    if (reviews.isEmpty) {
      return const CustomEmptyPlaceholder(
        iconWidget: Icon(
          Icons.rate_review_outlined,
          size: 50,
          color: AppColors.primary,
        ),
        title: 'No reviews yet',
        subtitle: 'Be the first to share your experience.',
      );
    }
    return ListView.builder(
      controller: scrollController,
      padding: const EdgeInsets.all(10),
      itemCount: reviews.length + (loadingMore ? 1 : 0),
      itemBuilder: (_, index) {
        if (index >= reviews.length) {
          return const Padding(
            padding: EdgeInsets.all(10),
            child: Center(child: CircularProgressIndicator()),
          );
        }
        return Padding(
          padding: const EdgeInsets.all(10),
          child: ReviewTile(review: reviews[index]),
        );
      },
    );
  }
}
