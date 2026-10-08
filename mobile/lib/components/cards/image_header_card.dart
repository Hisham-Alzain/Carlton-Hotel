import 'package:carlton/customWidgets/custom_containers.dart';
import 'package:carlton/customWidgets/custom_image.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:carlton/theme/theme.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';

/// The listing-card frame shared by Discover and Choose Room: a white 14px
/// card, a 150px image with an optional pill in its top corner, and a padded
/// body column under it.
class ImageHeaderCard extends StatelessWidget {
  final String image;
  final Widget? corner;
  final double cornerRadius;
  final List<Widget> children;
  final VoidCallback? onTap;
  final double? width;

  const ImageHeaderCard({
    required this.image,
    required this.children,
    this.corner,
    this.cornerRadius = 6,
    this.onTap,
    this.width,
    super.key,
  });

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      child: Card(
        clipBehavior: Clip.antiAlias,
        color: AppColors.white,
        shape: ContinuousRectangleBorder(
          borderRadius: BorderRadiusGeometry.circular(14),
        ),
        margin: const EdgeInsets.all(10),
        elevation: 1,
        child: SizedBox(
          width: width,
          child: Column(
            spacing: 10,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Stack(
                children: [
                  CustomImage(
                    source: image,
                    width: double.infinity,
                    height: 150,
                    fit: BoxFit.cover,
                  ),
                  if (corner != null)
                    PositionedDirectional(
                      top: 10,
                      end: 10,
                      child: PillContainer(
                        backgroundColor: AppColors.white88,
                        radius: cornerRadius,
                        child: corner!,
                      ),
                    ),
                ],
              ),
              Padding(
                padding: const EdgeInsets.all(10),
                child: Column(
                  spacing: 10,
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: children,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

/// A cream amenity tag ("King bed", "Free Wi-Fi") on a listing card.
class AmenityChip extends StatelessWidget {
  final String label;
  final Color backgroundColor;

  const AmenityChip({
    required this.label,
    this.backgroundColor = AppColors.cream,
    super.key,
  });

  @override
  Widget build(BuildContext context) {
    return PillContainer(
      backgroundColor: backgroundColor,
      radius: 4,
      child: Text(
        label,
        style: Get.textTheme.dmLabelSmall?.copyWith(color: AppColors.cocoaGold),
      ),
    );
  }
}
