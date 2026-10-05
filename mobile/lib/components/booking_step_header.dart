import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:smooth_page_indicator/smooth_page_indicator.dart';

/// The booking flow's app bar: the step title and a round close button.
/// [onClose] is passed in so each screen decides where closing leads.
class BookingStepAppBar extends StatelessWidget implements PreferredSizeWidget {
  final String title;
  final VoidCallback onClose;

  const BookingStepAppBar({
    required this.title,
    required this.onClose,
    super.key,
  });

  @override
  Widget build(BuildContext context) {
    return AppBar(
      title: Text(title),
      iconTheme: const IconThemeData(color: Colors.black),
      actions: [
        Container(
          decoration: const BoxDecoration(
            shape: BoxShape.circle,
            color: AppColors.whisperGrey,
          ),
          child: IconButton(
            onPressed: onClose,
            icon: const Icon(Icons.close, color: AppColors.inkBlack),
          ),
        ),
      ],
    );
  }

  @override
  Size get preferredSize => const Size.fromHeight(kToolbarHeight);
}

/// The six dots across the top of every booking step; [step] is 0-based.
class BookingStepIndicator extends StatelessWidget {
  final int step;

  const BookingStepIndicator({required this.step, super.key});

  @override
  Widget build(BuildContext context) {
    return AnimatedSmoothIndicator(
      activeIndex: step,
      count: 6,
      effect: const SlideEffect(
        dotHeight: 5,
        dotWidth: 50,
        spacing: 20,
        activeDotColor: AppColors.primary,
        dotColor: AppColors.iceBlue,
      ),
    );
  }
}
