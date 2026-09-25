import 'package:carlton/l10n/app_translations.dart';
import 'package:carlton/controllers/booking/booking_flow_controller.dart';
import 'package:carlton/customWidgets/custom_country_code_picker.dart';
import 'package:carlton/customWidgets/custom_filled_button.dart';
import 'package:carlton/customWidgets/custom_scaffold.dart';
import 'package:carlton/customWidgets/custom_text_field.dart';
import 'package:carlton/customWidgets/custom_validation.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:flutter_svg/flutter_svg.dart';
import 'package:get/get.dart';
import 'package:smooth_page_indicator/smooth_page_indicator.dart';

/// Step 4 — guest contact details (Figma "Booking / Step 4").
class GuestDetailsView extends StatelessWidget {
  const GuestDetailsView({super.key});

  @override
  Widget build(BuildContext context) {
    final controller = Get.find<BookingFlowController>();
    return CustomScaffold(
      appBar: AppBar(
        title: Text(AppTranslations.guestDetails),
        iconTheme: IconThemeData(color: Colors.black),
        actions: [
          Container(
            decoration: BoxDecoration(
              shape: BoxShape.circle,
              color: AppColors.whisperGrey,
            ),
            child: IconButton(
              onPressed: () {},
              icon: SvgPicture.asset(
                'assets/icons/close.svg',
                width: 24,
                height: 24,
                colorFilter: const ColorFilter.mode(
                  AppColors.inkBlack,
                  BlendMode.srcIn,
                ),
              ),
            ),
          ),
        ],
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(10),
        child: Form(
          key: controller.guestFormKey,
          child: Column(
            spacing: 10,
            children: [
              AnimatedSmoothIndicator(
                activeIndex: 3,
                count: 6,
                effect: SlideEffect(
                  dotHeight: 5,
                  dotWidth: 50,
                  spacing: 20,
                  activeDotColor: AppColors.primary,
                  dotColor: AppColors.iceBlue,
                ),
              ),
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
              Row(
                crossAxisAlignment: CrossAxisAlignment.end,
                spacing: 10,
                children: [
                  CustomCountryCodePicker(
                    phoneField: controller.phone,
                    fillColor: AppColors.whisperGrey,
                  ),
                  Expanded(
                    child: CustomTextField(
                      controller: controller.phone.controller,
                      inputFormatters: [controller.phone.formatter],
                      textInputType: TextInputType.phone,
                      textDirection: TextDirection.ltr,
                      captionLabel: 'Phone Number*',
                      labelColor: AppColors.inkBlack,
                      hintText: AppTranslations.phoneNumberHint,
                      fillColor: AppColors.whisperGrey,
                      validator: (enteredPhoneNumber) =>
                          CustomValidation().validatePhoneNumber(
                            enteredPhoneNumber,
                            dialCode: controller.phone.dialCode,
                          ),
                    ),
                  ),
                ],
              ),
              CustomTextField(
                controller: controller.specialRequestsCtrl,
                textInputType: TextInputType.multiline,
                captionLabel: AppTranslations.specialRequestsTitle,
                labelColor: AppColors.inkBlack,
                hintText:
                    'Any dietary needs, room preferences, or special occasions…',
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
