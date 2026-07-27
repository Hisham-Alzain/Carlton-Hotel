import 'package:carlton/components/custom_logo_avatar.dart';
import 'package:carlton/routes/routes.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';

const _tabTitles = ['', 'My Stays', 'BOOK', 'SERVICES', 'ACCOUNT'];

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
