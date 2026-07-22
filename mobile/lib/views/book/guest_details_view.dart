import 'package:carlton/components/custom_booking_app_bar.dart';
import 'package:carlton/controllers/booking/booking_flow_controller.dart';
import 'package:carlton/customWidgets/custom_country_code_picker.dart';
import 'package:carlton/customWidgets/custom_filled_button.dart';
import 'package:carlton/customWidgets/custom_scaffold.dart';
import 'package:carlton/customWidgets/custom_text_field.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';

/// Step 4 — guest contact details (Figma "Booking / Step 4").
class GuestDetailsView extends StatelessWidget {
  const GuestDetailsView({super.key});

  static const _labelStyle = TextStyle(
    fontFamily: 'Plus Jakarta Sans',
    fontSize: 12,
    fontWeight: FontWeight.w600,
    color: AppColors.navLabel,
  );

  Widget _label(String text) => Padding(
    padding: const EdgeInsets.only(bottom: 6),
    child: Text(text, style: _labelStyle),
  );

  @override
  Widget build(BuildContext context) {
    final c = Get.find<BookingFlowController>();
    return CustomScaffold(
      appBar: const CustomBookingAppBar(title: 'Guest Details', currentStep: 4),
      body: Column(
        children: [
          Expanded(
            child: ListView(
              padding: const EdgeInsets.all(20),
              children: [
                Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  spacing: 12,
                  children: [
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          _label('First Name'),
                          CustomTextField(
                            controller: c.firstNameCtrl,
                            textInputType: TextInputType.name,
                            hintText: 'Ahmed',
                            fillColor: AppColors.greyField,
                          ),
                        ],
                      ),
                    ),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          _label('Last Name'),
                          CustomTextField(
                            controller: c.lastNameCtrl,
                            textInputType: TextInputType.name,
                            hintText: 'Al-Rashid',
                            fillColor: AppColors.greyField,
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 16),
                _label('Email Address *'),
                CustomTextField(
                  controller: c.emailCtrl,
                  textInputType: TextInputType.emailAddress,
                  hintText: 'your@email.com',
                  fillColor: AppColors.greyField,
                ),
                const SizedBox(height: 16),
                _label('Phone Number*'),
                Row(
                  crossAxisAlignment: CrossAxisAlignment.center,
                  spacing: 8,
                  children: [
                    CustomCountryCodePicker(
                      onCodeChanged: (code) =>
                          c.dialCode = code.dialCode ?? '+963',
                      fillColor: AppColors.greyField,
                    ),
                    Expanded(
                      child: CustomTextField(
                        controller: c.phoneCtrl,
                        textInputType: TextInputType.phone,
                        label: '',
                        hintText: 'Phone number',
                        fillColor: AppColors.greyField,
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 16),
                _label('Special Requests'),
                CustomTextField(
                  controller: c.specialRequestsCtrl,
                  textInputType: TextInputType.multiline,
                  hintText:
                      'Any dietary needs, room preferences, or special occasions…',
                  maxLines: 3,
                  fillColor: AppColors.greyField,
                ),
              ],
            ),
          ),
          SafeArea(
            top: false,
            child: Padding(
              padding: const EdgeInsets.fromLTRB(20, 8, 20, 12),
              child: CustomFilledButton(
                width: double.infinity,
                backgroundColor: AppColors.teal,
                onPressed: c.continueFromGuest,
                child: const Text('Continue'),
              ),
            ),
          ),
        ],
      ),
    );
  }
}
