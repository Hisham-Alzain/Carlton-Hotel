import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';

enum _ChipKind { status, action }

class CustomChip extends StatelessWidget {
  final String label;
  final Color backgroundColor;
  final Color textColor;
  final Color? borderColor;
  final VoidCallback? onTap;
  final _ChipKind _kind;

  const CustomChip.status({
    required this.label,
    required this.textColor,
    required this.backgroundColor,
    super.key,
  }) : _kind = _ChipKind.status,
       borderColor = null,
       onTap = null;

  const CustomChip.action({
    required this.label,
    required this.onTap,
    this.backgroundColor = AppColors.white10,
    this.borderColor = AppColors.sandBorder,
    this.textColor = AppColors.textTealDark,
    super.key,
  }) : _kind = _ChipKind.action;

  @override
  Widget build(BuildContext context) {
    if (_kind == _ChipKind.status) {
      return Container(
        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
        decoration: BoxDecoration(
          color: backgroundColor,
          borderRadius: BorderRadius.circular(4),
        ),
        child: Text(
          label,
          style: TextStyle(
            color: textColor,
            fontSize: 9,
            fontWeight: FontWeight.w600,
            letterSpacing: 0.72,
          ),
        ),
      );
    }
    return GestureDetector(
      onTap: onTap,
      behavior: HitTestBehavior.opaque,
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 15, vertical: 7),
        decoration: BoxDecoration(
          color: backgroundColor,
          borderRadius: BorderRadius.circular(20),
          border: Border.all(color: borderColor!, width: 1.2),
        ),
        child: Text(label, style: TextStyle(fontSize: 12, color: textColor)),
      ),
    );
  }
}
