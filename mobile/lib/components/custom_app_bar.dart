import 'package:carlton/l10n/app_translations.dart';
import 'package:carlton/components/custom_logo_avatar.dart';
import 'package:carlton/routes/routes.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';

/// Tab titles, resolved per call so a language switch re-reads them —
/// a top-level `const` list would freeze whichever locale was active at
/// class-load time. Index 0 is the logo, which has no title.
List<String> get _tabTitles => [
  '',
  AppTranslations.navStays,
  AppTranslations.navBook,
  AppTranslations.navServices,
  AppTranslations.navAccount,
];

class CustomAppBar extends StatelessWidget implements PreferredSizeWidget {
  final int currentIndex;

  const CustomAppBar({required this.currentIndex, super.key});

  @override
  Widget build(BuildContext context) {
    final TextTheme textStyle = Get.textTheme;

    return AppBar(
      title: currentIndex == 0
          ? _LogoTitle(textStyle: textStyle)
          : Text(_tabTitles[currentIndex]),
      actions: [CustomLogoAvatar(onTap: () => Get.toNamed(Routes.aiConcierge))],
      iconTheme: const IconThemeData(color: AppColors.primary),
    );
  }

  @override
  Size get preferredSize => const Size.fromHeight(kToolbarHeight);
}

class _LogoTitle extends StatelessWidget {
  final TextTheme textStyle;

  const _LogoTitle({required this.textStyle});

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        Text(
          'CARLTON',
          style: textStyle.titleLarge?.copyWith(
            fontFamily: 'The Seasons',
            fontWeight: FontWeight.w400,
          ),
        ),
        Text(
          'HOTEL',
          style: textStyle.labelSmall?.copyWith(
            fontFamily: 'Cabinet Grotesk',
            fontWeight: FontWeight.w400,
          ),
        ),
      ],
    );
  }
}
