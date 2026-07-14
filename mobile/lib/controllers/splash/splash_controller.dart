import 'package:carlton/routes/routes.dart';
import 'package:carlton/services/session_service.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';

class SplashController extends GetxController with GetTickerProviderStateMixin {
  static const Color _nativeSplashColor = Color(0xFF14454C);

  late final AnimationController animationController;
  late final Animation<Offset> positionAnimation;
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
      duration: const Duration(milliseconds: 2000),
    );

    positionAnimation =
        Tween<Offset>(begin: const Offset(-200, 0), end: Offset.zero).animate(
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
      end: AppColors.tealGlow,
    ).animate(backgroundCurve);
    gradientEdgeAnimation = ColorTween(
      begin: _nativeSplashColor,
      end: AppColors.tealDeep,
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
