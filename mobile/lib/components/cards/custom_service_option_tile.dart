import 'package:carlton/customWidgets/custom_containers.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:flutter_svg/flutter_svg.dart';
import 'package:get/get.dart';

/// A tappable row for one service-catalog option inside a category detail
/// list: leading icon chip, title/description/ETA, trailing chevron.
class CustomServiceOptionTile extends StatelessWidget {
  /// Optional per-option SVG. The service catalog gives no per-item icon, so
  /// this is null there and a generic glyph is shown instead.
  final String? iconPath;
  final String title;
  final String description;
  final String etaLabel;
  final VoidCallback onTap;

  const CustomServiceOptionTile({
    required this.title,
    required this.description,
    required this.etaLabel,
    required this.onTap,
    this.iconPath,
    super.key,
  });

  @override
  Widget build(BuildContext context) {
    final TextTheme textStyle = Get.textTheme;

    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(12),
      child: Card(
        margin: const EdgeInsets.all(10),
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadiusGeometry.circular(12),
          side: BorderSide(color: AppColors.black06),
        ),
        color: AppColors.white39,
        elevation: 1,
        child: Padding(
          padding: const EdgeInsets.all(10),
          child: Row(
            spacing: 20,
            children: [
              iconPath != null
                  ? SvgPicture.asset(
                      height: 32,
                      width: 32,
                      iconPath!,
                      colorFilter: const ColorFilter.mode(
                        AppColors.antiqueGold,
                        BlendMode.srcIn,
                      ),
                    )
                  : const Icon(
                      Icons.room_service_outlined,
                      size: 32,
                      color: AppColors.antiqueGold,
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
                    if (etaLabel.isNotEmpty)
                      PillContainer(
                        backgroundColor: AppColors.cream,
                        radius: 3,
                        padding: const EdgeInsets.all(10),
                        child: Text(
                          etaLabel,
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
