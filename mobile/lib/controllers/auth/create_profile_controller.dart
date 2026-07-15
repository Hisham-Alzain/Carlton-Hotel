import 'package:carlton/constants/demo_data.dart';
import 'package:carlton/routes/routes.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';

/// Demo-only: no backend wired up. Field validation is handled by [formKey]
/// via the CustomTextField validators.
class CreateProfileController extends GetxController {
  final formKey = GlobalKey<FormState>();
  final firstNameController = TextEditingController();
  final lastNameController = TextEditingController();

  bool isSubmitting = false;

  Future<void> submit() async {
    if (!formKey.currentState!.validate()) return;

    isSubmitting = true;
    update();
    await Future.delayed(DemoData.networkDelay);
    isSubmitting = false;
    update();

    Get.toNamed(Routes.phoneEntry);
  }

  @override
  void onClose() {
    firstNameController.dispose();
    lastNameController.dispose();
    super.onClose();
  }
}
