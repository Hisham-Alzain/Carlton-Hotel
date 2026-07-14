import 'package:carlton/controllers/booking/reservation_choice_controller.dart';
import 'package:carlton/customWidgets/custom_auth_background.dart';
import 'package:carlton/customWidgets/custom_choice_card.dart';
import 'package:carlton/l10n/app_translations.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';

class ReservationChoiceView extends GetView<ReservationChoiceController> {
  const ReservationChoiceView({super.key});

  @override
  Widget build(BuildContext context) {
    return CustomAuthBackground(
      logoWidth: 130,
      topPadding: 20,
      title: AppTranslations.yourReservationTitle,
      subtitle: AppTranslations.yourReservationSubtitle,
      child: SingleChildScrollView(
        padding: const EdgeInsets.fromLTRB(23, 40, 23, 36),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          spacing: 14,
          children: [
            CustomChoiceCard(
              iconPath: 'assets/icons/ReservationChoiceScreen.svg',
              title: AppTranslations.haveReservationTitle,
              subtitle: AppTranslations.haveReservationSubtitle,
              onTap: controller.findBooking,
            ),
            CustomChoiceCard(
              iconPath: 'assets/icons/ReservationChoiceScreen2.svg',
              title: AppTranslations.noReservationTitle,
              subtitle: AppTranslations.noReservationSubtitle,
              onTap: controller.continueAsGuest,
            ),
          ],
        ),
      ),
    );
  }
}
