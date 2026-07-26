import 'dart:math';

import 'package:carlton/routes/routes.dart';
import 'package:carlton/services/session_service.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';

class SplashScreenController extends GetxController
    with GetTickerProviderStateMixin {
  static const Color _nativeSplashColor = AppColors.midnightTeal;

  late final AnimationController animationController;
  late final Animation<double> rotationAnimation;
  late final Animation<double> scaleAnimation;
  late final Animation<double> opacityAnimation;
  late final Animation<Color?> gradientCenterAnimation;
  late final Animation<Color?> gradientEdgeAnimation;
  late final Animation<double> bottomMarkOpacityAnimation;

  @override
  void onInit() {
    super.onInit();

    animationController = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 2500),
    );

    // Spins from -1 full turn (radians) down to rest at 0, same timing
    // window the old slide-in used.
    rotationAnimation = Tween<double>(begin: -2 * pi, end: 0.0).animate(
      CurvedAnimation(
        parent: animationController,
        curve: const Interval(0.0, 0.45, curve: Curves.easeOutCubic),
      ),
    );

    scaleAnimation = Tween<double>(begin: 0.85, end: 1.0).animate(
      CurvedAnimation(
        parent: animationController,
        curve: const Interval(0.35, 0.75, curve: Curves.elasticOut),
      ),
    );

    opacityAnimation = Tween<double>(begin: 0.0, end: 1.0).animate(
      CurvedAnimation(
        parent: animationController,
        curve: const Interval(0.0, 0.35, curve: Curves.easeIn),
      ),
    );

    final backgroundCurve = CurvedAnimation(
      parent: animationController,
      curve: const Interval(0.5, 1.0, curve: Curves.easeIn),
    );
    gradientCenterAnimation = ColorTween(
      begin: _nativeSplashColor,
      end: AppColors.oceanTeal,
    ).animate(backgroundCurve);
    gradientEdgeAnimation = ColorTween(
      begin: _nativeSplashColor,
      end: AppColors.abyssTeal,
    ).animate(backgroundCurve);

    bottomMarkOpacityAnimation = Tween<double>(begin: 0.0, end: 1.0).animate(
      CurvedAnimation(
        parent: animationController,
        curve: const Interval(0.6, 1.0, curve: Curves.easeIn),
      ),
    );

    animationController.forward();

    animationController.addStatusListener((status) {
      if (status == AnimationStatus.completed) {
        final skipsReservationQuestion =
            SessionService.isSignedIn || SessionService.hasReservation;
        Get.offAllNamed(
          skipsReservationQuestion ? Routes.main : Routes.reservationChoice,
        );
      }
    });
  }

  @override
  void onClose() {
    animationController.dispose();
    super.onClose();
  }
}
