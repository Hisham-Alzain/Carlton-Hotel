import 'package:carlton/controllers/booking/find_booking_controller.dart';
import 'package:carlton/customWidgets/custom_auth_background.dart';
import 'package:carlton/customWidgets/custom_filled_button.dart';
import 'package:carlton/customWidgets/custom_outlined_button.dart';
import 'package:carlton/customWidgets/custom_text_field.dart';
import 'package:carlton/l10n/app_translations.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';

class FindBookingView extends GetView<FindBookingController> {
  const FindBookingView({super.key});

  @override
  Widget build(BuildContext context) {
    return CustomAuthBackground(
      showBackButton: true,
      logoWidth: 130,
      topPadding: 20,
      title: AppTranslations.findBookingTitle,
      subtitle: AppTranslations.findBookingSubtitle,
      child: GetBuilder<FindBookingController>(
        builder: (controller) => SingleChildScrollView(
          padding: const EdgeInsets.fromLTRB(23, 36, 23, 36),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            spacing: 12,
            children: [
              CustomTextField.auth(
                controller: controller.codeController,
                textInputType: TextInputType.text,
                hintText: AppTranslations.reservationCodeHint,
                label: AppTranslations.reservationCodeLabel,
                errorText: controller.codeError,
              ),
              Padding(
                padding: const EdgeInsets.only(top: 6),
                child: CustomTextField.auth(
                  controller: controller.lastNameController,
                  textInputType: TextInputType.name,
                  hintText: AppTranslations.lastNameHint,
                  label: AppTranslations.lastNameLabel,
                  errorText: controller.lastNameError,
                ),
              ),
              Padding(
                padding: const EdgeInsets.only(top: 16),
                child: CustomFilledButton.auth(
                  label: AppTranslations.findReservationButtonLabel,
                  isLoading: controller.isSubmitting,
                  onPressed: controller.submit,
                ),
              ),

              CustomOutlinedButton(
                height: 54,
                width: double.infinity,
                borderColor: const Color(0x66FFFFFF),
                borderWidth: 1.4,
                backgroundColor: Colors.transparent,
                foregroundColor: Colors.white,
                elevation: 0,
                onPressed: Get.back,
                child: Text(AppTranslations.goBack),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
