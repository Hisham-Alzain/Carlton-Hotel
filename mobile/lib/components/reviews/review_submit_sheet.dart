import 'package:carlton/controllers/reviews/review_controller.dart';
import 'package:carlton/customWidgets/custom_filled_button.dart';
import 'package:carlton/customWidgets/custom_snackbar.dart';
import 'package:carlton/customWidgets/custom_text_field.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:flutter_rating_bar/flutter_rating_bar.dart';
import 'package:flutter_svg/flutter_svg.dart';
import 'package:get/get.dart';

/// "Write a Review" bottom-sheet body, shared verbatim by the restaurant Reviews
/// tab and Room Details. Star input + optional comment + submit; delegates the
/// POST to the injected [ReviewController]. Mirrors [ServiceRequestSheet]: state
/// is held in [State], not rebuilt in [build].
class ReviewSubmitSheet extends StatefulWidget {
  final ReviewController controller;

  const ReviewSubmitSheet({required this.controller, super.key});

  @override
  State<ReviewSubmitSheet> createState() => _ReviewSubmitSheetState();
}

class _ReviewSubmitSheetState extends State<ReviewSubmitSheet> {
  int _rating = 5;
  // Held in state, not build(): opening the keyboard rebuilds the sheet, and a
  // controller created in build() would drop whatever was typed.
  final TextEditingController _comment = TextEditingController();
  bool _submitting = false;

  @override
  void dispose() {
    _comment.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    setState(() => _submitting = true);
    final ok = await widget.controller.submitReview(
      rating: _rating,
      comment: _comment.text.trim(),
    );
    if (!mounted) return;
    if (ok) {
      Get.back(result: true);
      CustomSnackbars.showSuccess(message: 'Thank you for your review');
    } else {
      // The controller already surfaced the error.
      setState(() => _submitting = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final TextTheme textStyle = Get.textTheme;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      spacing: 12,
      children: [
        Text(
          'Your Rating'.toUpperCase(),
          style: textStyle.labelSmall?.copyWith(
            fontWeight: FontWeight.w700,
            color: AppColors.dimGrey,
          ),
        ),
        Center(
          child: RatingBar.builder(
            initialRating: _rating.toDouble(),
            minRating: 1,
            allowHalfRating: false,
            itemCount: 5,
            itemSize: 40,
            glow: false,
            unratedColor: AppColors.pearlGrey,
            itemPadding: const EdgeInsets.symmetric(horizontal: 4),
            itemBuilder: (context, _) =>
                SvgPicture.asset('assets/icons/star.svg'),
            onRatingUpdate: (value) => setState(() => _rating = value.toInt()),
          ),
        ),
        Text(
          'Your Review (Optional)'.toUpperCase(),
          style: textStyle.labelSmall?.copyWith(
            fontWeight: FontWeight.w700,
            color: AppColors.dimGrey,
          ),
        ),
        CustomTextField(
          controller: _comment,
          textInputType: TextInputType.multiline,
          maxLines: 3,
          hintText: 'Share your experience...',
          fillColor: AppColors.white,
          borderColor: AppColors.linenGrey,
        ),
        CustomFilledButton(
          width: double.infinity,
          backgroundColor: AppColors.lagoonTeal,
          isLoading: _submitting,
          onPressed: (_submitting || _rating < 1) ? null : _submit,
          child: const Text('Submit Review'),
        ),
      ],
    );
  }
}
