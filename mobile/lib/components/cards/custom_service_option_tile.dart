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

    return Material(
      color: Colors.white,
      borderRadius: BorderRadius.circular(14),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(14),
        child: Container(
          padding: const EdgeInsets.all(14),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(14),
            border: Border.all(color: AppColors.black06),
          ),
          child: Row(
            spacing: 12,
            crossAxisAlignment: CrossAxisAlignment.center,
            children: [
              PillContainer(
                width: 44,
                height: 44,
                backgroundColor: AppColors.pearlCream,
                radius: 10,
                padding: const EdgeInsets.all(8),
                child: SvgPicture.asset(
                  iconPath,
                  colorFilter: const ColorFilter.mode(
                    AppColors.antiqueGold,
                    BlendMode.srcIn,
                  ),
                ),
              ),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  spacing: 4,
                  children: [
                    Text(
                      title,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: textStyle.labelLarge?.copyWith(
                        fontSize: 15,
                        fontWeight: FontWeight.w600,
                        color: AppColors.inkBlack,
                      ),
                    ),
                    Text(
                      description,
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: textStyle.labelMedium?.copyWith(
                        fontSize: 13,
                        color: AppColors.dimGrey,
                      ),
                    ),
                    PillContainer(
                      backgroundColor: AppColors.cream,
                      radius: 6,
                      padding: const EdgeInsets.symmetric(
                        horizontal: 8,
                        vertical: 3,
                      ),
                      child: Text(
                        eta,
                        style: textStyle.labelSmall?.copyWith(
                          fontSize: 12,
                          color: AppColors.walnutGold,
                        ),
                      ),
                    ),
                  ],
                ),
              ),
              SvgPicture.asset(
                'assets/icons/chevron_right.svg',
                height: 16,
                width: 16,
                colorFilter: const ColorFilter.mode(
                  AppColors.dimGrey,
                  BlendMode.srcIn,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
