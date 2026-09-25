import 'package:carlton/components/custom_logo_avatar.dart';
import 'package:carlton/customWidgets/custom_containers.dart';
import 'package:carlton/customWidgets/custom_filled_button.dart';
import 'package:carlton/customWidgets/custom_image.dart';
import 'package:carlton/l10n/app_translations.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';

/// AI Concierge entry banner on the reservation-state Home (Figma 2237:3914):
/// a labelled prompt beside a stacked-photo cluster, over an "Ask the
/// Concierge" button that opens the concierge screen.
class CustomAiConciergeBanner extends StatelessWidget {
  final VoidCallback onTap;

  const CustomAiConciergeBanner({required this.onTap, super.key});

  @override
  Widget build(BuildContext context) {
    final TextTheme textStyle = Get.textTheme;

    return Card(
      // No self-margin: the parent owns the inset, so this sits flush in Home's
      // SliverPadding alongside the other reservation-state cards.
      margin: const EdgeInsets.all(10),
      color: AppColors.white,
      surfaceTintColor: Colors.transparent,
      elevation: 1,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(14),
        side: const BorderSide(color: AppColors.black06),
      ),
      child: Padding(
        padding: const EdgeInsets.all(10),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          spacing: 10,
          children: [
            Row(
              crossAxisAlignment: CrossAxisAlignment.center,
              spacing: 10,
              children: [
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    spacing: 10,
                    children: [
                      PillContainer(
                        gradient: LinearGradient(
                          colors: [
                            AppColors.primary.withValues(alpha: 0.2),
                            AppColors.dustyTeal.withValues(alpha: 0.2),
                          ],
                        ),
                        radius: 22,
                        padding: const EdgeInsets.symmetric(
                          horizontal: 10,
                          vertical: 5,
                        ),
                        child: Row(
                          mainAxisSize: MainAxisSize.min,
                          spacing: 10,
                          children: [
                            // No ring: the badge sits on the pill's own tint,
                            // where a white border would read as a halo.
                            const CustomLogoAvatar(size: 25, borderWidth: 0),
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
                SizedBox(
                  width: 100,
                  height: 80,
                  child: Stack(
                    alignment: Alignment.center,
                    children: [
                      Positioned(
                        left: 0,
                        child: Transform.rotate(
                          angle: -0.3,
                          child: _photo(
                            'assets/images/room_classic_courtyard.jpg',
                          ),
                        ),
                      ),
                      Positioned(
                        right: 0,
                        child: Transform.rotate(
                          angle: 0.25,
                          child: _photo('assets/images/room_deluxe_city.jpg'),
                        ),
                      ),
                      _photo('assets/images/room_premier_terrace.jpg'),
                    ],
                  ),
                ),
              ],
            ),
            CustomFilledButton(
              width: double.infinity,
              height: 50,
              onPressed: onTap,
              backgroundColor: AppColors.primary,
              foregroundColor: AppColors.white,
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(6),
              ),
              textStyle: textStyle.labelLarge?.copyWith(
                fontWeight: FontWeight.w600,
              ),
              child: Text(AppTranslations.askTheConcierge),
            ),
          ],
        ),
      ),
    );
  }

  Widget _photo(String source) => ClipRRect(
    borderRadius: BorderRadius.circular(4),
    child: CustomImage(
      source: source,
      width: 50,
      height: 50,
      fit: BoxFit.cover,
    ),
  );
}
