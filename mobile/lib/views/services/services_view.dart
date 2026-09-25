import 'package:carlton/l10n/app_translations.dart';
import 'package:carlton/components/cards/custom_service_card.dart';
import 'package:carlton/components/cards/custom_stay_card.dart';
import 'package:carlton/components/home/custom_active_requests_card.dart';
import 'package:carlton/controllers/home/services_controller.dart';
import 'package:carlton/customWidgets/custom_empty_placeholder.dart';
import 'package:carlton/customWidgets/custom_scaffold.dart';
import 'package:carlton/customWidgets/custom_snackbar.dart';
import 'package:carlton/enums/enums.dart';
import 'package:carlton/routes/routes.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';

class ServicesView extends GetView<ServicesController> {
  const ServicesView({super.key});

  @override
  Widget build(BuildContext context) {
    return CustomScaffold(
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
            return _ActiveStayServices(controller: controller);
        }
      }),
    );
  }
}

/// The in-stay Services screen (Figma "Services"): active-stay card, the
/// All Services / Active Requests tabs, the service grid + quick requests, and
/// the active-request list.
class _ActiveStayServices extends StatelessWidget {
  final ServicesController controller;
  const _ActiveStayServices({required this.controller});

  @override
  Widget build(BuildContext context) {
    return SingleChildScrollView(
      padding: const EdgeInsets.all(10),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        spacing: 20,
        children: [
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
          Obx(
            () => Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.start,
              spacing: 20,
              children: [
                if (controller.tabIndex.value == 0) ...[
                  GridView.builder(
                    shrinkWrap: true,
                    physics: const NeverScrollableScrollPhysics(),
                    itemCount: controller.services.length,
                    gridDelegate:
                        const SliverGridDelegateWithFixedCrossAxisCount(
                          crossAxisCount: 2,
                          mainAxisSpacing: 20,
                          crossAxisSpacing: 10,
                          childAspectRatio: 1.5,
                        ),
                    itemBuilder: (context, index) => GestureDetector(
                      onTap: () => controller.openServiceCategory(
                        controller.services[index].code,
                      ),
                      child: CustomServiceCard(
                        service: controller.services[index],
                      ),
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
                          subtitle:
                              'Your current requests will appear here once they are submitted',
                          primaryLabel:
                              AppTranslations.browseServicesButtonLabel,
                          onPrimary: () => controller.switchTab(0),
                        ),
                      if (controller.activeRequests.isNotEmpty)
                        // The card no longer carries its own margin, so hold
                        // its inset here: 10 from this Padding on top of the
                        // scroll view's 10 keeps it exactly where it has
                        // always sat.
                        Padding(
                          padding: const EdgeInsets.all(10),
                          child: CustomActiveRequestsCard(
                            requests: controller.activeRequests.toList(),
                            showHeading: false,
                            onOpen: controller.editRequest,
                            onNewRequest: () => controller.switchTab(0),
                          ),
                        ),
                    ],
                  ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

/// "Quick Requests" chips under the service grid (Figma "Services").
class _QuickRequests extends StatelessWidget {
  final ServicesController controller;
  const _QuickRequests({required this.controller});

  // No preset quick-request catalog exists on the backend yet, and these
  // chips never submitted anything real even when they showed hardcoded text.
  static const List<String> _quickRequestLabels = [];

  @override
  Widget build(BuildContext context) {
    if (_quickRequestLabels.isEmpty) return const SizedBox.shrink();
    final TextTheme textStyle = Get.textTheme;
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
          children: _quickRequestLabels
              .map((label) => Chip(label: Text(label)))
              .toList(),
        ),
      ],
    );
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
      primaryLabel: 'Sign in',
      onPrimary: () => Get.toNamed(Routes.signIn),
      secondaryLabel: 'Create Account',
      onSecondary: () => Get.toNamed(Routes.createProfile),
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
      onPrimary: () =>
          CustomSnackbars.showInfo(message: AppTranslations.bookingComingSoon),
    );
  }
}
