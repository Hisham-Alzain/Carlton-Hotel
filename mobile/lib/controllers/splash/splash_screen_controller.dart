import 'dart:math';

import 'package:carlton/controllers/home/home_controller.dart';
import 'package:carlton/routes/routes.dart';
import 'package:carlton/services/middleware_service.dart';
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
      if (status == AnimationStatus.completed) _routeOnward();
    });
  }

  /// Cold start with a saved session: refresh entitlements, fetch the current
  /// reservation, and resolve the Home state *before* leaving the splash — so
  /// a returning guest never sees one Home variant swapped for another a
  /// moment later. Signed out, the guest picks a path first.
  ///
  /// This makes the splash wait on the network where it previously routed off
  /// the cached session. Both calls fail soft (a dead backend yields a null
  /// reservation, not an error), so the cost of an unreachable API is a slower
  /// splash, never a blocked one.
  Future<void> _routeOnward() async {
    if (!MiddlewareService.find.isAuthenticated) {
      await Get.offAllNamed(Routes.reservationChoice);
      return;
    }
    // /me first: has_booking and is_checked_in are refreshed from it, and the
    // stay dashboard Home loads next reads them.
    await MiddlewareService.find.checkToken();
    if (isClosed) return;
    // A saved-but-revoked token 401s here, and ErrorInterceptor has already
    // signed the guest out and replaced the stack with Sign In. Routing Home
    // now would stomp that redirect and land an unauthenticated guest on the
    // stay dashboard, so re-read the session rather than trusting the one we
    // checked before the await.
    if (!MiddlewareService.find.isAuthenticated) return;
    await HomeController.restoreAndGoHome();
  }

  @override
  void onClose() {
    animationController.dispose();
    super.onClose();
  }
}
