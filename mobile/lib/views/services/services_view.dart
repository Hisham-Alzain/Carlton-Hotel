import 'package:carlton/components/cards/custom_service_card.dart';
import 'package:carlton/components/cards/custom_stay_card.dart';
import 'package:carlton/components/home/custom_active_requests_card.dart';
import 'package:carlton/controllers/home/services_controller.dart';
import 'package:carlton/controllers/main/main_controller.dart';
import 'package:carlton/customWidgets/custom_empty_placeholder.dart';
import 'package:carlton/customWidgets/custom_scaffold.dart';
import 'package:carlton/enums/enums.dart';
import 'package:carlton/routes/routes.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:carlton/l10n/app_translations.dart';
import 'package:carlton/customWidgets/custom_indicators.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';

class ServicesView extends GetView<ServicesController> {
  const ServicesView({super.key});

  @override
  Widget build(BuildContext context) {
    return CustomScaffold(
      // homeState reads MiddlewareService's Rx session flags, so this observer
      // swaps the body the moment auth or the booking entitlement changes.
      body: Obx(() {
        switch (controller.homeState) {
          // Not signed in: services are gated behind sign-in.
          case ServicesHomeState.guestBrowse:
            return const _GuestBrowse();
          // Signed in, no booking: invite them to book.
          case ServicesHomeState.exploreAndBook:
            return const _ExploreAndBook();
          // Guest with a current stay: their room + full room-service catalog.
          case ServicesHomeState.activeStay:
            return const _ActiveStayServices();
        }
      }),
    );
  }
}

/// The in-stay Services screen (Figma "Services"): active-stay card, the
/// All Services / Active Requests tabs, the service grid + quick requests, and
/// the active-request list.
class _ActiveStayServices extends StatelessWidget {
  const _ActiveStayServices();

  @override
  Widget build(BuildContext context) {
    final controller = Get.find<ServicesController>();

    return SingleChildScrollView(
      // The pagination mixin's controller: nearing the bottom of the Active
      // Requests tab loads the next page of requests.
      controller: controller.scrollController,
      padding: const EdgeInsets.all(10),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        spacing: 20,
        children: [
          // The stay card and the tab body read disjoint fields, so each Obx
          // subscribes only to its own: loading the active stay no longer
          // repaints the service grid, and switching tabs no longer repaints
          // the card.
          Obx(
            () => CustomStayCard(
              roomName: controller.room.value,
              checkedInTime: controller.checkedInTime.value,
              nightsRemaining: controller.nightsRemaining.value,
              imagePath: controller.stayImagePath,
            ),
          ),
          TabBar(
            tabs: [
              Text(AppTranslations.allServicesTab),
              Text(AppTranslations.activeRequests),
            ],
            controller: controller.tabController,
          ),
          Obx(() => _tabBody(controller)),
        ],
      ),
    );
  }

  Widget _tabBody(ServicesController controller) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      spacing: 20,
      children: [
        if (controller.tabIndex.value == 0) ...[
          GridView.builder(
            shrinkWrap: true,
            physics: const NeverScrollableScrollPhysics(),
            itemCount: controller.services.length,
            gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
              crossAxisCount: 2,
              mainAxisSpacing: 20,
              crossAxisSpacing: 10,
              childAspectRatio: 1.5,
            ),
            itemBuilder: (context, index) => GestureDetector(
              onTap: () => controller.openServiceCategory(
                controller.services[index].code,
              ),
              child: CustomServiceCard(service: controller.services[index]),
            ),
          ),
          _QuickRequests(controller: controller),
        ],
        if (controller.tabIndex.value == 1)
          Column(
            children: [
              if (controller.activeRequests.isEmpty)
                CustomEmptyPlaceholder(
                  iconPath: 'assets/icons/glass-empty.svg',
                  title: AppTranslations.noActiveRequestsTitle,
                  subtitle: AppTranslations.noActiveRequestsSubtitle,
                  primaryLabel: AppTranslations.browseServicesButtonLabel,
                  onPrimary: () => controller.switchTab(0),
                ),
              if (controller.activeRequests.isNotEmpty)
                // The card no longer carries its own margin, so hold its
                // inset here: 10 from this Padding on top of the scroll
                // view's 10 keeps it exactly where it has always sat.
                Padding(
                  padding: const EdgeInsets.all(10),
                  child: CustomActiveRequestsCard(
                    requests: controller.activeRequests,
                    showHeading: false,
                    onOpen: controller.editRequest,
                    onNewRequest: () => controller.switchTab(0),
                  ),
                ),
              if (controller.loadingMore.value)
                const Padding(
                  padding: EdgeInsets.all(10),
                  child: SpinningIconIndicator(size: 28),
                ),
            ],
          ),
      ],
    );
  }
}

/// "Quick Requests" chips under the service grid (Figma "Services").
class _QuickRequests extends StatelessWidget {
  final ServicesController controller;
  const _QuickRequests({required this.controller});

  @override
  Widget build(BuildContext context) {
    final TextTheme textStyle = Get.textTheme;
    // Scoped to this section: the chips come from the catalog fetch, which
    // lands after the first frame, and nothing else on the screen depends on it.
    return Obx(() {
      final chips = controller.quickRequests;
      // Nothing submittable in one tap — show no heading rather than an
      // empty-looking section.
      if (chips.isEmpty) return const SizedBox.shrink();
      return Column(
        spacing: 10,
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            AppTranslations.quickRequests,
            style: textStyle.titleSmall?.copyWith(color: AppColors.primary),
          ),
          Wrap(
            spacing: 10,
            runSpacing: 10,
            children: chips
                .map(
                  (item) => ActionChip(
                    label: Text(item.name.value),
                    onPressed: () => controller.quickRequest(item),
                  ),
                )
                .toList(),
          ),
        ],
      );
    });
  }
}

/// Guest with no reservation: full services are gated, so we only show the
/// sign-in / create-account prompt.
class _GuestBrowse extends StatelessWidget {
  const _GuestBrowse();

  @override
  Widget build(BuildContext context) {
    return CustomEmptyPlaceholder(
      iconPath: 'assets/images/ring.png',
      iconWidth: 90,
      iconHeight: 65,
      title: AppTranslations.signInPromptTitle,
      primaryLabel: AppTranslations.signInButtonLabel,
      onPrimary: () => Get.toNamed(Routes.signIn),
      secondaryLabel: AppTranslations.createAccountLink,
      // Phone + OTP first: the account exists only once the code is verified,
      // and the name form after it needs that session's token.
      onSecondary: () => Get.toNamed(Routes.phoneEntry),
    );
  }
}

/// Signed in, but no current reservation: invite them to start a new booking.
class _ExploreAndBook extends StatelessWidget {
  const _ExploreAndBook();

  @override
  Widget build(BuildContext context) {
    return CustomEmptyPlaceholder(
      iconPath: 'assets/images/ring.png',
      iconWidth: 90,
      iconHeight: 65,
      title: AppTranslations.readyForNextStayTitle,
      subtitle: AppTranslations.unlockInRoom,
      primaryLabel: AppTranslations.exploreAndBookButtonLabel,
      // The Book tab of this same shell, not a route (see HomeController.bookNow).
      onPrimary: () => Get.find<MainController>().changeTab(2),
    );
  }
}
