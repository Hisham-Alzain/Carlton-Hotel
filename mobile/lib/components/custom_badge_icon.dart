import 'package:flutter/material.dart';
import 'package:flutter_svg/svg.dart';
import 'package:get/get.dart';

class CustomBadgeIcon extends StatelessWidget {
  final String? path;
  final IconData? icon;
  final RxInt count;
  final void Function()? onTap;

  const CustomBadgeIcon({
    super.key,
    this.path,
    this.icon,
    required this.count,
    this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: onTap,
      child: Stack(
        clipBehavior: Clip.none,
        children: [
          if (icon == null && path != null) SvgPicture.asset(path.toString()),
          if (icon != null && path == null) Icon(icon),
          if (count.value > 0)
            Positioned(
              right: -10,
              top: -10,
              child: Container(
                padding: EdgeInsets.all(4),
                decoration: BoxDecoration(
                  color: Colors.red,
                  shape: BoxShape.circle,
                ),
                child: Obx(
                  () => Text(
                    count.value.toString(),
                    style: Get.textTheme.labelSmall,
                  ),
                ),
              ),
            ),
        ],
      ),
    );
  }
}
