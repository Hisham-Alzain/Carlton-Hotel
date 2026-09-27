import 'package:carlton/l10n/app_translations.dart';
import 'package:carlton/customWidgets/custom_filled_button.dart';
import 'package:carlton/customWidgets/custom_image.dart';
import 'package:carlton/customWidgets/custom_outlined_button.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:carlton/customWidgets/custom_texts.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:video_player/video_player.dart';

class CustomHomeContainer extends StatelessWidget {
  final String location;
  final String title;
  final String subtitle;

  /// The hero background: a `/public/home-sliders` photo URL, or a bundled
  /// `assets/…` still from [AppAssets] when the slider has no photo (and for
  /// the video hero, where the still is only the poster). [CustomImage] accepts
  /// either.
  final String imagePath;
  final VideoPlayerController? videoController;
  final bool videoReady;

  /// Null falls back to the localized default (Book Now / Explore), resolved in
  /// `build` so the text follows a language switch instead of freezing at the
  /// locale that was active when this widget was constructed.
  final String? primaryLabel;
  final String? secondaryLabel;
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
    this.primaryLabel,
    this.secondaryLabel,
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
                  colors: [Colors.transparent, AppColors.black70],
                  stops: [0.50, 1],
                ),
              ),
            ),
          ),
          Positioned(
            left: -50,
            right: -50,
            bottom: -60,
            height: 210,
            child: DecoratedBox(
              decoration: BoxDecoration(
                boxShadow: [
                  BoxShadow(
                    color: AppColors.whisperGrey.withValues(alpha: 0.9),
                    blurRadius: 25,
                  ),
                ],
              ),
            ),
          ),
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
                      RowTextComponent(
                        text: location.toUpperCase(),
                        icon: Icons.location_on_outlined,
                        iconColor: Colors.white,
                        spacing: 10,
                        textStyle: textStyle.labelSmall?.copyWith(
                          color: Colors.white,
                        ),
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
                  child: Column(
                    spacing: 10,
                    children: [
                      DecoratedBox(
                        decoration: const BoxDecoration(
                          boxShadow: [
                            BoxShadow(
                              color: AppColors.mistGrey,
                              blurRadius: 4,
                              offset: Offset(0, 4),
                            ),
                          ],
                        ),
                        child: CustomFilledButton(
                          width: 300,
                          onPressed: onPrimary,
                          child: Text(
                            (primaryLabel ?? AppTranslations.bookNowLabel)
                                .toUpperCase(),
                          ),
                        ),
                      ),
                      CustomOutlinedButton(
                        width: 300,
                        onPressed: onSecondary,
                        child: Text(
                          (secondaryLabel ?? AppTranslations.exploreLabel)
                              .toUpperCase(),
                        ),
                      ),
                    ],
                  ),
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
    var charOffset = 0;
    for (final match in pattern.allMatches(title)) {
      if (match.start > charOffset) {
        spans.add(TextSpan(text: title.substring(charOffset, match.start)));
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
      charOffset = match.end;
    }
    if (charOffset < title.length) {
      spans.add(TextSpan(text: title.substring(charOffset)));
    }
    return spans;
  }

  Widget _background() {
    final heroVideoController = videoController;
    if (heroVideoController != null && videoReady) {
      return FittedBox(
        fit: BoxFit.cover,
        child: SizedBox(
          width: heroVideoController.value.size.width,
          height: heroVideoController.value.size.height,
          child: VideoPlayer(heroVideoController),
        ),
      );
    }
    // Either a slider URL or a bundled 4x Figma export; CustomImage decodes
    // both at screen width rather than native size.
    return CustomImage(source: imagePath, fit: BoxFit.cover);
  }
}
