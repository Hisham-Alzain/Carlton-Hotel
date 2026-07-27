import 'package:carlton/customWidgets/custom_containers.dart';
import 'package:carlton/models/menu_item.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';

/// A single dish on the restaurant menu tab (Figma): food image on the left,
/// then name · description, and a price · dietary-tag row beneath.
class CustomMenuItemTile extends StatelessWidget {
  final MenuItem item;

  const CustomMenuItemTile({required this.item, super.key});

  @override
  Widget build(BuildContext context) {
    final TextTheme textStyle = Get.textTheme;

    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: AppColors.white,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: AppColors.black06),
      ),
      child: Row(
        spacing: 14,
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          ClipRRect(
            borderRadius: BorderRadius.circular(10),
            child: Image.asset(
              item.imagePath,
              width: 70,
              height: 85,
              fit: BoxFit.cover,
            ),
          ),
          Expanded(
            child: Column(
              spacing: 6,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  item.name,
                  style: textStyle.labelLarge?.copyWith(
                    fontWeight: FontWeight.w600,
                    color: AppColors.inkBlack,
                  ),
                ),
                Text(
                  item.description,
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: textStyle.labelMedium?.copyWith(
                    fontFamily: 'DM Sans',
                    color: AppColors.dimGrey,
                  ),
                ),
                Row(
                  spacing: 10,
                  children: [
                    Text(
                      item.price,
                      style: textStyle.labelLarge?.copyWith(
                        fontWeight: FontWeight.w700,
                        color: AppColors.primary,
                      ),
                    ),
                    if (item.tag != null)
                      PillContainer(
                        backgroundColor: AppColors.successGreen07,
                        radius: 6,
                        padding: const EdgeInsets.symmetric(
                          horizontal: 8,
                          vertical: 3,
                        ),
                        child: Text(
                          item.tag!,
                          style: textStyle.labelSmall?.copyWith(
                            fontFamily: 'DM Sans',
                            fontWeight: FontWeight.w600,
                            color: AppColors.forestGreen,
                          ),
                        ),
                      ),
                  ],
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
