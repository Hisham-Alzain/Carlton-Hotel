import 'package:carlton/controllers/booking/reservation_choice_controller.dart';
import 'package:carlton/components/custom_auth_background.dart';
import 'package:carlton/components/cards/custom_choice_card.dart';
import 'package:carlton/l10n/app_translations.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';

class ReservationChoiceView extends GetView<ReservationChoiceController> {
  const ReservationChoiceView({super.key});

  @override
  Widget build(BuildContext context) {
    return CustomAuthBackground(
      title: AppTranslations.yourReservationTitle,
      subtitle: AppTranslations.yourReservationSubtitle,
      child: Padding(
        padding: const EdgeInsets.all(10),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          spacing: 20,
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
