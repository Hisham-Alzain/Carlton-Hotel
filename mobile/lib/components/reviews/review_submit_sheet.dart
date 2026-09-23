import 'package:carlton/customWidgets/custom_filled_button.dart';
import 'package:carlton/customWidgets/custom_snackbar.dart';
import 'package:carlton/customWidgets/custom_text_field.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:flutter_rating_bar/flutter_rating_bar.dart';
import 'package:get/get.dart';

/// "Write a Review" bottom-sheet body, shared verbatim by the restaurant Reviews
/// tab and Room Details. Star input + optional comment + submit; the POST is
/// the caller's job via [onSubmit]. Mirrors [ServiceRequestSheet]: state is
/// held in [State], not rebuilt in [build].
class ReviewSubmitSheet extends StatefulWidget {
  /// Returns true when the review was accepted. On false the sheet stays open
  /// and re-enables the button — the caller is expected to have surfaced the
  /// error itself.
  final Future<bool> Function({required int rating, required String comment})
  onSubmit;

  const ReviewSubmitSheet({required this.onSubmit, super.key});

  @override
  State<ReviewSubmitSheet> createState() => _ReviewSubmitSheetState();
}

class _ReviewSubmitSheetState extends State<ReviewSubmitSheet> {
  // Deliberately local, not on ReviewController: the POST already lives there
  // (via [widget.onSubmit]), and this is per-sheet form state. ReviewController
  // is shared and outlives the sheet, so hoisting these would make a half-typed
  // comment and a stale rating reappear the next time the sheet opens.
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
    final ok = await widget.onSubmit(
      rating: _rating,
      comment: _comment.text.trim(),
    );
    if (!mounted) return;
    if (ok) {
      Get.back(result: true);
      CustomSnackbars.showSuccess(message: 'Thank you for your review');
    } else {
      // The caller already surfaced the error.
      setState(() => _submitting = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final TextTheme textStyle = Get.textTheme;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      spacing: 10,
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
            itemSize: 30,
            glow: false,
            unratedColor: AppColors.pearlGrey,
            itemBuilder: (context, _) =>
                const Icon(Icons.star, color: AppColors.antiqueGold),
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
