import 'package:carlton/controllers/auth/sign_in_controller.dart';
import 'package:carlton/components/custom_auth_background.dart';
import 'package:carlton/customWidgets/custom_country_code_picker.dart';
import 'package:carlton/customWidgets/custom_filled_button.dart';
import 'package:carlton/customWidgets/custom_segmented_button.dart';
import 'package:carlton/customWidgets/custom_text_field.dart';
import 'package:carlton/customWidgets/custom_validation.dart';
import 'package:carlton/enums/enums.dart';
import 'package:carlton/l10n/app_translations.dart';
import 'package:carlton/models/segement_item.dart';
import 'package:carlton/routes/routes.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';

class SignInView extends GetView<SignInController> {
  const SignInView({super.key});

  @override
  Widget build(BuildContext context) {
    final TextTheme textStyle = Get.textTheme;

    return CustomAuthBackground(
      title: AppTranslations.signInTitle,
      subtitle: AppTranslations.signInSubtitle,
      child: Padding(
        padding: const EdgeInsets.all(10),
        child: Form(
          key: controller.formKey,
          child: Column(
            spacing: 20,
            // Each reactive child gets its own Obx rather than one Obx around
            // a group: Column.spacing counts children, so collapsing two into
            // a single Obx would drop a 20px gap.
            children: [
              Obx(
                () => CustomSegmentedButton(
                  expanded: true,
                  selectedIndex: controller.method.value == SignInMethod.phone
                      ? 0
                      : 1,
                  onChanged: (index) => controller.switchMethod(
                    index == 0 ? SignInMethod.phone : SignInMethod.email,
                  ),
                  segments: [
                    SegmentItem(label: AppTranslations.signInByPhoneTab),
                    SegmentItem(label: AppTranslations.signInByEmailTab),
                  ],
                ),
              ),
              Obx(
                () => controller.method.value == SignInMethod.email
                    ? CustomTextField(
                        controller: controller.emailController,
                        textInputType: TextInputType.emailAddress,
                        hintText: AppTranslations.emailAddressHint,
                        captionLabel: AppTranslations.emailAddressLabel,
                        validator: (enteredEmail) =>
                            CustomValidation().validateEmail(enteredEmail),
                      )
                    : Row(
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
              ),
              Obx(
                () => CustomFilledButton(
                  width: double.infinity,
                  backgroundColor: AppColors.lagoonTeal,
                  isLoading: controller.isSubmitting.value,
                  onPressed: controller.submit,
                  child: Text(AppTranslations.nextButtonLabel),
                ),
              ),

              Container(
                height: 50,
                decoration: BoxDecoration(
                  color: AppColors.coffeeInk64,
                  borderRadius: BorderRadius.circular(8),
                  border: Border.all(color: AppColors.ironGrey, width: 1.5),
                ),
                child: Row(
                  spacing: 10,
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    Text(
                      AppTranslations.newGuestPrompt,
                      style: textStyle.labelLarge?.copyWith(
                        fontFamily: 'DM Sans',
                        color: Colors.white,
                        fontWeight: FontWeight.w500,
                      ),
                    ),
                    GestureDetector(
                      onTap: () => Get.toNamed(Routes.createProfile),
                      child: Text(
                        AppTranslations.createAccountLink,
                        style: textStyle.labelLarge?.copyWith(
                          fontFamily: 'DM Sans',
                          color: AppColors.antiqueGold,
                          fontWeight: FontWeight.w500,
                          decoration: TextDecoration.underline,
                          decorationColor: AppColors.antiqueGold,
                        ),
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
