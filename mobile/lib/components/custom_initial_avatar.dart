import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';

/// A circular avatar showing a person's initial — used for the Account profile
/// header and the chat agent header. Distinct from [CustomLogoAvatar], which
/// renders the app logo, not a user initial.
class CustomInitialAvatar extends StatelessWidget {
  final String initial;
  final double size;
  final Color backgroundColor;
  final Color foregroundColor;

  const CustomInitialAvatar({
    required this.initial,
    this.size = 60,
    this.backgroundColor = AppColors.primary,
    this.foregroundColor = AppColors.white,
    super.key,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      width: size,
      height: size,
      alignment: Alignment.center,
      decoration: BoxDecoration(color: backgroundColor, shape: BoxShape.circle),
      child: Text(
        initial.isEmpty ? '' : initial.characters.first.toUpperCase(),
        style: Get.textTheme.titleMedium?.copyWith(
          fontWeight: FontWeight.w700,
          color: foregroundColor,
          fontSize: size * 0.4,
        ),
      ),
    );
  }
}
