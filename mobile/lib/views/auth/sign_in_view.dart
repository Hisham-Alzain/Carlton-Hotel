import 'package:carlton/controllers/auth/sign_in_controller.dart';
import 'package:carlton/components/custom_auth_background.dart';
import 'package:carlton/customWidgets/custom_country_code_picker.dart';
import 'package:carlton/customWidgets/custom_filled_button.dart';
import 'package:carlton/customWidgets/custom_segmented_button.dart';
import 'package:carlton/customWidgets/custom_text_field.dart';
import 'package:carlton/customWidgets/custom_validation.dart';
import 'package:carlton/enums/enums.dart';
import 'package:carlton/l10n/app_translations.dart';
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
      child: GetBuilder<SignInController>(
        builder: (controller) => Padding(
          padding: const EdgeInsets.all(10),
          child: Column(
            spacing: 20,
            children: [
              CustomSegmentedButton(
                selectedIndex: controller.method == SignInMethod.phone ? 0 : 1,
                onChanged: (index) => controller.switchMethod(
                  index == 0 ? SignInMethod.phone : SignInMethod.email,
                ),
                segments: [
                  SegmentItem(label: AppTranslations.signInByPhoneTab),
                  SegmentItem(label: AppTranslations.signInByEmailTab),
                ],
              ),
              controller.method == SignInMethod.email
                  ? CustomTextField(
                      controller: controller.emailController,
                      textInputType: TextInputType.emailAddress,
                      hintText: AppTranslations.emailAddressHint,
                      label: AppTranslations.emailAddressLabel,
                    )
                  : Row(
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
                  label: AppTranslations.nextButtonLabel,
                  isLoading: controller.isSubmitting,
                  onPressed: controller.submit,
                ),
              ),

              Container(
                height: 55,
                decoration: BoxDecoration(
                  color: AppColors.panelDark,
                  borderRadius: BorderRadius.circular(8),
                  border: Border.all(color: AppColors.borderDark, width: 1.5),
                ),
                child: Row(
                  spacing: 10,
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    Text(
                      AppTranslations.newGuestPrompt,
                      style: textStyle.labelLarge?.copyWith(
                        color: Colors.white,
                        fontWeight: FontWeight.w500,
                      ),
                    ),
                    GestureDetector(
                      onTap: () => Get.toNamed(Routes.createProfile),
                      child: Text(
                        AppTranslations.createAccountLink,
                        style: textStyle.labelLarge?.copyWith(
                          color: AppColors.gold,
                          fontWeight: FontWeight.w500,
                          decoration: TextDecoration.underline,
                          decorationColor: AppColors.gold,
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
