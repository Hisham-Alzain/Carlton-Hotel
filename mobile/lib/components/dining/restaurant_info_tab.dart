import 'package:carlton/components/dining/custom_gallery_grid.dart';
import 'package:carlton/controllers/dining/restaurant_controller.dart';
import 'package:carlton/customWidgets/custom_filled_button.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:flutter_svg/flutter_svg.dart';
import 'package:get/get.dart';

/// Restaurant "info" tab: About, a card of Hours/Location/Cuisine/Rating rows,
/// the gallery grid, and a Reserve CTA.
class RestaurantInfoTab extends StatelessWidget {
  final RestaurantController c;

  const RestaurantInfoTab({required this.c, super.key});

  @override
  Widget build(BuildContext context) {
    final TextTheme textStyle = Get.textTheme;

    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        Text(
          'About',
          style: textStyle.titleSmall?.copyWith(
            fontWeight: FontWeight.w700,
            color: AppColors.inkBlack,
          ),
        ),
        const SizedBox(height: 8),
        Text(
          c.about.isNotEmpty ? c.about : 'Details coming soon.',
          style: textStyle.labelMedium?.copyWith(
            fontFamily: 'DM Sans',
            height: 1.5,
            color: AppColors.dimGrey,
          ),
        ),
        const SizedBox(height: 16),
        Container(
          padding: const EdgeInsets.all(16),
          decoration: BoxDecoration(
            color: AppColors.white,
            borderRadius: BorderRadius.circular(14),
            border: Border.all(color: AppColors.black06),
          ),
          child: Column(
            spacing: 14,
            children: [
              _InfoRow(
                icon: 'assets/icons/clock.svg',
                label: 'Hours',
                value: c.restaurant.hours,
              ),
              _InfoRow(
                icon: 'assets/icons/location.svg',
                label: 'Location',
                value: c.restaurant.location,
              ),
              _InfoRow(
                icon: 'assets/icons/cuisine.svg',
                label: 'Cuisine',
                value: c.restaurant.cuisine,
              ),
              _InfoRow(
                icon: 'assets/icons/rating.svg',
                label: 'Rating',
                value:
                    '${c.restaurant.rating} / 5.0 · '
                    '${c.restaurant.reviews} reviews',
              ),
            ],
          ),
        ),
        if (c.gallery.isNotEmpty) ...[
          const SizedBox(height: 20),
          Text(
            'Gallery',
            style: textStyle.titleSmall?.copyWith(
              fontWeight: FontWeight.w700,
              color: AppColors.inkBlack,
            ),
          ),
          const SizedBox(height: 10),
          CustomGalleryGrid(images: c.gallery),
        ],
        const SizedBox(height: 20),
        CustomFilledButton(
          width: double.infinity,
          height: 52,
          backgroundColor: AppColors.primary,
          onPressed: c.goToReserveTab,
          child: const Text('Reserve a table'),
        ),
      ],
    );
  }
}

class _InfoRow extends StatelessWidget {
  final String icon;
  final String label;
  final String value;

  const _InfoRow({
    required this.icon,
    required this.label,
    required this.value,
  });

  @override
  Widget build(BuildContext context) {
    final TextTheme textStyle = Get.textTheme;

    return Row(
      spacing: 12,
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Container(
          width: 32,
          height: 32,
          alignment: Alignment.center,
          decoration: BoxDecoration(
            color: AppColors.pearlCream,
            borderRadius: BorderRadius.circular(8),
          ),
          child: SvgPicture.asset(
            icon,
            width: 15,
            height: 15,
            colorFilter: const ColorFilter.mode(
              AppColors.antiqueGold,
              BlendMode.srcIn,
            ),
          ),
        ),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            spacing: 2,
            children: [
              Text(
                label.toUpperCase(),
                style: textStyle.labelSmall?.copyWith(
                  fontFamily: 'DM Sans',
                  fontSize: 10,
                  letterSpacing: 0.5,
                  color: AppColors.walnutGold,
                ),
              ),
              Text(
                value,
                style: textStyle.labelMedium?.copyWith(
                  fontWeight: FontWeight.w500,
                  color: AppColors.inkBlack,
                ),
              ),
            ],
          ),
        ),
      ],
    );
  }
}
