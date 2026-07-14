import 'package:carlton/controllers/auth/sign_in_controller.dart';
import 'package:carlton/customWidgets/custom_auth_background.dart';
import 'package:carlton/customWidgets/custom_filled_button.dart';
import 'package:carlton/customWidgets/custom_phone_field.dart';
import 'package:carlton/customWidgets/custom_segmented_toggle.dart';
import 'package:carlton/customWidgets/custom_text_field.dart';
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
    return CustomAuthBackground(
      title: AppTranslations.signInTitle,
      subtitle: AppTranslations.signInSubtitle,
      child: GetBuilder<SignInController>(
        builder: (controller) => SingleChildScrollView(
          padding: const EdgeInsets.fromLTRB(23, 34, 23, 36),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            spacing: 20,
            children: [
              CustomSegmentedToggle.outline(
                selectedIndex: controller.method == SignInMethod.phone ? 0 : 1,
                onChanged: (index) => controller.switchMethod(
                  index == 0 ? SignInMethod.phone : SignInMethod.email,
                ),
                segments: [
                  SegmentItem(label: AppTranslations.signInByPhoneTab),
                  SegmentItem(label: AppTranslations.signInByEmailTab),
                ],
              ),
              Padding(
                padding: const EdgeInsets.only(top: 8),
                child: controller.method == SignInMethod.phone
                    ? CustomPhoneField(
                        controller: controller.phoneController,
                        errorText: controller.phoneError,
                        label: AppTranslations.phoneNumber,
                      )
                    : _EmailField(controller: controller),
              ),
              CustomFilledButton.auth(
                label: AppTranslations.nextButtonLabel,
                isLoading: controller.isSubmitting,
                onPressed: controller.submit,
              ),
              const _NewGuestFooter(),
            ],
          ),
        ),
      ),
    );
  }
}

class _EmailField extends StatelessWidget {
  final SignInController controller;

  const _EmailField({required this.controller});

  @override
  Widget build(BuildContext context) {
    return CustomTextField.auth(
      controller: controller.emailController,
      textInputType: TextInputType.emailAddress,
      hintText: AppTranslations.emailAddressHint,
      label: AppTranslations.emailAddressLabel,
      errorText: controller.emailError,
    );
  }
}

class _NewGuestFooter extends StatelessWidget {
  const _NewGuestFooter();

  @override
  Widget build(BuildContext context) {
    return Container(
      height: 54,
      decoration: BoxDecoration(
        color: AppColors.panelDark,
        borderRadius: BorderRadius.circular(8),
        border: Border.all(color: AppColors.borderDark, width: 1.4),
      ),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          Text(
            AppTranslations.newGuestPrompt,
            style: const TextStyle(color: Colors.white, fontSize: 14),
          ),
          GestureDetector(
            onTap: () => Get.toNamed(Routes.createProfile),
            child: Text(
              AppTranslations.createAccountLink,
              style: const TextStyle(
                color: AppColors.gold,
                fontSize: 14,
                fontWeight: FontWeight.w500,
                decoration: TextDecoration.underline,
                decorationColor: AppColors.gold,
              ),
            ),
          ),
        ],
      ),
    );
  }
}
