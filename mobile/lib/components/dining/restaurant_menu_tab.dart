import 'package:carlton/components/dining/custom_menu_item_tile.dart';
import 'package:carlton/controllers/dining/restaurant_controller.dart';
import 'package:carlton/customWidgets/custom_filled_button.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:flutter_svg/flutter_svg.dart';
import 'package:get/get.dart';

/// Restaurant "menu" tab: category chips + dish list + Download Full Menu.
/// Categories and dishes come from the public content API (see
/// [RestaurantController]); a venue with no menu shows an empty state.
class RestaurantMenuTab extends StatelessWidget {
  final RestaurantController c;

  const RestaurantMenuTab({required this.c, super.key});

  @override
  Widget build(BuildContext context) {
    if (c.menuLoading) {
      return const Center(child: CircularProgressIndicator());
    }

    final categories = c.menuCategories;
    final items = c.visibleMenuItems;
    final lastCategory = categories.length - 1;
    final lastItem = items.length - 1;

    return Column(
      children: [
        if (categories.isNotEmpty)
          SizedBox(
            height: 44,
            child: ListView.builder(
              scrollDirection: Axis.horizontal,
              padding: const EdgeInsets.symmetric(horizontal: 16),
              itemCount: categories.length,
              itemBuilder: (context, i) {
                final selected = c.categoryIndex == i;
                return Padding(
                  padding: EdgeInsets.only(right: i == lastCategory ? 0 : 8),
                  child: ChoiceChip(
                    label: Text(categories[i].name.value),
                    selected: selected,
                    onSelected: (_) => c.selectCategory(i),
                    showCheckmark: false,
                    backgroundColor: AppColors.white,
                    selectedColor: AppColors.primary,
                    labelStyle: Get.textTheme.labelMedium?.copyWith(
                      fontWeight: FontWeight.w600,
                      color: selected ? AppColors.white : AppColors.inkBlack,
                    ),
                    side: BorderSide(
                      color: selected ? AppColors.primary : AppColors.black10,
                    ),
                  ),
                );
              },
            ),
          ),
        Expanded(
          child: items.isEmpty
              ? Center(
                  child: Text(
                    'Menu coming soon',
                    style: Get.textTheme.bodyMedium?.copyWith(
                      color: AppColors.dimGrey,
                    ),
                  ),
                )
              : ListView.builder(
                  padding: const EdgeInsets.all(16),
                  itemCount: items.length,
                  itemBuilder: (context, i) => Padding(
                    padding: EdgeInsets.only(bottom: i == lastItem ? 0 : 12),
                    child: CustomMenuItemTile(item: items[i]),
                  ),
                ),
        ),
        Padding(
          padding: const EdgeInsets.fromLTRB(16, 4, 16, 16),
          child: CustomFilledButton(
            width: double.infinity,
            height: 48,
            backgroundColor: AppColors.whisperGrey,
            foregroundColor: AppColors.inkBlack,
            onPressed: c.downloadMenu,
            child: Row(
              mainAxisAlignment: MainAxisAlignment.center,
              spacing: 6,
              children: [
                SvgPicture.asset(
                  'assets/icons/download.svg',
                  width: 18,
                  height: 18,
                  colorFilter: const ColorFilter.mode(
                    AppColors.inkBlack,
                    BlendMode.srcIn,
                  ),
                ),
                Text(
                  'Download Full Menu',
                  style: Get.textTheme.labelLarge?.copyWith(
                    fontFamily: 'DM Sans',
                    fontWeight: FontWeight.w500,
                  ),
                ),
              ],
            ),
          ),
        ),
      ],
    );
  }
}
