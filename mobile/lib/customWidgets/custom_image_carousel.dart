import 'package:carlton/customWidgets/custom_image.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:flutter_svg/flutter_svg.dart';

/// Full-bleed image carousel with prev/next arrows and page dots — generic
/// enough for any multi-photo gallery (room details, listings, etc). Fully
/// controlled: the caller owns [index] and reacts to [onIndexChanged].
class CustomImageCarousel extends StatelessWidget {
  final List<String> images;
  final int index;
  final ValueChanged<int> onIndexChanged;
  final double height;

  /// Optional overlay in the top-right corner (e.g. a close button).
  final Widget? topRight;

  const CustomImageCarousel({
    required this.images,
    required this.index,
    required this.onIndexChanged,
    this.height = 220,
    this.topRight,
    super.key,
  });

  @override
  Widget build(BuildContext context) {
    if (images.isEmpty) return SizedBox(height: height);
    final active = index.clamp(0, images.length - 1);

    return SizedBox(
      height: height,
      width: double.infinity,
      child: Stack(
        children: [
          Positioned.fill(
            child: CustomImage(path: images[active], fit: BoxFit.cover),
          ),
          if (topRight != null)
            Positioned(right: 14, top: 14, child: topRight!),
          if (images.length > 1) ...[
            Positioned(
              left: 14,
              top: height / 2 - 18,
              child: _arrow(
                onTap: () => onIndexChanged(
                  (active - 1 + images.length) % images.length,
                ),
              ),
            ),
            Positioned(
              right: 14,
              top: height / 2 - 18,
              child: _arrow(
                onTap: () => onIndexChanged((active + 1) % images.length),
                flip: true,
              ),
            ),
            Positioned(
              bottom: 14,
              left: 0,
              right: 0,
              child: _dots(images.length, active),
            ),
          ],
        ],
      ),
    );
  }

  Widget _arrow({required VoidCallback onTap, bool flip = false}) {
    Widget glyph = SvgPicture.asset(
      'assets/icons/chevron_left.svg',
      width: 18,
      height: 18,
      colorFilter: const ColorFilter.mode(AppColors.inkBlack, BlendMode.srcIn),
    );
    if (flip) glyph = Transform.flip(flipX: true, child: glyph);
    return InkWell(
      onTap: onTap,
      customBorder: const CircleBorder(),
      child: Container(
        width: 36,
        height: 36,
        alignment: Alignment.center,
        decoration: const BoxDecoration(
          color: AppColors.white73,
          shape: BoxShape.circle,
        ),
        child: glyph,
      ),
    );
  }

  Widget _dots(int count, int active) => Row(
    mainAxisAlignment: MainAxisAlignment.center,
    children: [
      for (var i = 0; i < count; i++)
        Container(
          margin: const EdgeInsets.symmetric(horizontal: 3),
          width: i == active ? 18 : 6,
          height: 6,
          decoration: BoxDecoration(
            color: i == active ? Colors.white : AppColors.white50,
            borderRadius: BorderRadius.circular(3),
          ),
        ),
    ],
  );
}
