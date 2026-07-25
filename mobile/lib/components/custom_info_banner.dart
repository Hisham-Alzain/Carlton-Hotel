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

  ({Color bg, Color icon, String iconPath}) get _style => switch (tone) {
    InfoBannerTone.info => (
      bg: AppColors.white10,
      icon: AppColors.inkBlack,
      iconPath: 'assets/icons/info.svg',
    ),

    InfoBannerTone.warning => (
      bg: AppColors.antiqueGold08,
      icon: AppColors.antiqueGold,
      iconPath: 'assets/icons/warning.svg',
    ),

    InfoBannerTone.success => (
      bg: AppColors.successGreen09,
      icon: AppColors.successGreen,
      iconPath: 'assets/icons/check.svg',
    ),

    InfoBannerTone.danger => (
      bg: AppColors.crimsonRed08,
      icon: AppColors.brickRed,
      iconPath: 'assets/icons/warning.svg',
    ),
  };

  @override
  Widget build(BuildContext context) {
    final TextTheme textstyle = Get.textTheme;

    final s = _style;
    return Container(
      padding: const EdgeInsets.all(10),
      decoration: BoxDecoration(
        color: s.bg,
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
            iconPath ?? s.iconPath,

            colorFilter: ColorFilter.mode(s.icon, BlendMode.srcIn),
          ),
          Flexible(
            child: Text(
              message,
              style: textstyle.labelMedium?.copyWith(
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
