import 'package:carlton/customWidgets/custom_image.dart';
import 'package:carlton/customWidgets/custom_pill_button.dart';
import 'package:carlton/l10n/app_translations.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:flutter_svg/flutter_svg.dart';
import 'package:get/get.dart';

/// AI Concierge entry banner on the reservation-state Home (Figma 2197:3322):
/// a labelled prompt beside a stacked-photo cluster, over an "Ask the
/// Concierge" button that opens the concierge screen.
class CustomAiConciergeBanner extends StatelessWidget {
  final VoidCallback onTap;

  const CustomAiConciergeBanner({required this.onTap, super.key});

  @override
  Widget build(BuildContext context) {
    final TextTheme textStyle = Get.textTheme;

    return Card(
      margin: EdgeInsets.zero,
      color: AppColors.white,
      surfaceTintColor: Colors.transparent,
      elevation: 0.5,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(14),
        side: const BorderSide(color: AppColors.black06),
      ),
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          spacing: 16,
          children: [
            Row(
              crossAxisAlignment: CrossAxisAlignment.center,
              spacing: 12,
              children: [
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    spacing: 10,
                    children: [
                      Container(
                        padding: const EdgeInsets.symmetric(
                          horizontal: 10,
                          vertical: 5,
                        ),
                        decoration: BoxDecoration(
                          gradient: LinearGradient(
                            colors: [
                              AppColors.primary.withValues(alpha: 0.2),
                              AppColors.dustyTeal.withValues(alpha: 0.2),
                            ],
                          ),
                          borderRadius: BorderRadius.circular(22),
                        ),
                        child: Row(
                          mainAxisSize: MainAxisSize.min,
                          spacing: 4,
                          children: [
                            SvgPicture.asset(
                              'assets/icons/concierge_spark.svg',
                              width: 14,
                              height: 14,
                              colorFilter: const ColorFilter.mode(
                                AppColors.primary,
                                BlendMode.srcIn,
                              ),
                            ),
                            Text(
                              'AI Concierge',
                              style: textStyle.labelSmall?.copyWith(
                                fontWeight: FontWeight.w500,
                                color: AppColors.inkBlack,
                              ),
                            ),
                          ],
                        ),
                      ),
                      Text(
                        AppTranslations.howMayIAssist,
                        style: textStyle.titleMedium?.copyWith(
                          fontWeight: FontWeight.w600,
                          color: AppColors.inkBlack,
                        ),
                      ),
                      Text(
                        AppTranslations.helpDescription,
                        style: textStyle.labelMedium?.copyWith(
                          fontFamily: 'DM Sans',
                          color: AppColors.slateGrey,
                        ),
                      ),
                    ],
                  ),
                ),
                const _PhotoCluster(),
              ],
            ),
            CustomPillButton(
              label: 'Ask the Concierge',
              onTap: onTap,
              backgroundColor: AppColors.primary,
              foregroundColor: AppColors.white,
              radius: 6,
              height: 44,
              expand: true,
            ),
          ],
        ),
      ),
    );
  }
}

/// The three tilted, overlapping room photos in the top-right of the banner.
class _PhotoCluster extends StatelessWidget {
  const _PhotoCluster();

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: 96,
      height: 76,
      child: Stack(
        alignment: Alignment.center,
        children: [
          Positioned(
            left: 0,
            child: Transform.rotate(
              angle: -0.27,
              child: _photo('assets/images/room_classic_courtyard.jpg'),
            ),
          ),
          Positioned(
            right: 0,
            child: Transform.rotate(
              angle: 0.24,
              child: _photo('assets/images/room_deluxe_city.jpg'),
            ),
          ),
          _photo('assets/images/room_premier_terrace.jpg'),
        ],
      ),
    );
  }

  Widget _photo(String source) => ClipRRect(
    borderRadius: BorderRadius.circular(4),
    child: CustomImage(
      source: source,
      width: 40,
      height: 50,
      fit: BoxFit.cover,
    ),
  );
}
