import 'package:flutter/material.dart';
import 'package:get/get.dart';

class EditContainer extends StatelessWidget {
  final double? height;
  final double? width;
  final String? title;
  final TextStyle? titleTextStyle;
  final IconData? icon;
  final void Function()? onPressed;
  final Color? iconColor;
  final Widget child;

  const EditContainer({
    this.height,
    this.width,
    this.title,
    this.titleTextStyle,
    this.icon,
    this.onPressed,
    this.iconColor,
    required this.child,
    super.key,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      height: height,
      width: width,
      decoration: BoxDecoration(),
      child: Column(
        spacing: 10,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              if (title != null) Text('$title', style: titleTextStyle),
              if (icon != null)
                IconButton(
                  onPressed: onPressed,
                  icon: Icon(icon, color: iconColor),
                ),
            ],
          ),
          child,
        ],
      ),
    );
  }
}

class ButtonsContainer extends StatelessWidget {
  final double? height;
  final double? width;
  final String? title;
  final TextStyle? titleTextStyle;
  final Widget buttons;
  final Widget child;

  const ButtonsContainer({
    this.height,
    this.width,
    this.title,
    this.titleTextStyle,
    required this.buttons,
    required this.child,
    super.key,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      height: height,
      width: width,
      decoration: BoxDecoration(),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.center,
        spacing: 10,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              if (title != null) Text('$title', style: titleTextStyle),
              buttons,
            ],
          ),
          child,
        ],
      ),
    );
  }
}

class NotesContainer extends StatelessWidget {
  final String? title;
  final TextStyle? titleTextStyle;
  final IconData? icon;
  final Widget child;

  const NotesContainer({
    this.title,
    this.titleTextStyle,
    this.icon,
    required this.child,
    super.key,
  });

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        Row(
          children: [
            if (icon != null) Icon(icon),
            if (title != null) Text('$title', style: titleTextStyle),
          ],
        ),
        Container(
          width: Get.width,
          // decoration:
          padding: EdgeInsets.all(10),
          child: child,
        ),
      ],
    );
  }
}
