import 'package:carlton/controllers/splash/splash_screen_controller.dart';
import 'package:carlton/customWidgets/custom_scaffold.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';

class SplashScreenView extends GetView<SplashScreenController> {
  const SplashScreenView({super.key});

  @override
  Widget build(BuildContext context) {
    final TextTheme textStyle = Get.textTheme;

    return AnimatedBuilder(
      animation: controller.animationController,
      builder: (context, child) {
        return CustomScaffold(
          body: Container(
            width: double.infinity,
            height: double.infinity,
            decoration: BoxDecoration(
              gradient: RadialGradient(
                center: const Alignment(0, -0.05),
                radius: 1.5,
                colors: [
                  controller.gradientCenterAnimation.value!,
                  controller.gradientEdgeAnimation.value!,
                ],
              ),
            ),
            child: Center(
              child: Column(
                spacing: 10,
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  Opacity(
                    opacity: controller.opacityAnimation.value,
                    child: Transform.rotate(
                      angle: controller.rotationAnimation.value,
                      child: Transform.scale(
                        scale: controller.scaleAnimation.value,
                        child: Image.asset(
                          'assets/images/splash_screen_logo.png',
                        ),
                      ),
                    ),
                  ),
                  Text(
                    'CARLTON',
                    style: textStyle.displaySmall?.copyWith(
                      fontFamily: 'The Seasons',
                      fontWeight: FontWeight.w700,
                      foreground: Paint()
                        ..shader = const LinearGradient(
                          colors: [Colors.white, AppColors.mistTeal],
                        ).createShader(const Rect.fromLTWH(0, 0, 200, 70)),
                    ),
                  ),
                  Text(
                    'HOTEL',
                    style: textStyle.labelLarge?.copyWith(
                      fontFamily: 'Cabinet Grotesk',
                      fontWeight: FontWeight.w500,
                      foreground: Paint()
                        ..shader = const LinearGradient(
                          colors: [Colors.white, AppColors.mistTeal],
                        ).createShader(const Rect.fromLTWH(0, 0, 200, 70)),
                    ),
                  ),
                ],
              ),
            ),
          ),
        );
      },
    );
  }
}
