import 'package:carlton/l10n/app_translations.dart';
import 'package:carlton/customWidgets/custom_empty_placeholder.dart';
import 'package:carlton/customWidgets/custom_indicators.dart';
import 'package:carlton/components/cards/custom_rating_label.dart';
import 'package:carlton/components/reviews/review_submit_sheet.dart';
import 'package:carlton/components/reviews/review_tile.dart';
import 'package:carlton/controllers/dining/restaurant_controller.dart';
import 'package:carlton/controllers/reviews/review_controller.dart';
import 'package:carlton/customWidgets/custom_bottom_sheet.dart';
import 'package:carlton/customWidgets/custom_filled_button.dart';
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
      CustomSnackbars.showInfo(message: AppTranslations.signInToReview);
      Get.toNamed(Routes.signIn);
      return;
    }
    CustomBottomSheet.show<bool>(
      title: AppTranslations.writeAReview,
      subtitle: c.restaurant.name,
      child: ReviewSubmitSheet(controller: _reviews),
    );
  }

  @override
  Widget build(BuildContext context) {
    final reviews = _reviews;

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
                CustomRatingLabel(
                  rating: c.restaurant.rating,
                  reviews: c.restaurant.reviews,
                ),
                CustomFilledButton(
                  width: double.infinity,
                  backgroundColor: AppColors.primary,
                  onPressed: _openSubmitSheet,
                  child: Text(AppTranslations.writeAReview),
                ),
              ],
            ),
          ),
        ),
        Expanded(
          child: Obx(() {
            if (reviews.loading.value) {
              return const Center(
                child: SpinningIconIndicator(
                  size: 40,
                  color: AppColors.primary,
                ),
              );
            }

            // Error and empty are the same slot, so both go through
            // CustomEmptyPlaceholder rather than a bare centred Text.
            if (reviews.hasError.value) {
              return Center(
                child: Padding(
                  padding: const EdgeInsets.all(10),
                  child: CustomEmptyPlaceholder(
                    title: AppTranslations.reviewsLoadFailed,
                    subtitle: AppTranslations.tryAgainShort,
                  ),
                ),
              );
            }
            if (reviews.items.isEmpty) {
              return Center(
                child: Padding(
                  padding: const EdgeInsets.all(10),
                  child: CustomEmptyPlaceholder(
                    title: AppTranslations.noReviewsYet,
                    subtitle: AppTranslations.beTheFirstReview,
                  ),
                ),
              );
            }
            final showLoadingMore = reviews.loadingMore.value;
            return ListView.builder(
              controller: reviews.scrollController,
              padding: const EdgeInsets.all(10),
              itemCount: reviews.items.length + (showLoadingMore ? 1 : 0),
              itemBuilder: (_, index) {
                if (index >= reviews.items.length) {
                  return const Padding(
                    padding: EdgeInsets.all(10),
                    child: Center(
                      child: SpinningIconIndicator(
                        size: 24,
                        color: AppColors.primary,
                      ),
                    ),
                  );
                }
                return Padding(
                  padding: const EdgeInsets.all(10),
                  child: ReviewTile(review: reviews.items[index]),
                );
              },
            );
          }),
        ),
      ],
    );
  }
}
