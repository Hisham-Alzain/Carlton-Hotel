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

    // Scrollable because this tab lives in a TabBarView inside an Expanded —
    // the height is bounded, so a bare Column would overflow instead of scroll.
    return SingleChildScrollView(
      padding: const EdgeInsets.all(10),
      child: Column(
        spacing: 10,
        // ListView stretched its children to full width for free; a Column
        // centres them by default, which would shrink the info card, the
        // gallery and the CTA to their content width.
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text(
            'About',
            style: textStyle.titleSmall?.copyWith(
              fontWeight: FontWeight.w700,
              color: AppColors.inkBlack,
            ),
          ),
          Text(
            c.about.isNotEmpty ? c.about : 'Details coming soon.',
            style: textStyle.labelMedium?.copyWith(
              fontFamily: 'DM Sans',
              height: 1.5,
              color: AppColors.dimGrey,
            ),
          ),

          Container(
            padding: const EdgeInsets.all(10),
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
            Text(
              'Gallery',
              style: textStyle.titleSmall?.copyWith(
                fontWeight: FontWeight.w700,
                color: AppColors.inkBlack,
              ),
            ),
            CustomGalleryGrid(images: c.gallery),
          ],
          CustomFilledButton(
            width: double.infinity,
            height: 50,
            backgroundColor: AppColors.primary,
            // This tab renders inside the detail view's TabBarView, so the
            // DefaultTabController is above us in the tree.
            onPressed: () => DefaultTabController.of(context).animateTo(2),
            child: const Text('Reserve a table'),
          ),
        ],
      ),
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
      spacing: 10,
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Container(
          width: 30,
          height: 30,
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
            children: [
              Text(
                label.toUpperCase(),
                style: textStyle.labelSmall?.copyWith(
                  fontFamily: 'DM Sans',
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
