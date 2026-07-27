import 'package:carlton/components/dining/custom_menu_item_tile.dart';
import 'package:carlton/constants/demo_data.dart';
import 'package:carlton/controllers/dining/restaurant_controller.dart';
import 'package:carlton/customWidgets/custom_pill_button.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';

/// Restaurant "menu" tab: category chips + dish list + Download Full Menu.
class RestaurantMenuTab extends StatelessWidget {
  final RestaurantController c;

  const RestaurantMenuTab({required this.c, super.key});

  @override
  Widget build(BuildContext context) {
    final category = DemoData.restaurantMenu[c.categoryIndex];

    return Column(
      children: [
        SizedBox(
          height: 44,
          child: ListView.separated(
            scrollDirection: Axis.horizontal,
            padding: const EdgeInsets.symmetric(horizontal: 16),
            itemCount: DemoData.restaurantMenu.length,
            separatorBuilder: (_, _) => const SizedBox(width: 8),
            itemBuilder: (context, i) {
              final selected = c.categoryIndex == i;
              return ChoiceChip(
                label: Text(DemoData.restaurantMenu[i].name),
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
              );
            },
          ),
        ),
        Expanded(
          child: ListView.separated(
            padding: const EdgeInsets.all(16),
            itemCount: category.items.length,
            separatorBuilder: (_, _) => const SizedBox(height: 12),
            itemBuilder: (context, i) =>
                CustomMenuItemTile(item: category.items[i]),
          ),
        ),
        Padding(
          padding: const EdgeInsets.fromLTRB(16, 4, 16, 16),
          child: CustomPillButton(
            label: 'Download Full Menu',
            onTap: c.downloadMenu,
            iconAsset: 'assets/icons/download.svg',
            backgroundColor: AppColors.whisperGrey,
            foregroundColor: AppColors.inkBlack,
            height: 48,
            expand: true,
            iconSize: 18,
            fontWeight: FontWeight.w500,
            fontFamily: 'DM Sans',
          ),
        ),
      ],
    );
  }
}
