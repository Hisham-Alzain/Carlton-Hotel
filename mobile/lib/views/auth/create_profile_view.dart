import 'package:carlton/controllers/auth/create_profile_controller.dart';
import 'package:carlton/customWidgets/custom_auth_background.dart';
import 'package:carlton/customWidgets/custom_filled_button.dart';
import 'package:carlton/customWidgets/custom_text_field.dart';
import 'package:carlton/l10n/app_translations.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';

class CreateProfileView extends GetView<CreateProfileController> {
  const CreateProfileView({super.key});

  @override
  Widget build(BuildContext context) {
    return CustomAuthBackground(
      showBackButton: true,
      logoWidth: 130,
      topPadding: 20,
      title: AppTranslations.createProfileTitle,
      subtitle: AppTranslations.createProfileSubtitle,
      child: GetBuilder<CreateProfileController>(
        builder: (controller) => SingleChildScrollView(
          padding: const EdgeInsets.fromLTRB(23, 36, 23, 36),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            spacing: 18,
            children: [
              CustomTextField.auth(
                controller: controller.firstNameController,
                textInputType: TextInputType.name,
                hintText: AppTranslations.firstNameHint,
                label: AppTranslations.firstNameLabel,
                errorText: controller.firstNameError,
              ),
              CustomTextField.auth(
                controller: controller.lastNameController,
                textInputType: TextInputType.name,
                hintText: AppTranslations.lastNameHint,
                label: AppTranslations.lastNameLabel,
                errorText: controller.lastNameError,
              ),
              Padding(
                padding: const EdgeInsets.only(top: 10),
                child: CustomFilledButton.auth(
                  label: AppTranslations.continueButtonLabel,
                  isLoading: controller.isSubmitting,
                  onPressed: controller.submit,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
