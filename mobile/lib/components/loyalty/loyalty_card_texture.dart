import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';

/// The two decorative layers shared by every teal-gold Loyalty surface
/// (`LoyaltyPointsCard`, `AccountProfileCard`): a soft glow disc and a
/// diagonal sheen. Pulled out on the second call site rather than left
/// duplicated — two copies had already drifted (one shadow was opaque where
/// the other was tinted) before either was touched again.
class LoyaltyGlowCircle extends StatelessWidget {
  final double size;

  const LoyaltyGlowCircle({required this.size, super.key});

  @override
  Widget build(BuildContext context) {
    return Container(
      width: size,
      height: size,
      decoration: const BoxDecoration(
        shape: BoxShape.circle,
        color: AppColors.white10,
      ),
    );
  }
}

/// A faint diagonal light band, the glossy-card cue on a membership/credit
/// card visual. Rotated well past the surface bounds so the band's edges
/// never show, only the streak of light crossing it.
class LoyaltySheen extends StatelessWidget {
  const LoyaltySheen({super.key});

  @override
  Widget build(BuildContext context) {
    return IgnorePointer(
      child: Transform.rotate(
        angle: -0.5,
        child: Transform.translate(
          offset: const Offset(60, -40),
          child: Container(
            height: 90,
            decoration: const BoxDecoration(
              gradient: LinearGradient(
                begin: Alignment.centerLeft,
                end: Alignment.centerRight,
                colors: [
                  AppColors.white00,
                  AppColors.white10,
                  AppColors.white00,
                ],
                stops: [0, 0.5, 1],
              ),
            ),
          ),
        ),
      ),
    );
  }
}
