import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:flutter_svg/svg.dart';
import 'package:get/get.dart';

/// A gold-tinted icon + hours/location line for the hero's floating meta card.
class RestaurantMetaLine extends StatelessWidget {
  final String icon;
  final String text;

  const RestaurantMetaLine({required this.icon, required this.text, super.key});

  @override
  Widget build(BuildContext context) {
    return Row(
      mainAxisSize: MainAxisSize.min,
      spacing: 5,
      children: [
        SvgPicture.asset(
          icon,
          width: 15,
          height: 15,
          colorFilter: const ColorFilter.mode(
            AppColors.antiqueGold,
            BlendMode.srcIn,
          ),
        ),
        Flexible(
          child: Text(
            text,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: Get.textTheme.labelMedium?.copyWith(
              fontFamily: 'DM Sans',
              color: AppColors.slateGrey,
            ),
          ),
        ),
      ],
    );
  }
}
