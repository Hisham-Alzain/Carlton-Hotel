import 'package:carlton/controllers/auth/otp_verify_controller.dart';
import 'package:carlton/components/custom_auth_background.dart';
import 'package:carlton/customWidgets/custom_filled_button.dart';
import 'package:carlton/customWidgets/custom_pinput.dart';
import 'package:carlton/customWidgets/custom_validation.dart';
import 'package:carlton/l10n/app_translations.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';

class OtpVerifyView extends GetView<OtpVerifyController> {
  const OtpVerifyView({super.key});

  @override
  Widget build(BuildContext context) {
    final TextTheme textStyle = Get.textTheme;

    return CustomAuthBackground(
      title: AppTranslations.verifyIdentityTitle,
      subtitle: AppTranslations.otpSentTo(controller.destination),
      child: GetBuilder<OtpVerifyController>(
        builder: (controller) => Padding(
          padding: const EdgeInsets.all(10),
          child: Form(
            key: controller.formKey,
            child: Column(
              spacing: 20,
              children: [
                CustomPinput(
                  controller: controller.pinController,
                  length: 6,
                  //TODO: change to otp validation when connecting api
                  validator: (p0) =>
                      CustomValidation().validateRequiredField(p0),
                  // onComplete: (_) => controller.verify(),
                ),

                GetBuilder<OtpVerifyController>(
                  id: OtpVerifyController.countdownId,
                  builder: (controller) => Center(
                    child: controller.secondsRemaining > 0
                        ? Text(
                            AppTranslations.resendIn(
                              '${controller.secondsRemaining}s',
                            ),
                            style: textStyle.labelLarge?.copyWith(
                              fontFamily: 'DM Sans',
                              color: AppColors.white50,
                              fontWeight: FontWeight.w400,
                            ),
                          )
                        : TextButton(
                            onPressed: controller.resend,
                            child: Text(
                              AppTranslations.resendCodeLink,
                              style: textStyle.labelLarge?.copyWith(
                                fontFamily: 'DM Sans',
                                color: AppColors.antiqueGold,
                                fontWeight: FontWeight.w500,
                                decoration: TextDecoration.underline,
                                decorationColor: AppColors.antiqueGold,
                              ),
                            ),
                          ),
                  ),
                ),
                Padding(
                  padding: const EdgeInsets.symmetric(vertical: 10),
                  child: CustomFilledButton(
                    width: 350,
                    backgroundColor: AppColors.lagoonTeal,
                    isLoading: controller.isVerifying,
                    onPressed: controller.verify,
                    child: Text(AppTranslations.verifyButtonLabel),
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
