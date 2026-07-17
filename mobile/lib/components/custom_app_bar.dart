// components/custom_app_bar.dart
import 'package:carlton/components/custom_logo_avatar.dart';
import 'package:carlton/routes/routes.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';

/// Titles for indices 1-4. Index 0 (Home) uses [_LogoTitle] instead.
const _tabTitles = [
  '', // unused — Home renders _LogoTitle
  'STAYS',
  'BOOK',
  'SERVICES',
  'ACCOUNT',
];

class CustomAppBar extends StatelessWidget implements PreferredSizeWidget {
  final int currentIndex;

  const CustomAppBar({required this.currentIndex, super.key});

  @override
  Widget build(BuildContext context) {
    final textTheme = Get.textTheme;

    return AppBar(
      title: currentIndex == 0
          ? _LogoTitle(textTheme: textTheme)
          : Text(_tabTitles[currentIndex]),
      actions: [CustomLogoAvatar(onTap: () => Get.toNamed(Routes.aiConcierge))],
      iconTheme: const IconThemeData(color: AppColors.primary),
    );
  }

  @override
  Size get preferredSize => const Size.fromHeight(kToolbarHeight);
}

/// The two-line "CARLTON / HOTEL" wordmark shown only on the Home tab.
class _LogoTitle extends StatelessWidget {
  final TextTheme textTheme;

  const _LogoTitle({required this.textTheme});

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        Text(
          'CARLTON',
          style: textTheme.titleLarge?.copyWith(
            fontFamily: 'The Seasons',
            fontWeight: FontWeight.w400,
          ),
        ),
        Text(
          'HOTEL',
          style: textTheme.labelSmall?.copyWith(
            fontFamily: 'Cabinet Grotesk',
            fontWeight: FontWeight.w400,
          ),
        ),
      ],
    );
  }
}
