import 'package:camera/camera.dart';
import 'package:carlton/controllers/check_in/scan_id_controller.dart';
import 'package:carlton/customWidgets/custom_containers.dart';
import 'package:carlton/customWidgets/custom_scaffold.dart';
import 'package:carlton/l10n/app_translations.dart';
import 'package:carlton/models/check_in/check_in_enums.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:carlton/views/check_in/camera_unavailable_body.dart';
import 'package:carlton/views/check_in/captured_photo_review.dart';
import 'package:carlton/customWidgets/custom_indicators.dart';
import 'package:flutter/material.dart';
import 'package:flutter_svg/flutter_svg.dart';
import 'package:get/get.dart';

part 'scan_id_camera.dart';

/// One view, five stages (Figma 75:653, 75:703, 75:757 plus the two states a
/// real camera adds: warming up, and unusable).
///
/// The preview is live — [ScanIdController] owns the [CameraController] and
/// this file only renders whatever stage it reports.
class ScanIdView extends GetView<ScanIdController> {
  const ScanIdView({super.key});

  @override
  Widget build(BuildContext context) {
    return CustomScaffold(
      backgroundColor: AppColors.pearlCream,
      appBar: AppBar(
        backgroundColor: AppColors.pearlCream,
        elevation: 0,
        leading: const BackButton(color: AppColors.primary),
        title: Text(
          AppTranslations.scanYourId,
          style: Get.textTheme.titleLarge?.copyWith(color: AppColors.primary),
        ),
      ),
      body: Obx(() {
        final ScanStage stage = controller.stage.value;
        if (stage == ScanStage.success) return const CapturedPhotoReview();
        if (stage == ScanStage.unavailable) {
          return const CameraUnavailableBody();
        }

        return Column(
          children: [
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 20),
              child: Text(
                AppTranslations.positionIdInFrame,
                style: Get.textTheme.bodyMedium?.copyWith(
                  color: AppColors.taupeBrown,
                ),
              ),
            ),
            Expanded(
              child: _CameraCaptureArea(
                scanning: stage == ScanStage.scanning,
                initializing: stage == ScanStage.initializing,
              ),
            ),
            const _CameraControlsRow(),
          ],
        );
      }),
    );
  }
}
