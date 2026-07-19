import 'package:carlton/customWidgets/custom_filled_button.dart';
import 'package:carlton/customWidgets/custom_image.dart';
import 'package:carlton/customWidgets/custom_outlined_button.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:flutter_svg/flutter_svg.dart';
import 'package:get/get.dart';
import 'package:video_player/video_player.dart';

class CustomHomeContainer extends StatelessWidget {
  final String location;
  final String title;
  final String subtitle;
  final String imagePath;
  final VideoPlayerController? videoController;
  final bool videoReady;
  final String primaryLabel;
  final String secondaryLabel;
  final VoidCallback? onPrimary;
  final VoidCallback? onSecondary;
  final double height;

  const CustomHomeContainer({
    required this.location,
    required this.title,
    required this.subtitle,
    required this.imagePath,
    this.videoController,
    this.videoReady = false,
    this.primaryLabel = 'Book Now',
    this.secondaryLabel = 'Explore',
    this.onPrimary,
    this.onSecondary,
    this.height = 500,
    super.key,
  });

  @override
  Widget build(BuildContext context) {
    final TextTheme textStyle = Get.textTheme;

    return Container(
      clipBehavior: Clip.antiAlias,
      decoration: BoxDecoration(borderRadius: BorderRadius.circular(20)),
      child: Stack(
        children: [
          Positioned.fill(child: _background()),
          const Positioned.fill(
            child: DecoratedBox(
              decoration: BoxDecoration(
                gradient: LinearGradient(
                  begin: Alignment.topCenter,
                  end: Alignment.bottomCenter,
                  colors: [Color(0x00000000), Color(0xB3000000)],
                  stops: [0.50, 1],
                ),
              ),
            ),
          ),
          _ctaGlow(),
          ConstrainedBox(
            constraints: BoxConstraints(minHeight: height),
            child: Column(
              mainAxisAlignment: MainAxisAlignment.end,
              mainAxisSize: MainAxisSize.min,
              children: [
                Padding(
                  padding: const EdgeInsets.all(20),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    spacing: 20,
                    children: [
                      Row(
                        spacing: 10,
                        children: [
                          SvgPicture.asset(
                            'assets/icons/pin.svg',
                            width: 12,
                            height: 12,
                          ),
                          Text(
                            location.toUpperCase(),
                            style: textStyle.labelSmall?.copyWith(
                              color: Colors.white,
                            ),
                          ),
                        ],
                      ),
                      Text.rich(
                        TextSpan(
                          style: textStyle.headlineMedium?.copyWith(
                            color: Colors.white,
                            fontWeight: FontWeight.w300,
                          ),
                          children: _titleSpans(title),
                        ),
                      ),
                      Text(
                        subtitle,
                        style: textStyle.labelLarge?.copyWith(
                          fontFamily: 'Cormorant',
                          color: Colors.white,
                          fontWeight: FontWeight.w400,
                        ),
                      ),
                    ],
                  ),
                ),
                Padding(
                  padding: const EdgeInsets.only(bottom: 20),
                  child: _ctas(),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  /// Splits `title` on `*word*` markup so callers pass a plain string while
  /// the wrapped portion still renders in the display italic.
  List<InlineSpan> _titleSpans(String title) {
    final spans = <InlineSpan>[];
    final pattern = RegExp(r'\*(.+?)\*');
    var index = 0;
    for (final match in pattern.allMatches(title)) {
      if (match.start > index) {
        spans.add(TextSpan(text: title.substring(index, match.start)));
      }
      spans.add(
        TextSpan(
          text: match.group(1),
          style: const TextStyle(
            fontStyle: FontStyle.italic,
            fontWeight: FontWeight.w400,
          ),
        ),
      );
      index = match.end;
    }
    if (index < title.length) {
      spans.add(TextSpan(text: title.substring(index)));
    }
    return spans;
  }

  Widget _background() {
    final vc = videoController;
    if (vc != null && videoReady) {
      return FittedBox(
        fit: BoxFit.cover,
        child: SizedBox(
          width: vc.value.size.width,
          height: vc.value.size.height,
          child: VideoPlayer(vc),
        ),
      );
    }
    return CustomImage(path: imagePath, fit: BoxFit.cover);
  }

  /// Soft light glow sitting behind the CTAs so the dark photo fades into a
  /// lighter patch there instead of the buttons floating on a hard edge.
  /// Clipped to the card's rounded corners by the outer Container.
  Widget _ctaGlow() {
    return Positioned(
      left: -50,
      right: -50,
      bottom: -60,
      height: 220,
      child: DecoratedBox(
        decoration: BoxDecoration(
          boxShadow: [
            BoxShadow(
              color: AppColors.heroGlow.withValues(alpha: 0.9),
              blurRadius: 25,
            ),
          ],
        ),
      ),
    );
  }

  Widget _ctas() {
    return Column(
      spacing: 10,
      children: [
        DecoratedBox(
          decoration: const BoxDecoration(
            boxShadow: [
              BoxShadow(
                color: Color(0xFFD4D4D4),
                blurRadius: 4,
                offset: Offset(0, 4),
              ),
            ],
          ),
          child: CustomFilledButton(
            width: 300,
            onPressed: onPrimary,
            child: Text(primaryLabel.toUpperCase()),
          ),
        ),
        CustomOutlinedButton(
          width: 300,
          onPressed: onSecondary,
          child: Text(secondaryLabel.toUpperCase()),
        ),
      ],
    );
  }
}
