import 'package:carlton/components/dining/custom_menu_item_tile.dart';
import 'package:carlton/customWidgets/custom_filled_button.dart';
import 'package:carlton/models/menu.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:carlton/customWidgets/custom_texts.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';

/// Restaurant "menu" tab: category chips + dish list + Download Full Menu.
/// Categories and dishes come from the public content API; a venue with no menu
/// shows an empty state.
class RestaurantMenuTab extends StatelessWidget {
  final List<MenuCategory> categories;

  /// Already filtered to [selectedCategory] by the caller — this tab renders
  /// the list it is handed rather than deciding what belongs in it.
  final List<MenuItem> items;
  final int selectedCategory;
  final bool loading;
  final ValueChanged<int> onSelectCategory;
  final VoidCallback onDownloadMenu;

  const RestaurantMenuTab({
    required this.categories,
    required this.items,
    required this.selectedCategory,
    required this.loading,
    required this.onSelectCategory,
    required this.onDownloadMenu,
    super.key,
  });

  @override
  Widget build(BuildContext context) {
    if (loading) {
      return const Center(child: CircularProgressIndicator());
    }

    return Column(
      children: [
        if (categories.isNotEmpty)
          SizedBox(
            height: 50,
            child: ListView.builder(
              scrollDirection: Axis.horizontal,
              itemCount: categories.length,
              itemBuilder: (context, i) {
                final selected = selectedCategory == i;
                return Padding(
                  padding: const EdgeInsets.all(10),
                  child: ChoiceChip(
                    label: Text(categories[i].name.value),
                    selected: selected,
                    onSelected: (_) => onSelectCategory(i),
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
            onPressed: onDownloadMenu,
            child: RowTextComponent(
              text: 'Download Full Menu',
              icon: Icons.download,
              spacing: 10,
              mainAxisAlignment: MainAxisAlignment.center,
              textStyle: Get.textTheme.labelLarge?.copyWith(
                fontFamily: 'DM Sans',
                fontWeight: FontWeight.w500,
              ),
            ),
          ),
        ),
      ],
    );
  }
}
