import 'package:flutter/material.dart';
import 'package:flutter_svg/svg.dart';

class CustomCircleIconButton extends StatelessWidget {
  final IconData? icon;
  final String? iconPath;
  final VoidCallback? onTap;
  final double size;
  final Color? color;
  final Gradient? gradient;

  /// White ring around the circle. Default `true` for the elevated white
  /// button; pass `false` for a flat variant (e.g. the Figma sheet close).
  final bool bordered;

  /// Soft drop shadow under the circle. Default `true`; pass `false` for flat.
  final bool shadow;

  /// SVG glyph height. Defaults to 25 (the original elevated button); set it
  /// smaller for a compact button.
  final double iconSize;

  /// Padding around the glyph inside the circle. Defaults to 10.
  final double iconPadding;

  const CustomCircleIconButton({
    this.icon,
    this.iconPath,
    this.onTap,
    this.size = 40,
    this.color = Colors.white,
    this.gradient,
    this.bordered = true,
    this.shadow = true,
    this.iconSize = 25,
    this.iconPadding = 10,
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
          border: bordered ? Border.all(color: Colors.white) : null,
          boxShadow: shadow
              ? const [
                  BoxShadow(
                    color: Color(0x40888888),
                    blurRadius: 2,
                    offset: Offset(0, 1),
                  ),
                ]
              : null,
        ),
        child: _buildPrefixIcon(),
      ),
    );
  }

  Widget? _buildPrefixIcon() {
    if (icon != null) {
      return Icon(icon);
    } else if (iconPath != null) {
      return Padding(
        padding: EdgeInsets.all(iconPadding),
        child: SvgPicture.asset(iconPath!, height: iconSize),
      );
    }
    return null;
  }
}
