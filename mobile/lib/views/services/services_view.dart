import 'package:carlton/components/cards/custom_service_card.dart';
import 'package:carlton/components/cards/custom_stay_card.dart';
import 'package:carlton/components/cards/custom_request_card.dart';
import 'package:carlton/constants/demo_data.dart';
import 'package:carlton/controllers/home/services_controller.dart';
import 'package:carlton/customWidgets/custom_empty_placeholder.dart';
import 'package:carlton/customWidgets/custom_scaffold.dart';
import 'package:carlton/customWidgets/custom_snackbar.dart';
import 'package:carlton/enums/enums.dart';
import 'package:carlton/routes/routes.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';

class ServicesView extends GetView<ServicesController> {
  const ServicesView({super.key});

  @override
  Widget build(BuildContext context) {
    return CustomScaffold(
      body: GetBuilder<ServicesController>(
        builder: (controller) {
          switch (controller.homeState) {
            case ServicesHomeState.activeStay:
              return const _GuestBrowse();
            case ServicesHomeState.exploreAndBook:
              return const _ExploreAndBook();
            case ServicesHomeState.guestBrowse:
              return SingleChildScrollView(
                padding: const EdgeInsets.all(10),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  spacing: 20,
                  children: [
                    CustomStayCard(
                      room: DemoData.room,
                      checkedInTime: DemoData.checkedInTime,
                      nightsRemaining: DemoData.nightsRemaining,
                    ),
                    TabBar(
                      tabs: [Text('All Services'), Text('Active Requests')],
                      controller: controller.tabController,
                    ),

                    if (controller.tabIndex == 0)
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
                        itemBuilder: (context, index) =>
                            CustomServiceCard(item: controller.services[index]),
                      ),
                    if (controller.tabIndex == 1)
                      Column(
                        children: [
                          if (controller.activeRequests.isEmpty)
                            CustomEmptyPlaceholder(
                              iconPath: 'assets/icons/glass-empty.svg',
                              title: 'No active requests',
                              subtitle:
                                  'Your current requests will appear here once they are submitted',
                              primaryLabel: 'Browse services',
                              onPrimary: () => controller.switchTab(0),
                            ),
                          if (controller.activeRequests.isNotEmpty)
                            ...controller.activeRequests.map(
                              (request) => CustomRequestCard(
                                title: request.title,
                                detail: request.detail,
                                iconPath: request.status.iconPath,
                                iconBackgroundColor: request.status.iconBgColor,
                                statusLabel: request.status.label,
                                statusTextColor: request.status.textColor,
                                statusBackgroundColor: request.status.bgColor,
                                onEdit: () => controller.editRequest(request),
                                onCancel: () =>
                                    controller.cancelRequest(request),
                              ),
                            ),
                        ],
                      ),
                  ],
                ),
              );
          }
        },
      ),
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
      title: 'Sign in to access room services',
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
      title: 'Ready for your next stay?',
      subtitle: 'Book your stay to unlock in-room services.',
      primaryLabel: 'Explore & Book',
      onPrimary: () => CustomSnackbars.showInfo(message: 'Booking coming soon'),
    );
  }
}

// class _ActiveStay extends StatelessWidget {
//   const _ActiveStay();

//   @override
//   Widget build(BuildContext context) {
//     return
//   }
// }
