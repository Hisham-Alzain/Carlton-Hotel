import 'package:carlton/controllers/auth/phone_entry_controller.dart';
import 'package:carlton/customWidgets/custom_auth_background.dart';
import 'package:carlton/customWidgets/custom_filled_button.dart';
import 'package:carlton/customWidgets/custom_phone_field.dart';
import 'package:carlton/l10n/app_translations.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';

class PhoneEntryView extends GetView<PhoneEntryController> {
  const PhoneEntryView({super.key});

  @override
  Widget build(BuildContext context) {
    return CustomAuthBackground(
      showBackButton: true,
      logoWidth: 130,
      topPadding: 20,
      title: AppTranslations.addPhoneTitle,
      subtitle: AppTranslations.addPhoneSubtitle,
      child: GetBuilder<PhoneEntryController>(
        builder: (controller) => SingleChildScrollView(
          padding: const EdgeInsets.fromLTRB(23, 36, 23, 36),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            spacing: 28,
            children: [
              CustomPhoneField(
                controller: controller.phoneController,
                errorText: controller.phoneError,
              ),
              CustomFilledButton.auth(
                label: AppTranslations.sendCodeButtonLabel,
                isLoading: controller.isSubmitting,
                onPressed: controller.submit,
              ),
            ],
          ),
        ),
      ),
    );
  }
}
