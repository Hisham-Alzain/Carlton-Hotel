import 'package:carlton/controllers/auth/contact_entry_controller.dart';
import 'package:carlton/components/custom_auth_background.dart';
import 'package:carlton/customWidgets/custom_country_code_picker.dart';
import 'package:carlton/customWidgets/custom_filled_button.dart';
import 'package:carlton/customWidgets/custom_text_field.dart';
import 'package:carlton/customWidgets/custom_validation.dart';
import 'package:carlton/l10n/app_translations.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';

class ContactEntryView extends GetView<ContactEntryController> {
  const ContactEntryView({super.key});

  @override
  Widget build(BuildContext context) {
    return CustomAuthBackground(
      title: AppTranslations.addPhoneTitle,
      subtitle: AppTranslations.addPhoneSubtitle,
      child: Padding(
        padding: const EdgeInsets.all(10),
        child: Form(
          key: controller.formKey,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            spacing: 20,
            children: [
              CustomTextField(
                controller: controller.emailController,
                textInputType: TextInputType.emailAddress,
                captionLabel: 'Email Address',
                hintText: 'your@email.com',
                validator: (enteredEmail) =>
                    CustomValidation().validateRequiredField(enteredEmail) ??
                    CustomValidation().validateEmail(enteredEmail),
              ),
              Row(
                spacing: 10,
                mainAxisAlignment: MainAxisAlignment.end,
                children: [
                  CustomCountryCodePicker(phoneField: controller.phone),
                  Flexible(
                    child: CustomTextField(
                      controller: controller.phone.controller,
                      inputFormatters: [controller.phone.formatter],
                      textInputType: TextInputType.phone,
                      textDirection: TextDirection.ltr,
                      captionLabel: 'Phone Number',
                      hintText: 'Phone number',
                      validator: (enteredPhoneNumber) =>
                          CustomValidation().validatePhoneNumber(
                            enteredPhoneNumber,
                            dialCode: controller.phone.dialCode,
                          ),
                    ),
                  ),
                ],
              ),
              Padding(
                padding: const EdgeInsets.symmetric(vertical: 10),
                child: Obx(
                  () => CustomFilledButton(
                    width: 350,
                    backgroundColor: AppColors.lagoonTeal,
                    isLoading: controller.isSubmitting.value,
                    onPressed: controller.submit,
                    child: Text(AppTranslations.sendCodeButtonLabel),
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
