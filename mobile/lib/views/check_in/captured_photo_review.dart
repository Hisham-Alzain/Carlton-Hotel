import 'package:carlton/controllers/check_in/scan_id_controller.dart';
import 'package:carlton/customWidgets/custom_containers.dart';
import 'package:carlton/customWidgets/custom_filled_button.dart';
import 'package:carlton/l10n/app_translations.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:flutter_svg/flutter_svg.dart';
import 'package:get/get.dart';

/// Review screen for the just-captured photo (Figma 2237:4861): success badge,
/// the shot itself with a retake affordance, and Continue/Scan Again.
class CapturedPhotoReview extends GetView<ScanIdController> {
  const CapturedPhotoReview({super.key});

  @override
  Widget build(BuildContext context) {
    return SingleChildScrollView(
      padding: const EdgeInsets.all(20),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        spacing: 20,
        children: [
          Center(
            child: CustomIconChip.circle(
              size: 50,
              backgroundColor: AppColors.lagoonTeal,
              child: SvgPicture.asset(
                'assets/icons/check.svg',
                width: 24,
                height: 24,
                colorFilter: const ColorFilter.mode(
                  AppColors.white,
                  BlendMode.srcIn,
                ),
              ),
            ),
          ),
          Text(
            AppTranslations.scanSuccessful,
            textAlign: TextAlign.center,
            style: Get.textTheme.headlineSmall?.copyWith(
              color: AppColors.primary,
            ),
          ),
          Text(
            AppTranslations.capturedIdDetails,
            textAlign: TextAlign.center,
            style: Get.textTheme.bodyMedium?.copyWith(
              color: AppColors.taupeBrown,
            ),
          ),
          Card(
            color: Colors.white,
            elevation: 0,
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(10),
            ),
            child: Padding(
              padding: const EdgeInsets.all(15),
              child: Column(
                spacing: 10,
                children: [
                  Row(
                    children: [
                      Text(
                        AppTranslations.frontOfId,
                        style: Get.textTheme.titleSmall?.copyWith(
                          color: AppColors.primary,
                        ),
                      ),
                      const Spacer(),
                      // "Edit" is the retake affordance — the only edit there
                      // is to a captured photo.
                      InkWell(
                        onTap: controller.scanAgain,
                        child: Text(
                          AppTranslations.editLabel,
                          style: Get.textTheme.labelLarge?.copyWith(
                            color: AppColors.lagoonTeal,
                          ),
                        ),
                      ),
                    ],
                  ),
                  Obx(() {
                    final file = controller.capturedPhoto.value;
                    return ClipRRect(
                      borderRadius: BorderRadius.circular(10),
                      child: AspectRatio(
                        aspectRatio: 85.6 / 54,
                        child: file == null
                            ? const ColoredBox(color: AppColors.stoneTaupe)
                            : Image.file(file, fit: BoxFit.cover),
                      ),
                    );
                  }),
                  Text(
                    AppTranslations.makeSureDetailsClear,
                    style: Get.textTheme.bodySmall?.copyWith(
                      color: AppColors.taupeBrown,
                    ),
                  ),
                ],
              ),
            ),
          ),
          CustomFilledButton(
            height: 50,
            onPressed: controller.confirm,
            backgroundColor: AppColors.lagoonTeal,
            child: Text(AppTranslations.continueLabel),
          ),
          CustomFilledButton(
            height: 50,
            onPressed: controller.scanAgain,
            backgroundColor: AppColors.primary08,
            foregroundColor: AppColors.primary,
            child: Text(AppTranslations.scanAgain),
          ),
        ],
      ),
    );
  }
}
