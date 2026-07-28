import 'package:carlton/components/reviews/review_submit_sheet.dart';
import 'package:carlton/components/reviews/review_tile.dart';
import 'package:carlton/controllers/dining/restaurant_controller.dart';
import 'package:carlton/controllers/reviews/review_controller.dart';
import 'package:carlton/customWidgets/custom_bottom_sheet.dart';
import 'package:carlton/customWidgets/custom_filled_button.dart';
import 'package:carlton/customWidgets/custom_rating_component.dart';
import 'package:carlton/customWidgets/custom_snackbar.dart';
import 'package:carlton/routes/routes.dart';
import 'package:carlton/services/middleware_service.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';

/// The 4th restaurant-detail tab: overall-rating header + a "Write a Review" CTA
/// pinned above the paginated reviews list. The list reads the Rx state of
/// [ReviewController] (the codebase's sanctioned Obx exception) resolved via
/// `Get.find`; the header rating stays on the venue's demo defaults because the
/// venue-detail endpoint is unwired (a Phase 2 deferral).
class RestaurantReviewsTab extends StatelessWidget {
  final RestaurantController c;

  const RestaurantReviewsTab({required this.c, super.key});

  ReviewController get _reviews => Get.find<ReviewController>();

  void _openSubmitSheet() {
    // Auth gate: a POST while unauthenticated returns 401 and fires the global
    // logout — the wrong outcome for a review attempt.
    if (!MiddlewareService.find.isAuthenticated) {
      CustomSnackbars.showInfo(message: 'Sign in to leave a review');
      Get.toNamed(Routes.signIn);
      return;
    }
    CustomBottomSheet.show<bool>(
      title: 'Write a Review',
      subtitle: c.restaurant.name,
      child: ReviewSubmitSheet(controller: _reviews),
    );
  }

  @override
  Widget build(BuildContext context) {
    final TextTheme textStyle = Get.textTheme;
    final reviews = _reviews;

    return Column(
      children: [
        Padding(
          padding: const EdgeInsets.fromLTRB(16, 16, 16, 8),
          child: Container(
            padding: const EdgeInsets.all(16),
            decoration: BoxDecoration(
              color: AppColors.white,
              borderRadius: BorderRadius.circular(14),
              border: Border.all(color: AppColors.black06),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              spacing: 12,
              children: [
                CustomRatingComponent(
                  rating: c.restaurant.rating,
                  reviewCount: c.restaurant.reviews,
                ),
                CustomFilledButton(
                  width: double.infinity,
                  backgroundColor: AppColors.primary,
                  onPressed: _openSubmitSheet,
                  child: const Text('Write a Review'),
                ),
              ],
            ),
          ),
        ),
        Expanded(
          child: Obx(() {
            if (reviews.loading.value) {
              return const Center(child: CircularProgressIndicator());
            }
            if (reviews.hasError.value) {
              return Center(
                child: Padding(
                  padding: const EdgeInsets.all(24),
                  child: Text(
                    'Could not load reviews. Please try again.',
                    textAlign: TextAlign.center,
                    style: textStyle.labelMedium?.copyWith(
                      color: AppColors.dimGrey,
                    ),
                  ),
                ),
              );
            }
            if (reviews.items.isEmpty) {
              return Center(
                child: Padding(
                  padding: const EdgeInsets.all(24),
                  child: Text(
                    'No reviews yet — be the first',
                    textAlign: TextAlign.center,
                    style: textStyle.labelMedium?.copyWith(
                      color: AppColors.dimGrey,
                    ),
                  ),
                ),
              );
            }
            final showLoadingMore = reviews.loadingMore.value;
            return ListView.separated(
              controller: reviews.scrollController,
              padding: const EdgeInsets.fromLTRB(16, 8, 16, 16),
              itemCount: reviews.items.length + (showLoadingMore ? 1 : 0),
              separatorBuilder: (_, _) => const SizedBox(height: 12),
              itemBuilder: (_, index) {
                if (index >= reviews.items.length) {
                  return const Padding(
                    padding: EdgeInsets.all(16),
                    child: Center(child: CircularProgressIndicator()),
                  );
                }
                return ReviewTile(review: reviews.items[index]);
              },
            );
          }),
        ),
      ],
    );
  }
}
