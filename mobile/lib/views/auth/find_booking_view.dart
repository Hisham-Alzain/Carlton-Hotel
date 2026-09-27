import 'package:carlton/controllers/auth/find_booking_controller.dart';
import 'package:carlton/components/custom_auth_background.dart';
import 'package:carlton/customWidgets/custom_country_code_picker.dart';
import 'package:carlton/customWidgets/custom_filled_button.dart';
import 'package:carlton/customWidgets/custom_text_field.dart';
import 'package:carlton/customWidgets/custom_validation.dart';
import 'package:carlton/l10n/app_translations.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';

class FindBookingView extends GetView<FindBookingController> {
  const FindBookingView({super.key});

  @override
  Widget build(BuildContext context) {
    return CustomAuthBackground(
      title: AppTranslations.findBookingTitle,
      subtitle: AppTranslations.findBookingSubtitle,
      child: Padding(
        padding: const EdgeInsets.all(10),
        child: Form(
          key: controller.formKey,
          child: Column(
            spacing: 20,
            children: [
              CustomTextField(
                controller: controller.codeController,
                textInputType: TextInputType.text,
                hintText: AppTranslations.reservationCodeHint,
                captionLabel: AppTranslations.reservationCodeLabel,
                validator: (enteredReservationCode) => CustomValidation()
                    .validateRequiredField(enteredReservationCode),
              ),
              // The phone on the reservation — the code is texted to it.
              Row(
                spacing: 10,
                crossAxisAlignment: CrossAxisAlignment.start,
                mainAxisAlignment: MainAxisAlignment.end,
                children: [
                  CustomCountryCodePicker(phoneField: controller.phone),
                  Flexible(
                    child: CustomTextField(
                      controller: controller.phone.controller,
                      inputFormatters: [controller.phone.formatter],
                      textInputType: TextInputType.phone,
                      textDirection: TextDirection.ltr,
                      captionLabel: AppTranslations.phoneNumber,
                      hintText: AppTranslations.phoneNumberHint,
                      validator: (enteredPhoneNumber) =>
                          CustomValidation().validatePhoneNumber(
                            enteredPhoneNumber,
                            dialCode: controller.phone.dialCode,
                          ),
                    ),
                  ),
                ],
              ),
              Obx(
                () => CustomFilledButton(
                  width: double.infinity,
                  backgroundColor: AppColors.lagoonTeal,
                  isLoading: controller.isSubmitting.value,
                  onPressed: controller.submit,
                  child: Text(AppTranslations.findReservationButtonLabel),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
