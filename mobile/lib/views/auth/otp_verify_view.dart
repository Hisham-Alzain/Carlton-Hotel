import 'package:carlton/controllers/auth/otp_verify_controller.dart';
import 'package:carlton/customWidgets/custom_auth_background.dart';
import 'package:carlton/customWidgets/custom_filled_button.dart';
import 'package:carlton/customWidgets/custom_pinput.dart';
import 'package:carlton/l10n/app_translations.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';

class OtpVerifyView extends GetView<OtpVerifyController> {
  const OtpVerifyView({super.key});

  @override
  Widget build(BuildContext context) {
    return CustomAuthBackground(
      showBackButton: true,
      logoWidth: 130,
      topPadding: 20,
      title: AppTranslations.verifyIdentityTitle,
      subtitle: AppTranslations.otpSentTo(controller.destination),
      child: GetBuilder<OtpVerifyController>(
        builder: (controller) => SingleChildScrollView(
          padding: const EdgeInsets.fromLTRB(23, 36, 23, 36),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            spacing: 8,
            children: [
              Center(
                child: CustomPinput(
                  controller: controller.pinController,
                  length: 6,
                  validator: null,
                  onComplete: (_) => controller.verify(),
                  fillColor: AppColors.cream,
                  textColor: AppColors.ink,
                  boxSize: 46,
                  hasError: controller.hasError,
                  errorBorderColor: AppColors.error,
                ),
              ),
              if (controller.hasError)
                Row(
                  spacing: 6,
                  children: [
                    const Icon(
                      Icons.error_outline,
                      size: 13,
                      color: AppColors.error,
                    ),
                    Expanded(
                      child: Text(
                        AppTranslations.codeMismatch,
                        style: const TextStyle(
                          color: AppColors.error,
                          fontSize: 12,
                        ),
                      ),
                    ),
                  ],
                ),
              Padding(
                padding: const EdgeInsets.only(top: 10),
                // Scoped to the countdown id so the once-a-second timer tick
                // repaints only this label, not the whole screen.
                child: GetBuilder<OtpVerifyController>(
                  id: OtpVerifyController.countdownId,
                  builder: (controller) => Center(
                    child: controller.secondsRemaining > 0
                        ? Text(
                            AppTranslations.resendIn(
                              '${controller.secondsRemaining}s',
                            ),
                            style: const TextStyle(
                              color: AppColors.textOnDarkFaint,
                              fontSize: 13,
                            ),
                          )
                        : GestureDetector(
                            onTap: controller.resend,
                            child: Text(
                              AppTranslations.resendCodeLink,
                              style: const TextStyle(
                                color: AppColors.gold,
                                fontSize: 13,
                                fontWeight: FontWeight.w500,
                                decoration: TextDecoration.underline,
                                decorationColor: AppColors.gold,
                              ),
                            ),
                          ),
                  ),
                ),
              ),
              Padding(
                padding: const EdgeInsets.only(top: 20),
                child: CustomFilledButton.auth(
                  label: AppTranslations.verifyButtonLabel,
                  isLoading: controller.isVerifying,
                  onPressed: controller.verify,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
