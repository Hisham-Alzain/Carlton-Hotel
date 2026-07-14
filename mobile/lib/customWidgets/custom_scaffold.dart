import 'dart:io';
import 'package:flutter/material.dart';

class CustomScaffold extends StatelessWidget {
  final PreferredSizeWidget? appBar;
  final Widget? body;
  final Widget? bottomNav;
  final bool extendBodyBehindAppBar;

  const CustomScaffold({
    required this.body,
    super.key,
    this.appBar,
    this.bottomNav,
    this.extendBodyBehindAppBar = false,
  });

  @override
  Widget build(BuildContext context) {
    return SafeArea(
      top: false,
      bottom: Platform.isIOS ? false : true,
      child: Scaffold(
        appBar: appBar,
        body: body,
        bottomNavigationBar: bottomNav,
        extendBodyBehindAppBar: extendBodyBehindAppBar,
      ),
    );
  }
}
