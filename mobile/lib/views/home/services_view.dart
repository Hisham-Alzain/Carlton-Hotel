import 'package:carlton/controllers/home/services_controller.dart';
import 'package:carlton/components/custom_app_bar.dart';
import 'package:carlton/customWidgets/custom_empty_placeholder.dart';
import 'package:carlton/components/custom_logo_avatar.dart';
import 'package:carlton/customWidgets/custom_scaffold.dart';
import 'package:carlton/customWidgets/custom_snackbar.dart';
import 'package:carlton/enums/enums.dart';
import 'package:carlton/routes/routes.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:carlton/components/custom_active_stay_section.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';

class ServicesView extends GetView<ServicesController> {
  const ServicesView({super.key});

  @override
  Widget build(BuildContext context) {
    return CustomScaffold(
      appBar: CustomAppBar(
        title: 'Services',
        leading: const Icon(Icons.menu),
        actions: [
          Padding(
            padding: const EdgeInsets.only(right: 20),
            child: CustomLogoAvatar(
              bordered: true,
              onTap: () => Get.toNamed(Routes.aiConcierge),
              // Long-press keeps the demo session-state toggle available.
              onLongPress: controller.toggleLogin,
            ),
          ),
        ],
      ),
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
      iconWidth: 91,
      iconHeight: 64,
      title: 'Sign in to access room services',
      subtitle: 'Book Now and the full services list unlock once you sign in.',
      primaryLabel: 'Sign in',
      primaryStyle: EmptyActionStyle.filled,
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
      iconWidget: Container(
        width: 64,
        height: 64,
        alignment: Alignment.center,
        decoration: const BoxDecoration(
          color: AppColors.primaryTileBg,
          shape: BoxShape.circle,
        ),
        child: const Icon(
          Icons.hotel_outlined,
          color: AppColors.primary,
          size: 30,
        ),
      ),
      title: 'Ready for your next stay?',
      subtitle:
          "You're signed in but don't have an active reservation. Explore "
          'our rooms and book your stay to unlock in-room services.',
      primaryLabel: 'Explore & Book',
      primaryStyle: EmptyActionStyle.filled,
      onPrimary: () => CustomSnackbars.showInfo(message: 'Booking coming soon'),
    );
  }
}
