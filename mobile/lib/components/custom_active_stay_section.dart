import 'package:carlton/controllers/home/services_controller.dart';
import 'package:carlton/customWidgets/custom_containers.dart';
import 'package:carlton/customWidgets/custom_empty_placeholder.dart';
import 'package:carlton/customWidgets/custom_image.dart';
import 'package:carlton/customWidgets/custom_request_tile.dart';
import 'package:carlton/customWidgets/custom_snackbar.dart';
import 'package:carlton/components/cards/custom_stay_card.dart';
import 'package:carlton/customWidgets/custom_tab_bar.dart';
import 'package:carlton/models/service_item.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';

class CustomActiveStaySection extends StatelessWidget {
  final ServicesController controller;

  const CustomActiveStaySection({required this.controller, super.key});

  @override
  Widget build(BuildContext context) {
    return SingleChildScrollView(
      padding: const EdgeInsets.fromLTRB(20, 16, 20, 24),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        spacing: 20,
        children: [
          CustomStayCard(
            room: controller.room,
            checkedInTime: controller.checkedInTime,
            nightsRemaining: controller.nightsRemaining,
            imagePath: controller.stayImagePath,
          ),
          // 20 spacing + 4 = the design's 24 gap above the tabs.
          Padding(
            padding: const EdgeInsets.only(top: 4),
            child: CustomTabBar(
              controller: controller.tabController,
              labels: [
                'All Services',
                'Active Requests (${controller.activeRequests.length})',
              ],
            ),
          ),
          if (controller.tabIndex == 0)
            _ServicesGrid(controller: controller)
          else
            _ActiveRequestsList(controller: controller),
        ],
      ),
    );
  }
}

/// The 2-column tile grid on the "All Services" tab.
class _ServicesGrid extends StatelessWidget {
  final ServicesController controller;

  const _ServicesGrid({required this.controller});

  @override
  Widget build(BuildContext context) {
    return GridView.builder(
      shrinkWrap: true,
      physics: const NeverScrollableScrollPhysics(),
      itemCount: controller.services.length,
      gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
        crossAxisCount: 2,
        mainAxisSpacing: 20.4,
        crossAxisSpacing: 11.2,
        childAspectRatio: 171.389 / 117.32,
      ),
      itemBuilder: (context, index) =>
          _ServiceTile(item: controller.services[index]),
    );
  }
}

/// One grid tile — Figma tile design: a tinted `CustomCard` with the title +
/// subtitle top-left and the service photo (via `CustomImage`) bleeding off
/// the bottom-right corner. Text scale is clamped to 1.0 because the tile's
/// geometry is fixed to Figma's exact pixels.
class _ServiceTile extends StatelessWidget {
  final ServiceItem item;

  const _ServiceTile({required this.item});

  @override
  Widget build(BuildContext context) {
    return CustomCard(
      color: AppColors.primaryTileBg,
      borderColor: Colors.white,
      borderWidth: 1.5,
      borderRadius: 12,
      padding: EdgeInsets.zero,
      onTap: () => CustomSnackbars.showInfo(message: '${item.title} selected'),
      child: MediaQuery(
        data: MediaQuery.of(
          context,
        ).copyWith(textScaler: const TextScaler.linear(1.0)),
        child: Stack(
          children: [
            PositionedDirectional(
              end: -2,
              bottom: -6,
              child: Opacity(
                opacity: item.imageOpacity,
                child: CustomImage(
                  path: item.imagePath,
                  width: item.imageWidth,
                  height: item.imageHeight,
                  fit: BoxFit.contain,
                ),
              ),
            ),
            Padding(
              padding: const EdgeInsets.all(16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                mainAxisSize: MainAxisSize.min,
                spacing: 2,
                children: [
                  Text(
                    item.title,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                      color: AppColors.primary,
                      fontSize: 14,
                    ),
                  ),
                  Text(
                    item.subtitle,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                      color: AppColors.tileSubtitle,
                      fontSize: 11.5,
                      fontWeight: FontWeight.w500,
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

/// The "Active Requests" tab: request tiles, or the empty state.
class _ActiveRequestsList extends StatelessWidget {
  final ServicesController controller;

  const _ActiveRequestsList({required this.controller});

  @override
  Widget build(BuildContext context) {
    if (controller.activeRequests.isEmpty) {
      return CustomEmptyPlaceholder(
        iconPath: 'assets/icons/glass-empty.svg',
        title: 'No active requests',
        subtitle:
            'Your current requests will appear here once they are submitted',
        primaryLabel: 'Browse services',
        onPrimary: () => controller.switchTab(0),
      );
    }

    return Column(
      spacing: 12,
      children: [
        for (final request in controller.activeRequests)
          CustomRequestTile(
            title: request.title,
            detail: request.detail,
            iconPath: request.status.iconPath,
            iconBackgroundColor: request.status.iconBgColor,
            statusLabel: request.status.label,
            statusTextColor: request.status.textColor,
            statusBackgroundColor: request.status.bgColor,
            onEdit: () => controller.editRequest(request),
            onCancel: () => controller.cancelRequest(request),
          ),
      ],
    );
  }
}
