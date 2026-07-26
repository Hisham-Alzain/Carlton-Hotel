import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:flutter_svg/flutter_svg.dart';

class CustomLogoAvatar extends StatelessWidget {
  final VoidCallback? onTap;

  const CustomLogoAvatar({this.onTap, super.key});

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,

      child: Container(
        width: 60,
        height: 60,
        alignment: Alignment.center,
        decoration: BoxDecoration(
          color: AppColors.primary06,
          shape: BoxShape.circle,
          border: Border.all(color: Colors.white, width: 1),
        ),
        child: SvgPicture.asset(
          'assets/icons/badge_logo.svg',
          width: 25,
          height: 25,
        ),
      ),
    );
  }
}
