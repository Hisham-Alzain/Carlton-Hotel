import 'package:carlton/components/cards/custom_active_stay_card.dart';
import 'package:carlton/components/cards/custom_past_stay_card.dart';
import 'package:carlton/components/cards/custom_upcoming_stay_card.dart';
import 'package:carlton/controllers/stays/stays_controller.dart';
import 'package:carlton/customWidgets/custom_empty_placeholder.dart';
import 'package:carlton/components/custom_info_banner.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';

/// My Stays tab body: Active / Upcoming / Past over a shared TabBar. The app bar
/// and bottom nav come from the surrounding `MainView` shell.
class StaysView extends StatelessWidget {
  const StaysView({super.key});

  @override
  Widget build(BuildContext context) {
    return GetBuilder<StaysController>(
      builder: (controller) => Column(
        children: [
          TabBar(
            controller: controller.tabController,
            tabs: const [
              Tab(text: 'Active'),
              Tab(text: 'Upcoming'),
              Tab(text: 'Past'),
            ],
          ),
          Expanded(
            child: TabBarView(
              controller: controller.tabController,
              children: [
                _ActiveTab(controller: controller),
                _UpcomingTab(controller: controller),
                _PastTab(controller: controller),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _ActiveTab extends StatelessWidget {
  final StaysController controller;
  const _ActiveTab({required this.controller});

  @override
  Widget build(BuildContext context) {
    if (controller.activeLoading) return const _Loading();
    if (controller.activeError) {
      return _Retry(
        title: "Couldn't load your stay",
        subtitle: 'Please check your connection and try again.',
        onRetry: controller.reloadActive,
      );
    }
    final activeStay = controller.active;
    if (activeStay == null) {
      return const _Empty(
        title: 'No active stay',
        subtitle: 'Your current stay will appear here during check-in.',
      );
    }
    return ListView(
      padding: const EdgeInsets.all(20),
      children: [
        CustomActiveStayCard(
          stay: activeStay,
          onRequestService: controller.requestService,
          onExpressCheckout: controller.expressCheckout,
        ),
      ],
    );
  }
}

class _UpcomingTab extends StatelessWidget {
  final StaysController controller;
  const _UpcomingTab({required this.controller});

  @override
  Widget build(BuildContext context) {
    if (controller.upcomingLoading) return const _Loading();
    if (controller.upcomingError) {
      return _Retry(
        title: "Couldn't load your reservations",
        subtitle: 'Please check your connection and try again.',
        onRetry: controller.reloadUpcoming,
      );
    }
    if (controller.upcoming.isEmpty) {
      return _Empty(
        title: 'No upcoming stays',
        subtitle: 'Book your next stay and it will show up here.',
        primaryLabel: 'Book a Stay',
        onPrimary: controller.startBooking,
      );
    }
    return ListView.builder(
      padding: const EdgeInsets.all(20),
      itemCount: controller.upcoming.length,
      itemBuilder: (_, index) {
        final stay = controller.upcoming[index];
        return Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            CustomUpcomingStayCard(
              stay: stay,
              onCancel: () => controller.requestCancel(stay),
            ),
            if (stay.nextCheckInDays != null)
              CustomInfoBanner(
                iconPath: 'assets/icons/calendar.svg',
                message:
                    'Your next check-in is in ${stay.nextCheckInDays} days. '
                    'Pre-order amenities and services before arrival.',
              ),
          ],
        );
      },
    );
  }
}

/// The one Obx island in this otherwise-GetBuilder view — the Past tab reads the
/// Rx state of [PaginatedControllerMixin] (items / loading / hasError /
/// loadingMore) and scrolls via the mixin's scrollController.
class _PastTab extends StatelessWidget {
  final StaysController controller;
  const _PastTab({required this.controller});

  @override
  Widget build(BuildContext context) {
    return Obx(() {
      if (controller.loading.value) return const _Loading();
      if (controller.hasError.value) {
        return _Retry(
          title: "Couldn't load past stays",
          subtitle: 'Please check your connection and try again.',
          onRetry: controller.reloadPast,
        );
      }
      if (controller.items.isEmpty) {
        return const _Empty(
          title: 'No past stays',
          subtitle: 'Completed stays and receipts will appear here.',
        );
      }
      final showLoadingMore = controller.loadingMore.value;
      return ListView.builder(
        controller: controller.scrollController,
        padding: const EdgeInsets.all(20),
        itemCount: controller.items.length + (showLoadingMore ? 1 : 0),
        itemBuilder: (_, index) {
          if (index >= controller.items.length) {
            return const Padding(
              padding: EdgeInsets.all(16),
              child: Center(child: CircularProgressIndicator()),
            );
          }
          final stay = controller.items[index];
          return CustomPastStayCard(
            stay: stay,
            onViewReceipt: () => controller.showReceipt(stay),
            onBookAgain: controller.startBooking,
          );
        },
      );
    });
  }
}

class _Loading extends StatelessWidget {
  const _Loading();

  @override
  Widget build(BuildContext context) =>
      const Center(child: CircularProgressIndicator());
}

class _Retry extends StatelessWidget {
  final String title;
  final String subtitle;
  final VoidCallback onRetry;

  const _Retry({
    required this.title,
    required this.subtitle,
    required this.onRetry,
  });

  @override
  Widget build(BuildContext context) {
    return CustomEmptyPlaceholder(
      iconWidget: const Icon(
        Icons.cloud_off_outlined,
        size: 50,
        color: AppColors.primary,
      ),
      title: title,
      subtitle: subtitle,
      primaryLabel: 'Retry',
      onPrimary: onRetry,
    );
  }
}

class _Empty extends StatelessWidget {
  final String title;
  final String subtitle;
  final String? primaryLabel;
  final VoidCallback? onPrimary;

  const _Empty({
    required this.title,
    required this.subtitle,
    this.primaryLabel,
    this.onPrimary,
  });

  @override
  Widget build(BuildContext context) {
    return CustomEmptyPlaceholder(
      iconWidget: const Icon(
        Icons.bed_outlined,
        size: 50,
        color: AppColors.primary,
      ),
      title: title,
      subtitle: subtitle,
      primaryLabel: primaryLabel,
      onPrimary: onPrimary,
    );
  }
}
