import 'package:flutter/material.dart';

class CustomCircleIconButton extends StatelessWidget {
  final Widget icon;
  final VoidCallback? onTap;
  final double size;
  final Color? color;
  final Gradient? gradient;

  const CustomCircleIconButton({
    required this.icon,
    this.onTap,
    this.size = 39,
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
        child: icon,
      ),
    );
  }
}
