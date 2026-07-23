import 'package:carlton/controllers/auth/create_profile_controller.dart';
import 'package:carlton/components/custom_auth_background.dart';
import 'package:carlton/customWidgets/custom_filled_button.dart';
import 'package:carlton/customWidgets/custom_text_field.dart';
import 'package:carlton/customWidgets/custom_validation.dart';
import 'package:carlton/l10n/app_translations.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';

class CreateProfileView extends GetView<CreateProfileController> {
  const CreateProfileView({super.key});

  @override
  Widget build(BuildContext context) {
    return CustomAuthBackground(
      title: AppTranslations.createProfileTitle,
      subtitle: AppTranslations.createProfileSubtitle,
      //TODO: use obx instead  of getbuilder
      child: GetBuilder<CreateProfileController>(
        builder: (controller) => Padding(
          padding: const EdgeInsets.all(10),
          child: Form(
            key: controller.formKey,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              spacing: 20,
              children: [
                CustomTextField(
                  controller: controller.firstNameController,
                  textInputType: TextInputType.name,
                  hintText: AppTranslations.firstNameHint,
                  label: AppTranslations.firstNameLabel,
                  validator: (p0) =>
                      CustomValidation().validateRequiredField(p0),
                ),
                CustomTextField(
                  controller: controller.lastNameController,
                  textInputType: TextInputType.name,
                  hintText: AppTranslations.lastNameHint,
                  label: AppTranslations.lastNameLabel,
                  validator: (p0) =>
                      CustomValidation().validateRequiredField(p0),
                ),
                Padding(
                  padding: const EdgeInsets.symmetric(vertical: 10),
                  child: CustomFilledButton(
                    width: 350,
                    backgroundColor: AppColors.lagoonTeal,
                    isLoading: controller.isSubmitting,
                    onPressed: controller.submit,
                    child: Text(AppTranslations.continueButtonLabel),
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
