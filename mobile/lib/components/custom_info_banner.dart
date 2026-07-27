import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:flutter_svg/flutter_svg.dart';
import 'package:get/get.dart';

/// Tone of a [CustomInfoBanner] — sets the tint and leading glyph.
//TODO: move to enum
enum InfoBannerTone { info, warning, success, danger }

/// Soft rounded banner with a leading icon and message, used across the flow:
/// the 52-days notice, free-cancellation rows, the SSL/PCI note, the payment-
/// processed confirmation, and the cancel warning. [tone] picks the tint +
/// glyph; [bordered] adds the white hairline + drop shadow the cancel-sheet
/// warning uses.
class CustomInfoBanner extends StatelessWidget {
  final String message;
  final InfoBannerTone tone;

  /// Override the tone's default leading glyph (e.g. the calendar used by the
  /// upcoming-stay "next check-in" notice). The tone still drives the tint.
  final String? iconPath;

  const CustomInfoBanner({
    required this.message,
    this.tone = InfoBannerTone.info,
    this.iconPath,
    super.key,
  });

  ({Color backgroundColor, Color iconColor, String iconPath}) get _style =>
      switch (tone) {
        InfoBannerTone.info => (
          backgroundColor: AppColors.white10,
          iconColor: AppColors.inkBlack,
          iconPath: 'assets/icons/info.svg',
        ),

        InfoBannerTone.warning => (
          backgroundColor: AppColors.antiqueGold08,
          iconColor: AppColors.antiqueGold,
          iconPath: 'assets/icons/warning.svg',
        ),

        InfoBannerTone.success => (
          backgroundColor: AppColors.successGreen09,
          iconColor: AppColors.successGreen,
          iconPath: 'assets/icons/check.svg',
        ),

        InfoBannerTone.danger => (
          backgroundColor: AppColors.crimsonRed08,
          iconColor: AppColors.brickRed,
          iconPath: 'assets/icons/warning.svg',
        ),
      };

  @override
  Widget build(BuildContext context) {
    final TextTheme textStyle = Get.textTheme;

    final toneStyle = _style;
    return Container(
      padding: const EdgeInsets.all(10),
      decoration: BoxDecoration(
        color: toneStyle.backgroundColor,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: AppColors.white48, width: 1),
        boxShadow: [
          BoxShadow(
            color: AppColors.pebbleGrey32,
            blurRadius: 4,
            offset: Offset(0, 2),
          ),
        ],
      ),
      child: Row(
        spacing: 10,
        children: [
          SvgPicture.asset(
            iconPath ?? toneStyle.iconPath,

            colorFilter: ColorFilter.mode(toneStyle.iconColor, BlendMode.srcIn),
          ),
          Flexible(
            child: Text(
              message,
              style: textStyle.labelMedium?.copyWith(
                fontFamily: 'DM Sans',
                color: AppColors.inkBlack,
              ),
            ),
          ),
        ],
      ),
    );
  }
}
