import 'package:carlton/controllers/auth/phone_entry_controller.dart';
import 'package:carlton/components/custom_auth_background.dart';
import 'package:carlton/customWidgets/custom_country_code_picker.dart';
import 'package:carlton/customWidgets/custom_filled_button.dart';
import 'package:carlton/customWidgets/custom_text_field.dart';
import 'package:carlton/customWidgets/custom_validation.dart';
import 'package:carlton/l10n/app_translations.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';

class PhoneEntryView extends GetView<PhoneEntryController> {
  const PhoneEntryView({super.key});

  @override
  Widget build(BuildContext context) {
    return CustomAuthBackground(
      title: AppTranslations.addPhoneTitle,
      subtitle: AppTranslations.addPhoneSubtitle,
      child: GetBuilder<PhoneEntryController>(
        builder: (controller) => Padding(
          padding: const EdgeInsets.all(10),
          child: Form(
            key: controller.formKey,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              spacing: 20,
              children: [
                Row(
                  spacing: 10,
                  mainAxisAlignment: MainAxisAlignment.end,
                  children: [
                    CustomCountryCodePicker(onCodeChanged: (p0) {}),
                    Flexible(
                      child: CustomTextField(
                        controller: controller.phoneController,
                        textInputType: TextInputType.phone,
                        label: 'Phone Number',
                        hintText: 'Phone number',
                        validator: (p0) =>
                            CustomValidation().validateRequiredField(p0),
                      ),
                    ),
                  ],
                ),
                Padding(
                  padding: const EdgeInsets.symmetric(vertical: 10),
                  child: CustomFilledButton(
                    width: 350,
                    backgroundColor: AppColors.teal,
                    isLoading: controller.isSubmitting,
                    onPressed: controller.submit,
                    child: Text(AppTranslations.sendCodeButtonLabel),
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
