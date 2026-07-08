import 'package:carlton/controllers/controller.dart';
import 'package:carlton/customWidgets/custom_dialogs.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';

class View extends GetView<Controller> {
  const View({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(),
      body: GetBuilder<Controller>(
        builder: (controller) => Center(
          child: Column(
            spacing: 10,
            mainAxisAlignment: MainAxisAlignment.center,
            crossAxisAlignment: CrossAxisAlignment.center,
            children: [
              Text('Welcome to flutter base code'),
              FilledButton(
                onPressed: () => CustomDialogs.showSuccessDialog(),
                child: Text('Test'),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
