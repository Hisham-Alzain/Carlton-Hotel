import 'package:carlton/controllers/home/services_controller.dart';
import 'package:carlton/customWidgets/custom_empty_placeholder.dart';
import 'package:carlton/customWidgets/custom_scaffold.dart';
import 'package:carlton/customWidgets/custom_snackbar.dart';
import 'package:carlton/enums/enums.dart';
import 'package:carlton/routes/routes.dart';
import 'package:carlton/components/custom_active_stay_section.dart';
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
            case ServicesHomeState.guestBrowse:
              return const _GuestBrowse();
            case ServicesHomeState.exploreAndBook:
              return const _ExploreAndBook();
            case ServicesHomeState.activeStay:
              return CustomActiveStaySection(controller: controller);
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
