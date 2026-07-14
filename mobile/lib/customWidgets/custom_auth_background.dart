import 'package:carlton/customWidgets/custom_scaffold.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:flutter_svg/flutter_svg.dart';
import 'package:get/get.dart';

const _kBackgroundImage = 'assets/images/backgroundimg2.png';
const _kLogoImage = 'assets/images/CarltonLogo.svg';

class CustomAuthBackground extends StatelessWidget {
  final Widget child;
  final bool showBackButton;
  final VoidCallback? onBack;
  final String? title;
  final String? subtitle;
  final double logoWidth;
  final double topPadding;

  const CustomAuthBackground({
    required this.child,
    this.showBackButton = false,
    this.onBack,
    this.title,
    this.subtitle,
    this.logoWidth = 170,
    this.topPadding = 44,
    super.key,
  });

  @override
  Widget build(BuildContext context) {
    final headerBar = title == null
        ? null
        : _AuthHeaderBar(
            title: title!,
            subtitle: subtitle,
            logoWidth: logoWidth,
            topPadding: topPadding,
            showBackButton: showBackButton,
            onBack: onBack,
          );

    return CustomScaffold(
      extendBodyBehindAppBar: headerBar != null,
      appBar: headerBar,
      body: Stack(
        fit: StackFit.expand,
        children: [
          Image.asset(_kBackgroundImage, fit: BoxFit.cover),
          const DecoratedBox(
            decoration: BoxDecoration(
              gradient: LinearGradient(
                begin: Alignment.topCenter,
                end: Alignment.bottomCenter,
                colors: [AppColors.scrimTop, AppColors.scrimBottom],
              ),
            ),
          ),
          if (headerBar != null)
            Padding(
              padding: EdgeInsets.only(
                top:
                    MediaQuery.of(context).padding.top +
                    headerBar.preferredSize.height,
              ),
              child: child,
            )
          else
            SafeArea(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  if (showBackButton) _AuthBackButton(onBack: onBack),
                  Expanded(child: child),
                ],
              ),
            ),
        ],
      ),
    );
  }
}

class _AuthBackButton extends StatelessWidget {
  final VoidCallback? onBack;

  const _AuthBackButton({this.onBack});

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(23, 18, 23, 0),
      child: GestureDetector(
        onTap: onBack ?? Get.back,
        child: const Icon(
          Icons.arrow_back_ios_new,
          color: Colors.white,
          size: 20,
        ),
      ),
    );
  }
}

class _AuthHeaderBar extends StatelessWidget implements PreferredSizeWidget {
  static const _backButtonBlockHeight = 38.0;
  static const _logoToTitleGap = 26.0;
  static const _titleLineHeight = 34.0;
  static const _subtitleBlockHeight = 50.0;
  static const _subtitleTopGap = 6.0;
  static const _subtitleHorizontalPadding = 23.0;
  static const _bottomBuffer = 16.0;

  final String title;
  final String? subtitle;
  final double logoWidth;
  final double topPadding;
  final bool showBackButton;
  final VoidCallback? onBack;

  const _AuthHeaderBar({
    required this.title,
    required this.logoWidth,
    required this.topPadding,
    required this.showBackButton,
    this.subtitle,
    this.onBack,
  });

  // CarltonLogo.svg has a 130x91 viewBox — derive rendered height from width.
  double get _logoHeight => logoWidth * 91 / 130;

  @override
  Size get preferredSize => Size.fromHeight(
    (showBackButton ? _backButtonBlockHeight : 0) +
        topPadding +
        _logoHeight +
        _logoToTitleGap +
        _titleLineHeight +
        (subtitle != null ? _subtitleBlockHeight : 0) +
        _bottomBuffer,
  );

  @override
  Widget build(BuildContext context) {
    return MediaQuery(
      data: MediaQuery.of(
        context,
      ).copyWith(textScaler: const TextScaler.linear(1.0)),
      child: AppBar(
        backgroundColor: Colors.transparent,
        elevation: 0,
        automaticallyImplyLeading: false,
        toolbarHeight: preferredSize.height,
        titleSpacing: 0,
        title: Column(
          mainAxisAlignment: MainAxisAlignment.start,
          children: [
            if (showBackButton)
              Align(
                alignment: Alignment.topLeft,
                child: _AuthBackButton(onBack: onBack),
              ),
            Padding(
              padding: EdgeInsets.only(
                top: topPadding,
                bottom: _logoToTitleGap,
              ),
              child: SvgPicture.asset(_kLogoImage, width: logoWidth),
            ),
            Text(
              title,
              textAlign: TextAlign.center,
              style: const TextStyle(
                fontSize: 26,
                fontWeight: FontWeight.w500,
                color: Colors.white,
              ),
            ),
            if (subtitle != null)
              Padding(
                padding: const EdgeInsets.only(
                  top: _subtitleTopGap,
                  left: _subtitleHorizontalPadding,
                  right: _subtitleHorizontalPadding,
                ),
                child: Text(
                  subtitle!,
                  textAlign: TextAlign.center,
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    fontSize: 14,
                    color: AppColors.textOnDark,
                  ),
                ),
              ),
          ],
        ),
      ),
    );
  }
}
