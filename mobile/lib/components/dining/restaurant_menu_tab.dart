import 'package:carlton/customWidgets/custom_indicators.dart';
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
    return Obx(() {
      if (c.menuLoading.value) {
        return const Center(
          child: SpinningIconIndicator(size: 40, color: AppColors.primary),
        );
      }

      final categories = c.menuCategories;
      final items = c.visibleMenuItems;

      return Column(
        children: [
          if (categories.isNotEmpty)
            SizedBox(
              height: 50,
              child: ListView.builder(
                scrollDirection: Axis.horizontal,
                itemCount: categories.length,
                itemBuilder: (context, i) {
                  final selected = c.categoryIndex.value == i;
                  return Padding(
                    padding: const EdgeInsets.all(10),
                    child: ChoiceChip(
                      label: Text(categories[i].name.value),
                      selected: selected,
                      onSelected: (_) => c.selectCategory(i),
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
                    padding: const EdgeInsets.all(10),
                    itemCount: items.length,
                    itemBuilder: (context, i) => Padding(
                      padding: EdgeInsets.all(10),
                      child: CustomMenuItemTile(item: items[i]),
                    ),
                  ),
          ),
          Padding(
            padding: const EdgeInsets.all(10),
            child: CustomFilledButton(
              width: double.infinity,
              height: 50,
              backgroundColor: AppColors.whisperGrey,
              foregroundColor: AppColors.inkBlack,
              onPressed: c.downloadMenu,
              child: Row(
                mainAxisAlignment: MainAxisAlignment.center,
                spacing: 10,
                children: [
                  SvgPicture.asset(
                    'assets/icons/download.svg',
                    width: 20,
                    height: 20,
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
    });
  }
}
