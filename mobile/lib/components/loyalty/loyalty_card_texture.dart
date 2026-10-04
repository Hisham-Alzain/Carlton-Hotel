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

/// The teal-gold foil card itself: gradient, gold hairline, soft teal shadow,
/// the glow discs and the sheen, with [child] on top. Used by the loyalty
/// card, the Account card and the My Profile header.
class TealFoilCard extends StatelessWidget {
  final Widget child;
  final double radius;
  final double shadowAlpha;
  final double shadowBlur;
  final double shadowOffset;
  final List<Widget> glows;

  const TealFoilCard({
    required this.child,
    this.radius = 18,
    this.shadowAlpha = 0.3,
    this.shadowBlur = 16,
    this.shadowOffset = 8,
    this.glows = const [
      PositionedDirectional(
        top: -40,
        end: -20,
        child: LoyaltyGlowCircle(size: 130),
      ),
    ],
    super.key,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      // Load-bearing: the glow and sheen layers overflow the card bounds by
      // design, and without the clip they paint over the scaffold.
      clipBehavior: Clip.antiAlias,
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(radius),
        gradient: const LinearGradient(
          begin: AlignmentDirectional.topStart,
          end: AlignmentDirectional.bottomEnd,
          colors: [AppColors.primary, AppColors.abyssTeal],
        ),
        // A gold hairline, not white — the edge of a foil-trimmed card rather
        // than a generic dark panel.
        border: Border.all(color: AppColors.antiqueGold56),
        boxShadow: [
          // Translucent, not the opaque swatch — a solid dark fill paints as
          // a flat smudge under the card rather than a soft drop shadow.
          BoxShadow(
            color: AppColors.abyssTeal.withValues(alpha: shadowAlpha),
            blurRadius: shadowBlur,
            offset: Offset(0, shadowOffset),
          ),
        ],
      ),
      child: Stack(
        children: [
          ...glows,
          const Positioned.fill(child: LoyaltySheen()),
          child,
        ],
      ),
    );
  }
}

/// The white, gold-edged panel under the loyalty card (activity list and
/// stats grid), so the two read as one set.
const loyaltyPanelDecoration = BoxDecoration(
  color: AppColors.white,
  borderRadius: BorderRadius.all(Radius.circular(18)),
  border: Border.fromBorderSide(BorderSide(color: AppColors.antiqueGold20)),
  boxShadow: [
    BoxShadow(
      color: AppColors.slateShadow04,
      blurRadius: 14,
      offset: Offset(0, 6),
    ),
  ],
);
