import 'dart:io';
import 'package:flutter/material.dart';

class CustomScaffold extends StatelessWidget {
  final PreferredSizeWidget? appBar;
  final Widget? body;

  const CustomScaffold({required this.body, super.key, this.appBar});

  @override
  Widget build(BuildContext context) {
    return SafeArea(
      top: false,
      bottom: Platform.isIOS ? false : true,
      child: Scaffold(appBar: appBar, body: body),
    );
  }
}
