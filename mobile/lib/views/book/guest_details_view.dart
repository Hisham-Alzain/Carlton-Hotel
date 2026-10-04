import 'package:carlton/components/booking_step_header.dart';
import 'package:carlton/l10n/app_translations.dart';
import 'package:carlton/controllers/booking/booking_flow_controller.dart';
import 'package:carlton/customWidgets/custom_country_code_picker.dart';
import 'package:carlton/customWidgets/custom_filled_button.dart';
import 'package:carlton/customWidgets/custom_scaffold.dart';
import 'package:carlton/customWidgets/custom_text_field.dart';
import 'package:carlton/customWidgets/custom_validation.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';

/// Step 4 — guest contact details (Figma "Booking / Step 4").
class GuestDetailsView extends StatelessWidget {
  const GuestDetailsView({super.key});

  @override
  Widget build(BuildContext context) {
    final controller = Get.find<BookingFlowController>();
    return CustomScaffold(
      appBar: BookingStepAppBar(
        title: AppTranslations.guestDetails,
        onClose: Get.back,
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(10),
        child: Form(
          key: controller.guestFormKey,
          child: Column(
            spacing: 10,
            children: [
              BookingStepIndicator(step: 3),
              Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                spacing: 10,
                children: [
                  Expanded(
                    child: CustomTextField(
                      controller: controller.firstNameCtrl,
                      textInputType: TextInputType.name,
                      captionLabel: AppTranslations.firstNameLabel,
                      labelColor: AppColors.inkBlack,
                      hintText: AppTranslations.firstNameHint,
                      fillColor: AppColors.whisperGrey,
                      validator: (enteredFirstName) => CustomValidation()
                          .validateRequiredField(enteredFirstName),
                    ),
                  ),
                  Expanded(
                    child: CustomTextField(
                      controller: controller.lastNameCtrl,
                      textInputType: TextInputType.name,
                      captionLabel: AppTranslations.lastNameLabel,
                      labelColor: AppColors.inkBlack,
                      hintText: AppTranslations.lastNameHint,
                      fillColor: AppColors.whisperGrey,
                      validator: (enteredLastName) => CustomValidation()
                          .validateRequiredField(enteredLastName),
                    ),
                  ),
                ],
              ),
              CustomTextField(
                controller: controller.emailCtrl,
                textInputType: TextInputType.emailAddress,
                captionLabel: AppTranslations.emailAddressRequired,
                labelColor: AppColors.inkBlack,
                hintText: AppTranslations.emailAddressHint,
                fillColor: AppColors.whisperGrey,
                validator: (enteredEmail) =>
                    CustomValidation().validateEmail(enteredEmail),
              ),
              CustomPhoneField(
                phoneField: controller.phone,
                captionLabel: AppTranslations.phoneNumberRequired,
                labelColor: AppColors.inkBlack,
                fillColor: AppColors.whisperGrey,
                crossAxisAlignment: CrossAxisAlignment.end,
              ),
              CustomTextField(
                controller: controller.specialRequestsCtrl,
                textInputType: TextInputType.multiline,
                captionLabel: AppTranslations.specialRequestsTitle,
                labelColor: AppColors.inkBlack,
                hintText: AppTranslations.dietaryNotesHint,
                maxLines: 3,
                fillColor: AppColors.whisperGrey,
              ),
              CustomFilledButton(
                width: double.infinity,
                backgroundColor: AppColors.lagoonTeal,
                onPressed: controller.continueFromGuest,
                child: Text(AppTranslations.continueButtonLabel),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
