import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:flutter_svg/flutter_svg.dart';

/// Tone of a [CustomInfoBanner] — sets the tint and leading glyph.
enum InfoBannerTone { info, warning, success, danger }

/// Soft rounded banner with a leading icon and message, used across the flow:
/// the 52-days notice, free-cancellation rows, the SSL/PCI note, the payment-
/// processed confirmation, and the cancel warning. [tone] picks the tint +
/// glyph; [bordered] adds the white hairline + drop shadow the cancel-sheet
/// warning uses.
class CustomInfoBanner extends StatelessWidget {
  final String message;
  final InfoBannerTone tone;
  final bool bordered;

  /// Override the tone's default leading glyph (e.g. the calendar used by the
  /// upcoming-stay "next check-in" notice). The tone still drives the tint.
  final String? iconPath;

  const CustomInfoBanner({
    required this.message,
    this.tone = InfoBannerTone.info,
    this.bordered = false,
    this.iconPath,
    super.key,
  });

  ({Color bg, Color icon, String iconPath}) get _style => switch (tone) {
    InfoBannerTone.info => (
      bg: AppColors.statusProgressIconBg,
      icon: AppColors.gold,
      iconPath: 'assets/icons/info.svg',
    ),
    InfoBannerTone.warning => (
      bg: AppColors.statusProgressIconBg,
      icon: AppColors.gold,
      iconPath: 'assets/icons/warning.svg',
    ),
    InfoBannerTone.success => (
      bg: AppColors.statusConfirmedBg,
      icon: AppColors.statusConfirmedText,
      iconPath: 'assets/icons/check.svg',
    ),
    InfoBannerTone.danger => (
      bg: AppColors.dangerSoft,
      icon: AppColors.danger,
      iconPath: 'assets/icons/warning.svg',
    ),
  };

  @override
  Widget build(BuildContext context) {
    final s = _style;
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
      decoration: BoxDecoration(
        color: s.bg,
        borderRadius: BorderRadius.circular(bordered ? 12 : 10),
        border: bordered
            ? Border.all(color: const Color(0x7AFFFFFF), width: 1.18)
            : null,
        boxShadow: bordered
            ? const [
                BoxShadow(
                  color: Color(0x52DBDBDB),
                  blurRadius: 4,
                  offset: Offset(0, 2),
                ),
              ]
            : null,
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Padding(
            padding: const EdgeInsets.only(top: 2),
            child: SvgPicture.asset(
              iconPath ?? s.iconPath,
              width: 14,
              height: 14,
              colorFilter: ColorFilter.mode(s.icon, BlendMode.srcIn),
            ),
          ),
          const SizedBox(width: 8),
          Expanded(
            child: Text(
              message,
              style: const TextStyle(
                fontFamily: 'DM Sans',
                fontSize: 12,
                height: 1.5,
                color: AppColors.navLabel,
              ),
            ),
          ),
        ],
      ),
    );
  }
}
