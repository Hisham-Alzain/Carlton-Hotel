import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:flutter_svg/flutter_svg.dart';

class CustomLogoAvatar extends StatelessWidget {
  final VoidCallback? onTap;
  final VoidCallback? onLongPress;

  final bool bordered;
  final double logoSize;

  const CustomLogoAvatar({
    this.onTap,
    this.onLongPress,
    this.bordered = false,
    this.logoSize = 24,
    super.key,
  });

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: onTap,
      onLongPress: onLongPress,
      behavior: HitTestBehavior.opaque,
      child: Container(
        width: 48,
        height: 48,
        alignment: Alignment.center,
        decoration: BoxDecoration(
          color: AppColors.primaryTileBg,
          shape: BoxShape.circle,
          border: bordered ? Border.all(color: Colors.white) : null,
        ),
        child: SvgPicture.asset(
          'assets/icons/logo.svg',
          width: logoSize,
          height: logoSize,
        ),
      ),
    );
  }
}
