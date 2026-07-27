import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:flutter_svg/flutter_svg.dart';
import 'package:get/get.dart';

/// A settings-style row rendered as a standalone light-grey card (Figma Account
/// / Preferences): optional leading icon badge · title (+ optional subtitle) ·
/// trailing slot. The trailing defaults to a chevron; pass a [Switch], a value
/// [Text], or any widget to override it.
///
/// Provide the leading as either a Material [icon] or an [iconAsset] SVG path;
/// both are tinted primary inside the badge.
class CustomListRow extends StatelessWidget {
  final IconData? icon;
  final String? iconAsset;
  final String title;
  final String? subtitle;
  final Widget? trailing;
  final VoidCallback? onTap;

  const CustomListRow({
    required this.title,
    this.icon,
    this.iconAsset,
    this.subtitle,
    this.trailing,
    this.onTap,
    super.key,
  });

  @override
  Widget build(BuildContext context) {
    final TextTheme textStyle = Get.textTheme;

    return Material(
      color: AppColors.whisperGrey,
      borderRadius: BorderRadius.circular(14),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(14),
        child: Padding(
          padding: const EdgeInsets.all(14),
          child: Row(
            spacing: 14,
            children: [
              if (icon != null || iconAsset != null)
                Container(
                  width: 40,
                  height: 40,
                  alignment: Alignment.center,
                  decoration: BoxDecoration(
                    color: AppColors.white,
                    borderRadius: BorderRadius.circular(10),
                  ),
                  child: iconAsset != null
                      ? SvgPicture.asset(
                          iconAsset!,
                          width: 18,
                          height: 18,
                          colorFilter: const ColorFilter.mode(
                            AppColors.primary,
                            BlendMode.srcIn,
                          ),
                        )
                      : Icon(icon, size: 20, color: AppColors.primary),
                ),
              Expanded(
                child: Column(
                  spacing: 3,
                  crossAxisAlignment: CrossAxisAlignment.start,
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Text(
                      title,
                      style: textStyle.labelLarge?.copyWith(
                        fontWeight: FontWeight.w600,
                        color: AppColors.primary,
                      ),
                    ),
                    if (subtitle != null)
                      Text(
                        subtitle!,
                        style: textStyle.labelSmall?.copyWith(
                          fontFamily: 'DM Sans',
                          color: AppColors.primary50,
                        ),
                      ),
                  ],
                ),
              ),
              trailing ??
                  const Icon(
                    Icons.chevron_right,
                    size: 20,
                    color: AppColors.dimGrey,
                  ),
            ],
          ),
        ),
      ),
    );
  }
}
