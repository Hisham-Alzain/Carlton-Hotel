import 'package:carlton/controllers/splash/splash_controller.dart';
import 'package:carlton/customWidgets/custom_scaffold.dart';
import 'package:flutter/material.dart';
import 'package:flutter_svg/flutter_svg.dart';
import 'package:get/get.dart';

class SplashView extends GetView<SplashController> {
  const SplashView({super.key});

  @override
  Widget build(BuildContext context) {
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
                radius: 1.1,
                colors: [
                  controller.gradientCenterAnimation.value!,
                  controller.gradientEdgeAnimation.value!,
                ],
              ),
            ),
            child: Stack(
              children: [
                Center(
                  child: Opacity(
                    opacity: controller.opacityAnimation.value,
                    child: Transform.translate(
                      offset: controller.positionAnimation.value,
                      child: Transform.scale(
                        scale: controller.scaleAnimation.value,
                        child: Image.asset(
                          'assets/images/splashscreen.png',
                          // Figma's logo frame is ~152/403 of the frame width.
                          width: MediaQuery.sizeOf(context).width * 0.38,
                        ),
                      ),
                    ),
                  ),
                ),
                Align(
                  alignment: const Alignment(0, 0.82),
                  child: Opacity(
                    opacity: controller.bottomMarkOpacityAnimation.value,
                    child: SvgPicture.asset(
                      'assets/icons/logo.svg',
                      width: MediaQuery.sizeOf(context).width * 0.075,
                      colorFilter: const ColorFilter.mode(
                        Colors.white,
                        BlendMode.srcIn,
                      ),
                    ),
                  ),
                ),
              ],
            ),
          ),
        );
      },
    );
  }
}
