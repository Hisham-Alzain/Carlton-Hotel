import 'package:carlton/controllers/check_in/scan_id_controller.dart';
import 'package:carlton/components/check_in/scan_status_header.dart';
import 'package:carlton/customWidgets/custom_filled_button.dart';
import 'package:carlton/l10n/app_translations.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';

/// Terminal failure: permission refused, no camera, or a plugin error. Upload
/// stays reachable so a denied camera never dead-ends the check-in.
class CameraUnavailableBody extends GetView<ScanIdController> {
  const CameraUnavailableBody({super.key});

  @override
  Widget build(BuildContext context) {
    return SingleChildScrollView(
      padding: const EdgeInsets.all(20),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        spacing: 20,
        children: [
          ScanStatusHeader(
            icon: Icons.no_photography_outlined,
            iconColor: AppColors.primary,
            chipColor: AppColors.primary08,
            title: AppTranslations.cameraUnavailable,
          ),
          Obx(
            () => Text(
              controller.errorMessage.value,
              textAlign: TextAlign.center,
              style: Get.textTheme.bodyMedium?.copyWith(
                color: AppColors.taupeBrown,
              ),
            ),
          ),
          Obx(
            () => CustomFilledButton(
              height: 50,
              onPressed: controller.isBlockedByPermission.value
                  ? controller.openSettings
                  : controller.startCamera,
              backgroundColor: AppColors.lagoonTeal,
              child: Text(
                controller.isBlockedByPermission.value
                    ? AppTranslations.openSettings
                    : AppTranslations.tryAgain,
              ),
            ),
          ),
          CustomFilledButton(
            height: 50,
            onPressed: controller.openUpload,
            backgroundColor: AppColors.primary08,
            foregroundColor: AppColors.primary,
            child: Text(AppTranslations.uploadLabel),
          ),
        ],
      ),
    );
  }
}
