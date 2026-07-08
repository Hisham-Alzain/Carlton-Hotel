import 'package:flutter/material.dart';
import 'package:flutter_svg/flutter_svg.dart';

class RowTextComponent extends StatelessWidget {
  final String text;
  final String? title;
  final IconData? icon;
  final double? iconSize;
  final Color? iconColor;
  final String? path;
  final TextStyle? textStyle;
  final TextStyle? titleStyle;
  final MainAxisAlignment? mainAxisAlignment;
  final double? spacing;

  /// 👇 add this
  final bool expandText;

  const RowTextComponent({
    required this.text,
    this.title,
    this.icon,
    this.iconSize,
    this.iconColor,
    this.path,
    this.textStyle,
    this.titleStyle,
    this.mainAxisAlignment,
    this.spacing,
    this.expandText = false,
    super.key,
  });

  @override
  Widget build(BuildContext context) {
    return Row(
      spacing: 10,
      mainAxisSize: MainAxisSize.min,
      mainAxisAlignment: mainAxisAlignment ?? MainAxisAlignment.start,
      children: [
        if (icon != null)
          Icon(icon, size: iconSize)
        else if (path != null)
          SvgPicture.asset(
            path!,
            colorFilter:
                iconColor != null
                    ? ColorFilter.mode(iconColor!, BlendMode.srcIn)
                    : null,
            width: 20,
          ),

        if (title != null) ...[Text(title!, style: titleStyle)],

        expandText
            ? Expanded(child: Text(text, style: textStyle))
            : Text(text, style: textStyle),
      ],
    );
  }
}

class InvertedRowTextComponent extends StatelessWidget {
  final String text;
  final String? title;
  final IconData? icon;
  final double? iconSize;
  final Color? iconColor;
  final String? path;
  final TextStyle? textStyle;
  final TextStyle? titleStyle;
  final MainAxisAlignment? mainAxisAlignment;
  final double? spacing;

  /// 👇 add this
  final bool expandText;

  const InvertedRowTextComponent({
    required this.text,
    this.title,
    this.icon,
    this.iconSize,
    this.iconColor,
    this.path,
    this.textStyle,
    this.titleStyle,
    this.mainAxisAlignment,
    this.spacing,
    this.expandText = false,
    super.key,
  });

  @override
  Widget build(BuildContext context) {
    return Row(
      spacing: 10,
      mainAxisSize: MainAxisSize.min,
      mainAxisAlignment: mainAxisAlignment ?? MainAxisAlignment.start,
      children: [
        if (title != null) ...[Text(title!, style: titleStyle)],

        expandText
            ? Expanded(child: Text(text, style: textStyle))
            : Text(text, style: textStyle),
        if (icon != null)
          Icon(icon, size: iconSize)
        else if (path != null)
          SvgPicture.asset(
            path!,
            colorFilter:
                iconColor != null
                    ? ColorFilter.mode(iconColor!, BlendMode.srcIn)
                    : null,
            width: 20,
          ),
      ],
    );
  }
}

class ColumnTextComponent extends StatelessWidget {
  final String text;
  final String? title;
  final IconData? icon;
  final double? iconSize;
  final String? path;
  final TextStyle? textStyle;
  final TextStyle? titleStyle;
  final double? spacing;

  const ColumnTextComponent({
    required this.text,
    this.title,
    this.icon,
    this.iconSize,
    this.path,
    this.textStyle,
    this.titleStyle,
    this.spacing,
    super.key,
  });

  @override
  Widget build(BuildContext context) {
    return Column(
      spacing: spacing ?? 0.0,
      children: [
        Row(
          children: [
            if (icon != null)
              Icon(icon, size: iconSize)
            else if (path != null)
              SvgPicture.asset(
                path.toString(),
                // colorFilter: ColorFilter.mode(
                //   AppColors.primaryColor,
                //   BlendMode.srcIn,
                // ),
              ),
            if (title != null) Text(title.toString(), style: titleStyle),
          ],
        ),

        Text(text, style: textStyle),
      ],
    );
  }
}
