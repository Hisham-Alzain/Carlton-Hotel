import 'dart:async';

import 'package:carlton/constants/demo_data.dart';
import 'package:carlton/customWidgets/custom_snackbar.dart';
import 'package:carlton/l10n/app_translations.dart';
import 'package:carlton/routes/routes.dart';
import 'package:carlton/services/session_service.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';

/// Demo-only: the "correct" code is [DemoData.otpCode]. Any other 6 digits
/// shows the exact Figma error copy and immediately reveals "Resend code"
/// (matching the error-state screenshot, where the countdown has elapsed).
class OtpVerifyController extends GetxController {
  /// GetBuilder id for the resend-countdown label: the timer ticks once a
  /// second, and rebuilding the whole screen for one Text is wasted work.
  /// Note: a plain `update()` does NOT refresh id-scoped builders, so state
  /// changes touching [secondsRemaining] must also emit this id.
  static const countdownId = 'countdown';

  late final String destination;
  final pinController = TextEditingController();

  bool hasError = false;
  bool isVerifying = false;
  int secondsRemaining = DemoData.otpResendSeconds;
  Timer? _timer;

  @override
  void onInit() {
    super.onInit();
    destination = Get.arguments is String ? Get.arguments as String : '';
    _startCountdown();
  }

  void _startCountdown() {
    _timer?.cancel();
    secondsRemaining = DemoData.otpResendSeconds;
    update([countdownId]);
    _timer = Timer.periodic(const Duration(seconds: 1), (timer) {
      secondsRemaining--;
      if (secondsRemaining <= 0) {
        secondsRemaining = 0;
        timer.cancel();
      }
      update([countdownId]);
    });
  }

  void resend() {
    CustomSnackbars.showInfo(message: AppTranslations.demoNewCodeSent);
    pinController.clear();
    hasError = false;
    update();
    _startCountdown();
  }

  Future<void> verify() async {
    // Captured before the await: popping the screen mid-delay disposes
    // pinController.
    final code = pinController.text;
    if (code.length < 6) return;

    isVerifying = true;
    update();
    await Future.delayed(DemoData.networkDelay);
    if (isClosed) return;
    isVerifying = false;

    if (code == DemoData.otpCode) {
      hasError = false;
      update();
      await SessionService.markSignedIn();
      Get.offNamed(Routes.welcomeBack);
    } else {
      hasError = true;
      secondsRemaining = 0;
      _timer?.cancel();
      update();
      update([countdownId]);
    }
  }

  @override
  void onClose() {
    _timer?.cancel();
    pinController.dispose();
    super.onClose();
  }
}
