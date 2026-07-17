import 'package:flutter/material.dart';
import 'package:flutter_svg/svg.dart';

class CustomCircleIconButton extends StatelessWidget {
  final IconData? icon;
  final String? iconPath;
  final VoidCallback? onTap;
  final double size;
  final Color? color;
  final Gradient? gradient;

  const CustomCircleIconButton({
    this.icon,
    this.iconPath,
    this.onTap,
    this.size = 40,
    this.color = Colors.white,
    this.gradient,
    super.key,
  });

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: onTap,
      child: Container(
        width: size,
        height: size,
        alignment: Alignment.center,
        decoration: BoxDecoration(
          shape: BoxShape.circle,
          color: gradient == null ? color : null,
          gradient: gradient,
          border: Border.all(color: Colors.white),
          boxShadow: const [
            BoxShadow(
              color: Color(0x40888888),
              blurRadius: 2,
              offset: Offset(0, 1),
            ),
          ],
        ),
        child: _buildPrefixIcon(),
      ),
    );
  }

  Widget? _buildPrefixIcon() {
    if (icon != null) {
      return Icon(
        icon,
        //  color: prefixIconColor ?? AppColors.primaryColor
      );
    } else if (iconPath != null) {
      return Padding(
        padding: const EdgeInsets.all(10),
        child: iconPath != null
            ? SvgPicture.asset(
                iconPath.toString(),
                // colorFilter: ColorFilter.mode(
                //   prefixIconColor ?? AppColors.primaryColor,
                //   BlendMode.srcIn,
                // ),
                height: 25,
              )
            : null,
      );
    }
    return null;
  }
}
