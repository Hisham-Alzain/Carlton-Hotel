import 'package:carlton/customWidgets/custom_scaffold.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:flutter_svg/flutter_svg.dart';
import 'package:get/get.dart';

const _backgroundImage = 'assets/images/backgroundimg2.png';
const _logoImage = 'assets/images/white_logo.svg';

class CustomAuthBackground extends StatelessWidget {
  final Widget child;
  final String? title;
  final String? subtitle;

  const CustomAuthBackground({
    required this.child,
    this.title,
    this.subtitle,
    super.key,
  });

  @override
  Widget build(BuildContext context) {
    final TextTheme textStyle = Get.textTheme;

    return Stack(
      fit: StackFit.expand,
      children: [
        // Fixed layer — lives outside the Scaffold, never resizes or shifts.
        Image.asset(_backgroundImage, fit: BoxFit.cover),
        const DecoratedBox(
          decoration: BoxDecoration(
            gradient: LinearGradient(
              begin: Alignment.topCenter,
              end: Alignment.bottomCenter,
              colors: [AppColors.duskScrim83, AppColors.nightScrim90],
            ),
          ),
        ),

        // Interactive layer — this Scaffold can resize freely for the
        // keyboard without ever touching the image/gradient above.
        CustomScaffold(
          backgroundColor: Colors.transparent,
          appBar: AppBar(
            // toolbarHeight: 10,
            backgroundColor: Colors.transparent,
          ),
          resizeToAvoidBottomInset: true,
          body: SingleChildScrollView(
            padding: const EdgeInsets.all(10),
            child: Column(
              spacing: 30,
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Column(
                  children: [
                    SvgPicture.asset(_logoImage),
                    Text(
                      'CARLTON',
                      style: textStyle.titleLarge?.copyWith(
                        fontFamily: 'The Seasons',
                        fontWeight: FontWeight.w700,
                        color: Colors.white,
                      ),
                    ),
                    Text(
                      'HOTEL',
                      style: textStyle.labelMedium?.copyWith(
                        fontFamily: 'Cabinet Grotesk',
                        fontWeight: FontWeight.w500,
                        color: Colors.white,
                      ),
                    ),
                  ],
                ),

                if (title != null)
                  Text(
                    title!,
                    style: textStyle.headlineMedium?.copyWith(
                      fontWeight: FontWeight.w500,
                      color: Colors.white,
                    ),
                  ),
                if (subtitle != null)
                  Text(
                    subtitle!,
                    textAlign: TextAlign.center,
                    style: textStyle.titleSmall?.copyWith(
                      fontFamily: 'DM Sans',
                      fontWeight: FontWeight.w400,
                      color: Colors.white,
                    ),
                  ),

                child,
              ],
            ),
          ),
        ),
      ],
    );
  }
}
