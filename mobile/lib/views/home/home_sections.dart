part of 'home_view.dart';

/// Top of the pre-arrival Home while the booking still awaits the hotel's
/// confirmation: the rest of the pre-arrival layout already shows the stay, so
/// this only adds the status note.
class _PendingBookingSection extends GetView<HomeController> {
  const _PendingBookingSection();

  @override
  Widget build(BuildContext context) {
    return Obx(() {
      if (!controller.upcomingPending.value) return const SizedBox.shrink();
      return Padding(
        padding: const EdgeInsets.only(bottom: 20),
        child: CustomInfoBanner(
          iconPath: 'assets/icons/clock.svg',
          message: AppTranslations.awaitingHotelConfirmation,
        ),
      );
    });
  }
}

/// The stay hero. Checked in → the interactive card; booked but not yet
/// arrived → the same card read-only; neither → nothing.
class _ActiveStaySection extends GetView<HomeController> {
  const _ActiveStaySection();

  @override
  Widget build(BuildContext context) {
    return Obx(() {
      // Placeholder while the first /stays/active fetch is in flight —
      // otherwise this collapses to nothing and the card pops in.
      if (controller.bookingLoading.value) {
        return const _CardShimmer(height: 220);
      }

      final stay = controller.activeStay.value;
      if (stay != null) {
        return CustomActiveBookingCard(
          stay: stay,
          doNotDisturb: controller.doNotDisturb.value,
          onDndChanged: controller.toggleDoNotDisturb,
          onRequest: controller.quickRequest,
          onConcierge: controller.openConcierge,
          onBill: controller.openBill,
          onCheckout: controller.checkout,
        );
      }

      final upcoming = controller.upcomingStay.value;
      if (upcoming == null) return const SizedBox.shrink();

      // Booked but not checked in: the header shows the reservation and the
      // in-stay controls are greyed until arrival.
      return CustomActiveBookingCard(
        stay: upcoming,
        interactive: false,
        doNotDisturb: false,
        onDndChanged: (_) {},
        onRequest: () {},
        onConcierge: () {},
        onBill: () {},
        onCheckout: () {},
      );
    });
  }
}

/// In-stay service requests — only exist once checked in.
class _ActiveRequestsSection extends GetView<HomeController> {
  const _ActiveRequestsSection();

  @override
  Widget build(BuildContext context) {
    return Obx(() {
      if (controller.bookingLoading.value) {
        return const _CardShimmer(height: 140);
      }
      if (controller.activeStay.value == null) return const SizedBox.shrink();
      return CustomActiveRequestsCard(
        // toList() both snapshots the list and registers the read — passing
        // the RxList itself would subscribe to nothing, since the card's
        // build runs outside this closure.
        requests: controller.activeRequests.toList(),
        onOpen: controller.openRequest,
        onNewRequest: controller.newRequest,
      );
    });
  }
}

/// Running bill — only exists once checked in.
class _CurrentBillSection extends GetView<HomeController> {
  const _CurrentBillSection();

  @override
  Widget build(BuildContext context) {
    return Obx(() {
      if (controller.bookingLoading.value) {
        return const _CardShimmer(height: 160);
      }
      if (controller.activeStay.value == null) return const SizedBox.shrink();
      return CustomCurrentBillCard(
        lines: controller.billLines.toList(),
        total: controller.billTotal.value,
        onFullStatement: controller.fullStatement,
      );
    });
  }
}

/// Static content — no Obx, only needs the controller for its callback.
class _AirportTransferSection extends GetView<HomeController> {
  const _AirportTransferSection();

  @override
  Widget build(BuildContext context) {
    return const AirportTransferSection(onRequest: showAirportTransferSheet);
  }
}

class _AiConciergeSection extends GetView<HomeController> {
  const _AiConciergeSection();

  @override
  Widget build(BuildContext context) {
    return CustomAiConciergeBanner(onTap: controller.openConcierge);
  }
}

class _VideoHeroSection extends GetView<HomeController> {
  const _VideoHeroSection();

  @override
  Widget build(BuildContext context) {
    return Obx(() {
      final slider = controller.videoHeroSlider;
      return CustomHomeContainer(
        // The video is the actual visual here; the still is only the poster
        // shown before it is ready, so it stays the bundled asset regardless of
        // what photo the slider carries.
        imagePath: AppAssets.heroHomeImagePath,
        videoController: controller.videoController,
        videoReady: controller.isVideoReady.value,
        location: slider?.location.value ?? AppTranslations.heroLocation,
        title: slider?.headerText.value ?? AppTranslations.heroVideoTitle,
        subtitle:
            slider?.descriptionText.value ?? AppTranslations.heroVideoSubtitle,
        onPrimary: controller.bookNow,
        onSecondary: controller.explore,
      );
    });
  }
}

class _DiningHeroSection extends GetView<HomeController> {
  const _DiningHeroSection();

  @override
  Widget build(BuildContext context) {
    return Obx(() {
      final slider = controller.diningHeroSlider;
      return CustomHomeContainer(
        imagePath: slider?.photo ?? AppAssets.heroDiningImagePath,
        location: slider?.location.value ?? AppTranslations.heroLocation,
        title: slider?.headerText.value ?? AppTranslations.heroDiningTitle,
        subtitle:
            slider?.descriptionText.value ?? AppTranslations.heroDiningSubtitle,
        onPrimary: controller.bookNow,
        onSecondary: controller.explore,
      );
    });
  }
}

/// Experiences in the explore state is still a placeholder hero, not the real
/// carousel — kept deliberately distinct from [_ExperiencesCarousel].
class _ExperiencesHeroSection extends GetView<HomeController> {
  const _ExperiencesHeroSection();

  @override
  Widget build(BuildContext context) {
    return SectionContainer(
      title: AppTranslations.experiences,
      onPressed: () => controller.discoverAll(DiscoverSection.experiences),
      child: Obx(() {
        final slider = controller.experiencesHeroSlider;
        return CustomHomeContainer(
          imagePath: slider?.photo ?? AppAssets.heroExperienceImagePath,
          location: slider?.location.value ?? AppTranslations.heroLocation,
          title:
              slider?.headerText.value ?? AppTranslations.heroExperiencesTitle,
          subtitle:
              slider?.descriptionText.value ??
              AppTranslations.heroExperiencesSubtitle,
          onPrimary: controller.bookNow,
          onSecondary: controller.explore,
        );
      }),
    );
  }
}
