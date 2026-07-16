import 'package:carlton/customWidgets/custom_image.dart';
import 'package:carlton/models/card_meta.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:flutter_svg/flutter_svg.dart';
import 'package:get/get.dart';

class CustomListingCard extends StatelessWidget {
  final String imagePath;
  final String title;
  final String subtitle;
  final List<CardMeta> meta;
  final bool metaInRow;
  final String? priceAmount;
  final VoidCallback? onTap;
  final double? width;

  const CustomListingCard({
    required this.imagePath,
    required this.title,
    required this.subtitle,
    required this.meta,
    this.metaInRow = false,
    this.priceAmount,
    this.onTap,
    this.width = 280,
    super.key,
  });

  @override
  Widget build(BuildContext context) {
    final TextTheme textStyle = Get.textTheme;
    final items = meta
        .map(
          (m) => Row(
            mainAxisSize: MainAxisSize.min,
            spacing: 10,
            children: [
              SvgPicture.asset(
                m.iconPath,
                width: 12,
                height: 12,
                colorFilter: const ColorFilter.mode(
                  AppColors.textMuted,
                  BlendMode.srcIn,
                ),
              ),
              Text(
                m.text,
                style: textStyle.labelMedium?.copyWith(
                  color: AppColors.textMuted,
                  fontWeight: FontWeight.w300,
                ),
              ),
            ],
          ),
        )
        .toList();
    return InkWell(
      onTap: onTap,
      child: Card(
        margin: const EdgeInsets.all(10),
        child: Column(
          spacing: 10,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            CustomImage(
              path: imagePath,
              width: width,
              height: 200,
              fit: BoxFit.cover,
            ),
            Padding(
              padding: const EdgeInsets.all(10),
              child: Column(
                spacing: 10,
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    title,
                    style: textStyle.titleMedium?.copyWith(
                      color: AppColors.primary,
                      fontWeight: FontWeight.w500,
                    ),
                  ),
                  Text(
                    subtitle,
                    style: textStyle.labelSmall?.copyWith(
                      color: AppColors.textMuted,
                      fontWeight: FontWeight.w500,
                    ),
                  ),
                  metaInRow
                      ? Wrap(spacing: 10, runSpacing: 10, children: items)
                      : Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          mainAxisSize: MainAxisSize.min,
                          spacing: 10,
                          children: items,
                        ),
                  if (priceAmount != null)
                    Text.rich(
                      TextSpan(
                        style: textStyle.labelMedium?.copyWith(
                          color: AppColors.textMuted,
                          fontWeight: FontWeight.w500,
                        ),
                        children: [
                          const TextSpan(text: 'From '),
                          TextSpan(
                            text: priceAmount,
                            style: textStyle.titleLarge?.copyWith(
                              color: AppColors.primary,
                              fontWeight: FontWeight.w400,
                            ),
                          ),
                          const TextSpan(text: ' /night'),
                        ],
                      ),
                    ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}
