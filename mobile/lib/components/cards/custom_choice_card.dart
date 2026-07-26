import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:flutter_svg/flutter_svg.dart';
import 'package:get/get.dart';

class CustomChoiceCard extends StatelessWidget {
  final IconData? icon;
  final String? iconPath;
  final String title;
  final String subtitle;
  final VoidCallback onTap;

  const CustomChoiceCard({
    required this.title,
    required this.subtitle,
    required this.onTap,
    this.icon,
    this.iconPath,
    super.key,
  });

  @override
  Widget build(BuildContext context) {
    final TextTheme textStyle = Get.textTheme;

    return GestureDetector(
      onTap: onTap,
      behavior: HitTestBehavior.opaque,
      child: Container(
        padding: const EdgeInsets.all(20),
        decoration: BoxDecoration(
          color: AppColors.cream08,
          borderRadius: BorderRadius.circular(12),
          border: Border.all(color: AppColors.cream20, width: 1.5),
        ),
        child: Row(
          children: [
            Container(
              width: 45,
              height: 45,
              alignment: Alignment.center,

              margin: const EdgeInsetsDirectional.only(end: 16),
              decoration: BoxDecoration(
                color: AppColors.antiqueGold56,
                borderRadius: BorderRadius.circular(10),
              ),
              child: iconPath != null
                  ? SvgPicture.asset(iconPath!, width: 20, height: 20)
                  : Icon(icon, color: Colors.white, size: 20),
            ),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                spacing: 10,
                children: [
                  Text(
                    title,
                    style: textStyle.titleMedium?.copyWith(
                      fontWeight: FontWeight.w500,
                      color: Colors.white,
                    ),
                  ),
                  Text(
                    subtitle,
                    style: textStyle.labelLarge?.copyWith(
                      fontWeight: FontWeight.w500,
                      color: AppColors.cream60,
                    ),
                  ),
                ],
              ),
            ),
            const Icon(Icons.chevron_right, color: AppColors.cream60, size: 20),
          ],
        ),
      ),
    );
  }
}
