import 'package:carlton/l10n/app_translations.dart';
import 'package:carlton/components/cards/custom_active_stay_card.dart';
import 'package:carlton/components/cards/custom_past_stay_card.dart';
import 'package:carlton/components/cards/custom_upcoming_stay_card.dart';
import 'package:carlton/controllers/stays/stays_controller.dart';
import 'package:carlton/customWidgets/custom_empty_placeholder.dart';
import 'package:carlton/components/custom_info_banner.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:carlton/customWidgets/custom_indicators.dart';
import 'package:carlton/routes/routes.dart';
import 'package:carlton/services/middleware_service.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';

/// My Stays tab body: Active / Upcoming / Past over a shared TabBar. The app bar
/// and bottom nav come from the surrounding `MainView` shell.
class StaysView extends StatelessWidget {
  const StaysView({super.key});

  @override
  Widget build(BuildContext context) {
    // No observer here: the TabBar/TabBarView read only `tabController`, which
    // never changes. Each tab owns its own Obx so a fetch in one repaints one
    // tab instead of all three.
    final controller = Get.find<StaysController>();

    // A browsing guest has no stays to list — ask them to sign in rather than
    // showing three tabs of refused requests as connection errors. Reads only
    // the session, so signing in swaps it for the tabs.
    return Obx(
      () => MiddlewareService.find.isAuthenticated
          ? _tabs(controller)
          : CustomEmptyPlaceholder(
              iconPath: 'assets/images/ring.png',
              iconWidth: 90,
              iconHeight: 65,
              title: AppTranslations.staysSignInPromptTitle,
              primaryLabel: AppTranslations.signInButtonLabel,
              onPrimary: () => Get.toNamed(Routes.signIn),
              secondaryLabel: AppTranslations.createAccountLink,
              onSecondary: () => Get.toNamed(Routes.phoneEntry),
            ),
    );
  }

  Widget _tabs(StaysController controller) {
    return Column(
      children: [
        TabBar(
          controller: controller.tabController,
          tabs: [
            Tab(text: AppTranslations.active),
            Tab(text: AppTranslations.upcoming),
            Tab(text: AppTranslations.past),
          ],
        ),
        Expanded(
          child: TabBarView(
            controller: controller.tabController,
            children: const [_ActiveTab(), _UpcomingTab(), _PastTab()],
          ),
        ),
      ],
    );
  }
}

class _ActiveTab extends StatelessWidget {
  const _ActiveTab();

  @override
  Widget build(BuildContext context) {
    final controller = Get.find<StaysController>();

    return Obx(() {
      if (controller.activeLoading.value) return const _Loading();
      if (controller.activeError.value) {
        return _Retry(
          title: AppTranslations.loadStayFailed,
          subtitle: AppTranslations.checkConnectionRetry,
          onRetry: controller.reloadActive,
        );
      }
      final activeStay = controller.active.value;
      if (activeStay == null) {
        return _Empty(
          title: AppTranslations.noActiveStay,
          subtitle: AppTranslations.noActiveStaySubtitle,
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
    });
  }
}

class _UpcomingTab extends StatelessWidget {
  const _UpcomingTab();

  @override
  Widget build(BuildContext context) {
    final controller = Get.find<StaysController>();

    return Obx(() {
      if (controller.upcomingLoading.value) return const _Loading();
      if (controller.upcomingError.value) {
        return _Retry(
          title: AppTranslations.loadReservationsFailed,
          subtitle: AppTranslations.checkConnectionRetry,
          onRetry: controller.reloadUpcoming,
        );
      }
      if (controller.upcoming.isEmpty) {
        return _Empty(
          title: AppTranslations.noUpcomingStays,
          subtitle: AppTranslations.noUpcomingStaysSubtitle,
          primaryLabel: AppTranslations.bookAStay,
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
                onCopyCode: () => controller.copyBookingCode(stay),
              ),
              if (stay.nextCheckInDays != null)
                CustomInfoBanner(
                  iconPath: 'assets/icons/calendar.svg',
                  // The day count is a parameter, not a glued prefix: it sits
                  // mid-sentence in English and elsewhere in other locales.
                  message:
                      '${AppTranslations.nextCheckInInDays('${stay.nextCheckInDays}')} '
                      '${AppTranslations.preOrderBeforeArrival}',
                ),
            ],
          );
        },
      );
    });
  }
}

/// The Past tab reads the Rx state of [PaginatedControllerMixin] (items /
/// loading / hasError / loadingMore) and scrolls via the mixin's
/// scrollController.
class _PastTab extends StatelessWidget {
  const _PastTab();

  @override
  Widget build(BuildContext context) {
    final controller = Get.find<StaysController>();

    return Obx(() {
      if (controller.loading.value) return const _Loading();
      if (controller.hasError.value) {
        return _Retry(
          title: AppTranslations.loadPastFailed,
          subtitle: AppTranslations.checkConnectionRetry,
          onRetry: controller.reloadPast,
        );
      }
      if (controller.items.isEmpty) {
        return _Empty(
          title: AppTranslations.noPastStays,
          subtitle: AppTranslations.noPastStaysSubtitle,
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
              child: Center(child: SpinningIconIndicator(size: 28)),
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
      const Center(child: LogoLoadingIndicator(size: 50));
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
    return CustomEmptyPlaceholder.loadFailed(
      title: title,
      subtitle: subtitle,
      onRetry: onRetry,
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
