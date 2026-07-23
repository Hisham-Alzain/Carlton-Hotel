import 'package:carlton/customWidgets/custom_image.dart';
import 'package:carlton/models/service_item.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';

class CustomServiceCard extends StatelessWidget {
  final ServiceItem item;

  const CustomServiceCard({super.key, required this.item});

  @override
  Widget build(BuildContext context) {
    final TextTheme textStyle = Get.textTheme;

    return Card(
      color: AppColors.primary06,
      elevation: 1,
      shadowColor: AppColors.white25,
      clipBehavior: Clip.antiAlias,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(12),
        side: const BorderSide(color: Colors.white, width: 1.5),
      ),
      child: Stack(
        children: [
          PositionedDirectional(
            end: 0,
            bottom: 0,
            child: CustomImage(
              path: item.imagePath,
              width: item.imageWidth,
              height: item.imageHeight,
              fit: BoxFit.contain,
            ),
          ),
          Padding(
            padding: const EdgeInsets.all(10),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisSize: MainAxisSize.min,
              spacing: 2,
              children: [
                Text(
                  item.title,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: textStyle.labelLarge?.copyWith(
                    color: AppColors.primary,
                    fontWeight: FontWeight.w400,
                  ),
                ),
                Text(
                  item.subtitle,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: textStyle.labelMedium?.copyWith(
                    color: AppColors.stoneTaupe,
                    fontWeight: FontWeight.w500,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
