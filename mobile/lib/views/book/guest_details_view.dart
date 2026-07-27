import 'package:carlton/controllers/booking/booking_flow_controller.dart';
import 'package:carlton/customWidgets/custom_country_code_picker.dart';
import 'package:carlton/customWidgets/custom_filled_button.dart';
import 'package:carlton/customWidgets/custom_scaffold.dart';
import 'package:carlton/customWidgets/custom_text_field.dart';
import 'package:carlton/customWidgets/custom_validation.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
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
        title: Text('Guest Details'),
        iconTheme: IconThemeData(color: Colors.black),
        actions: [
          Container(
            decoration: BoxDecoration(
              shape: BoxShape.circle,
              color: AppColors.whisperGrey,
            ),
            child: IconButton(
              onPressed: () {},
              icon: const Icon(Icons.close, color: AppColors.inkBlack),
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
                      captionLabel: 'First Name',
                      labelColor: AppColors.inkBlack,
                      hintText: 'Ahmed',
                      fillColor: AppColors.whisperGrey,
                      validator: (enteredFirstName) => CustomValidation()
                          .validateRequiredField(enteredFirstName),
                    ),
                  ),
                  Expanded(
                    child: CustomTextField(
                      controller: controller.lastNameCtrl,
                      textInputType: TextInputType.name,
                      captionLabel: 'Last Name',
                      labelColor: AppColors.inkBlack,
                      hintText: 'Al-Rashid',
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
                captionLabel: 'Email Address *',
                labelColor: AppColors.inkBlack,
                hintText: 'your@email.com',
                fillColor: AppColors.whisperGrey,
                validator: (enteredEmail) =>
                    CustomValidation().validateEmail(enteredEmail),
              ),
              Row(
                crossAxisAlignment: CrossAxisAlignment.start,
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
                      hintText: 'Phone number',
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
                captionLabel: 'Special Requests',
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
                child: const Text('Continue'),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
