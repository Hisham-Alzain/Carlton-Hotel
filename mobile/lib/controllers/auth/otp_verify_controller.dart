import 'package:carlton/l10n/app_translations.dart';
import 'dart:async';

import 'package:carlton/constants/error_codes.dart';
import 'package:carlton/customWidgets/custom_snackbar.dart';
import 'package:carlton/models/api/api_exception.dart';
import 'package:carlton/models/guest.dart';
import 'package:carlton/models/otp_verify_args.dart';
import 'package:carlton/routes/routes.dart';
import 'package:carlton/services/api/api_service.dart';
import 'package:carlton/services/middleware_service.dart';
import 'package:carlton/services/session_service.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';

/// Verifies the OTP against `POST /auth/guest/verify-otp`. On success it stores
/// the session ([MiddlewareService.saveSession]) and routes a named guest to
/// Welcome Back, or a nameless (new) guest to Create Profile.
class OtpVerifyController extends GetxController {
  static const _resendSeconds = 60;

  late final OtpVerifyArgs args;
  final formKey = GlobalKey<FormState>();
  final pinController = TextEditingController();

  /// The server's verdict on the last code (incorrect, expired), shown under
  /// the pin boxes and turning them red. Cleared as soon as the guest edits.
  final RxnString codeError = RxnString();
  final RxBool isVerifying = false.obs;

  /// Drives the resend-countdown label; ticks once a second.
  final RxInt secondsRemaining = _resendSeconds.obs;
  Timer? _timer;

  /// Contact string shown in the "code sent to …" subtitle.
  String get destination => args.display;

  @override
  void onInit() {
    super.onInit();
    args = Get.arguments is OtpVerifyArgs
        ? Get.arguments as OtpVerifyArgs
        : const OtpVerifyArgs(channel: 'sms', purpose: 'login', identifier: '');
    _startCountdown();
  }

  void _startCountdown() {
    _timer?.cancel();
    secondsRemaining.value = _resendSeconds;
    _timer = Timer.periodic(const Duration(seconds: 1), (timer) {
      secondsRemaining.value--;
      if (secondsRemaining.value <= 0) {
        secondsRemaining.value = 0;
        timer.cancel();
      }
    });
  }

  Future<void> resend() async {
    if (args.isBookingLink) {
      final pending = SessionService.pendingBookingLink;
      if (pending != null) {
        await ApiService.find.post<Map<String, dynamic>>(
          path: '/auth/guest/link-booking-code',
          data: {
            'booking_code': pending.bookingCode,
            if (pending.lastName != null) 'last_name': pending.lastName,
            if (pending.phone != null) 'phone': pending.phone,
          },
        );
      }
    } else {
      await ApiService.find.post<Map<String, dynamic>>(
        path: '/auth/guest/request-otp',
        data: {
          'channel': args.channel,
          if (args.isEmail)
            'email': args.identifier
          else
            'phone': args.identifier,
          'purpose': args.purpose,
        },
      );
    }
    pinController.clear();
    codeError.value = null;
    _startCountdown();
  }

  Future<void> verify() async {
    if (!formKey.currentState!.validate()) return;

    final code = pinController.text;

    isVerifying.value = true;
    codeError.value = null;

    final response = await ApiService.find.post<Map<String, dynamic>>(
      path: '/auth/guest/verify-otp',
      data: {
        'code': code,
        'purpose': args.purpose,
        // booking_link: the phone the guest typed on Find Booking (the code
        // was texted to it) plus the booking code, which links the stay to
        // the account. Login/register send phone or email.
        if (args.isBookingLink) ...{
          'phone': args.identifier,
          'booking_code': SessionService.pendingBookingLink?.bookingCode ?? '',
        } else if (args.isEmail)
          'email': args.identifier
        else
          'phone': args.identifier,
      },
      showErrorDialog: false,
    );
    if (isClosed) return;
    isVerifying.value = false;

    if (response.hasData) {
      final token = response.data!['token'] as String;
      final guest = Guest.fromJson(
        response.data!['guest'] as Map<String, dynamic>,
      );
      await MiddlewareService.find.saveSession(token: token, guest: guest);
      await SessionService.clearPendingBookingLink();
      // The verify-otp guest projection omits entitlements (has_booking /
      // is_checked_in default to false), so refresh from the authoritative
      // `/me` before landing — otherwise a guest who just linked a booking would
      // see the default Home instead of their reservation until the next launch.
      if (guest.hasName) await MiddlewareService.find.checkToken();
      if (isClosed) return;
      // A returning guest already has a name → greet; a new guest completes it.
      Get.offNamed(guest.hasName ? Routes.welcomeBack : Routes.createProfile);
      return;
    }

    _reportError(response.error);
  }

  /// Typing again clears the last verdict, so the red state never sits on a
  /// code the server has not seen yet.
  void onCodeChanged(String _) => codeError.value = null;

  /// A wrong or expired code is answered under the pin boxes, where the
  /// guest is looking — a pre-auth failure, not a session one. Anything else
  /// (offline, server error) is not about the code, so it gets the standard
  /// error dialog instead of a misleading "Incorrect code".
  void _reportError(ApiException? error) {
    switch (error?.errorCode) {
      case ErrorCodes.otpInvalid:
        codeError.value = AppTranslations.otpIncorrect;
      case ErrorCodes.otpExpired:
        pinController.clear();
        codeError.value = AppTranslations.otpExpired;
      case ErrorCodes.otpLocked:
        CustomSnackbars.showError(message: AppTranslations.otpTooManyAttempts);
        Get.back();
      default:
        if (error != null) ApiService.find.dialogs.showError(error);
    }
  }

  @override
  void onClose() {
    _timer?.cancel();
    pinController.dispose();
    super.onClose();
  }
}
