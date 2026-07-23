import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';

/// Discrete step indicator for the booking flow: a row of equal-width segments
/// where the first [currentStep] are filled. Figma booking header uses 6
/// segments, 4px gap, ~3px tall, 2px radius (primary filled / progressTrack idle).
class CustomStepProgressBar extends StatelessWidget {
  final int totalSteps;
  final int currentStep;
  final double height;
  final double gap;
  final Color? activeColor;
  final Color? inactiveColor;

  const CustomStepProgressBar({
    required this.totalSteps,
    required this.currentStep,
    this.height = 3,
    this.gap = 4,
    this.activeColor,
    this.inactiveColor,
    super.key,
  }) : assert(totalSteps > 0),
       assert(currentStep >= 0);

  @override
  Widget build(BuildContext context) {
    final active = activeColor ?? AppColors.primary;
    final inactive = inactiveColor ?? AppColors.iceBlue;

    return Row(
      children: List.generate(totalSteps, (i) {
        final filled = i < currentStep;
        return Expanded(
          child: Padding(
            padding: EdgeInsetsDirectional.only(
              end: i == totalSteps - 1 ? 0 : gap,
            ),
            child: Container(
              height: height,
              decoration: BoxDecoration(
                color: filled ? active : inactive,
                borderRadius: BorderRadius.circular(2),
              ),
            ),
          ),
        );
      }),
    );
  }
}
