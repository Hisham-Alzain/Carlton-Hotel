import 'package:carlton/components/cards/custom_service_option_tile.dart';
import 'package:carlton/controllers/home/services_controller.dart';
import 'package:carlton/customWidgets/custom_containers.dart';
import 'package:carlton/customWidgets/custom_scaffold.dart';
import 'package:carlton/l10n/app_translations.dart';
import 'package:carlton/models/service_catalog_item.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';

/// Lists a services-hub category's requestable options (Room Service,
/// Housekeeping, ...). The [ServiceCatalogItem] arrives via `Get.arguments`.
class ServiceCategoryDetailView extends StatelessWidget {
  const ServiceCategoryDetailView({super.key});

  @override
  Widget build(BuildContext context) {
    final categoryArgument = Get.arguments;
    if (categoryArgument is! ServiceCatalogItem) {
      WidgetsBinding.instance.addPostFrameCallback((_) => Get.back());
      return const CustomScaffold(body: SizedBox.shrink());
    }
    final category = categoryArgument;
    final controller = Get.find<ServicesController>();

    return CustomScaffold(
      appBar: AppBar(
        iconTheme: const IconThemeData(color: AppColors.inkBlack),
        title: Row(
          spacing: 10,
          crossAxisAlignment: CrossAxisAlignment.center,
          children: [
            PillContainer(
              width: 52,
              height: 52,
              radius: 14,
              backgroundColor: AppColors.pearlCream,
              child: Icon(
                _iconFor(category.icon),
                color: AppColors.antiqueGold,
              ),
            ),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    category.name.value,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: Get.textTheme.titleMedium?.copyWith(
                      fontWeight: FontWeight.w700,
                      color: AppColors.inkBlack,
                    ),
                  ),
                  Text(
                    category.description.value,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: Get.textTheme.labelMedium?.copyWith(
                      color: AppColors.dimGrey,
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
      body: Padding(
        padding: const EdgeInsets.all(10),
        child: category.items.isEmpty
            ? Center(
                child: Text(
                  AppTranslations.noItems,
                  style: Get.textTheme.labelMedium?.copyWith(
                    color: AppColors.dimGrey,
                  ),
                ),
              )
            : ListView.builder(
                padding: EdgeInsets.zero,
                itemCount: category.items.length,
                itemBuilder: (context, index) {
                  final option = category.items[index];
                  return CustomServiceOptionTile(
                    title: option.name.value,
                    description: option.description.value,
                    etaLabel: _etaLabel(option),
                    onTap: () => controller.openServiceRequest(option),
                  );
                },
              ),
      ),
    );
  }

  /// The API gives a category-level `icon` key (no per-item SVG); map the known
  /// ones to a Material glyph, with a generic fallback.
  IconData _iconFor(String icon) {
    switch (icon) {
      case 'room_service':
        return Icons.room_service_outlined;
      case 'housekeeping':
        return Icons.cleaning_services_outlined;
      case 'laundry':
        return Icons.local_laundry_service_outlined;
      case 'concierge':
        return Icons.support_agent_outlined;
      case 'transport':
        return Icons.directions_car_outlined;
      case 'maintenance':
        return Icons.build_outlined;
      case 'do_not_disturb':
        return Icons.do_not_disturb_on_outlined;
      case 'restaurant':
        return Icons.restaurant_outlined;
      default:
        return Icons.spa_outlined;
    }
  }

  /// Expected turnaround + optional price, e.g. "~30 min · $18.00".
  String _etaLabel(ServiceCatalogOption option) {
    final parts = <String>[];
    if (option.expectedMinutes != null) {
      parts.add(AppTranslations.etaMinutes(option.expectedMinutes!));
    }
    final price = option.priceUsd;
    if (price != null && price.isNotEmpty) parts.add('\$$price');
    return parts.join(' · ');
  }
}
