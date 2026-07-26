import 'package:carlton/customWidgets/custom_containers.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:flutter_svg/flutter_svg.dart';
import 'package:get/get.dart';

/// A tappable row for one [ServiceOption] inside a category detail list:
/// leading icon chip, title/description/ETA, trailing chevron.
class CustomServiceOptionTile extends StatelessWidget {
  final String iconPath;
  final String title;
  final String description;
  final String eta;
  final VoidCallback onTap;

  const CustomServiceOptionTile({
    required this.iconPath,
    required this.title,
    required this.description,
    required this.eta,
    required this.onTap,
    super.key,
  });

  @override
  Widget build(BuildContext context) {
    final textStyle = Get.textTheme;

    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(14),
      child: Card(
        margin: const EdgeInsets.all(10),
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadiusGeometry.circular(14),
          side: BorderSide(color: AppColors.black06),
        ),
        color: AppColors.white,
        elevation: 1,
        child: Padding(
          padding: const EdgeInsets.all(10),
          child: Row(
            spacing: 20,
            children: [
              SvgPicture.asset(
                height: 30,
                width: 30,
                iconPath,
                colorFilter: const ColorFilter.mode(
                  AppColors.antiqueGold,
                  BlendMode.srcIn,
                ),
              ),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  spacing: 10,
                  children: [
                    Text(
                      title,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: textStyle.labelLarge?.copyWith(
                        fontWeight: FontWeight.w600,
                        color: AppColors.inkBlack,
                      ),
                    ),
                    Text(
                      description,
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: textStyle.labelMedium?.copyWith(
                        color: AppColors.dimGrey,
                      ),
                    ),
                    PillContainer(
                      backgroundColor: AppColors.cream,
                      radius: 3,
                      padding: const EdgeInsets.all(10),
                      child: Text(
                        eta,
                        style: textStyle.labelSmall?.copyWith(
                          color: AppColors.walnutGold,
                        ),
                      ),
                    ),
                  ],
                ),
              ),
              const Icon(Icons.chevron_right, color: AppColors.mochaBrown),
            ],
          ),
        ),
      ),
    );
  }
}
