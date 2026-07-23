import 'package:carlton/components/cards/custom_active_stay_card.dart';
import 'package:carlton/components/cards/custom_past_stay_card.dart';
import 'package:carlton/components/cards/custom_upcoming_stay_card.dart';
import 'package:carlton/controllers/stays/stays_controller.dart';
import 'package:carlton/customWidgets/custom_empty_placeholder.dart';
import 'package:carlton/customWidgets/custom_info_banner.dart';
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
      builder: (c) => Column(
        children: [
          TabBar(
            controller: c.tabController,
            tabs: const [
              Tab(text: 'Active'),
              Tab(text: 'Upcoming'),
              Tab(text: 'Past'),
            ],
          ),
          //TODO: do not pass controller excplictely when wireing up apis
          Expanded(
            child: TabBarView(
              controller: c.tabController,
              children: [
                _ActiveTab(controller: c),
                _UpcomingTab(controller: c),
                _PastTab(controller: c),
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
    final active = controller.active;
    if (active == null) {
      return const _Empty(
        title: 'No active stay',
        subtitle: 'Your current stay will appear here during check-in.',
      );
    }
    return ListView(
      padding: const EdgeInsets.all(20),
      children: [
        CustomActiveStayCard(
          stay: active,
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
    if (controller.upcoming.isEmpty) {
      return _Empty(
        title: 'No upcoming stays',
        subtitle: 'Book your next stay and it will show up here.',
        actionLabel: 'Book a Stay',
        onAction: controller.startBooking,
      );
    }
    return ListView.builder(
      padding: const EdgeInsets.all(20),
      itemCount: controller.upcoming.length,
      itemBuilder: (_, i) {
        final stay = controller.upcoming[i];
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

class _PastTab extends StatelessWidget {
  final StaysController controller;
  const _PastTab({required this.controller});

  @override
  Widget build(BuildContext context) {
    if (controller.past.isEmpty) {
      return const _Empty(
        title: 'No past stays',
        subtitle: 'Completed stays and receipts will appear here.',
      );
    }
    return ListView.builder(
      padding: const EdgeInsets.all(20),
      itemCount: controller.past.length,
      itemBuilder: (_, i) {
        final stay = controller.past[i];
        return CustomPastStayCard(
          stay: stay,
          onViewReceipt: () => controller.showReceipt(stay),
          onBookAgain: controller.startBooking,
        );
      },
    );
  }
}

class _Empty extends StatelessWidget {
  final String title;
  final String subtitle;
  final String? actionLabel;
  final VoidCallback? onAction;

  const _Empty({
    required this.title,
    required this.subtitle,
    this.actionLabel,
    this.onAction,
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
      primaryLabel: actionLabel,
      onPrimary: onAction,
    );
  }
}
