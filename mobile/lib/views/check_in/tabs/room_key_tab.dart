import 'package:carlton/components/check_in/digital_key_button.dart';
import 'package:carlton/controllers/check_in/check_in_controller.dart';
import 'package:carlton/customWidgets/custom_containers.dart';
import 'package:carlton/customWidgets/custom_filled_button.dart';
import 'package:carlton/l10n/app_translations.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:flutter_svg/flutter_svg.dart';
import 'package:get/get.dart';

/// Room Key tab (Figma 75:928).
class RoomKeyTab extends GetView<CheckInController> {
  const RoomKeyTab({super.key});

  @override
  Widget build(BuildContext context) {
    return SingleChildScrollView(
      padding: const EdgeInsets.all(20),
      child: Obx(() {
        final reservation = controller.service.reservation.value;
        return Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          spacing: 20,
          children: [
            Center(
              child: CustomIconChip.circle(
                size: 60,
                backgroundColor: AppColors.primary08,
                child: SvgPicture.asset('assets/icons/chk_key.svg', width: 28),
              ),
            ),
            Text(
              AppTranslations.yourRoomKey,
              textAlign: TextAlign.center,
              style: Get.textTheme.headlineSmall?.copyWith(
                color: AppColors.primary,
              ),
            ),
            Text(
              AppTranslations.roomKeySubtitle,
              textAlign: TextAlign.center,
              style: Get.textTheme.bodyMedium?.copyWith(
                color: AppColors.taupeBrown,
              ),
            ),
            Card(
              color: AppColors.cream,
              elevation: 0,
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(10),
              ),
              child: Padding(
                padding: const EdgeInsets.all(15),
                child: Row(
                  spacing: 15,
                  children: [
                    CustomIconChip.circle(
                      size: 44,
                      backgroundColor: AppColors.antiqueGold,
                      child: SvgPicture.asset(
                        'assets/icons/bed.svg',
                        width: 20,
                      ),
                    ),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        spacing: 5,
                        children: [
                          Text(
                            reservation.suiteName,
                            style: Get.textTheme.titleSmall?.copyWith(
                              color: AppColors.primary,
                            ),
                          ),
                          Row(
                            spacing: 5,
                            crossAxisAlignment: CrossAxisAlignment.end,
                            children: [
                              Text(
                                reservation.roomNumber,
                                style: Get.textTheme.headlineMedium?.copyWith(
                                  color: AppColors.primary,
                                ),
                              ),
                              Padding(
                                padding: const EdgeInsets.only(bottom: 5),
                                child: Text(
                                  reservation.floorLabel,
                                  style: Get.textTheme.bodySmall?.copyWith(
                                    color: AppColors.taupeBrown,
                                  ),
                                ),
                              ),
                            ],
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
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
                  crossAxisAlignment: CrossAxisAlignment.start,
                  spacing: 5,
                  children: [
                    Text(
                      AppTranslations.keyCardWaitingTitle,
                      style: Get.textTheme.titleSmall?.copyWith(
                        color: AppColors.primary,
                      ),
                    ),
                    Text(
                      AppTranslations.keyCardWaitingBody,
                      style: Get.textTheme.bodySmall?.copyWith(
                        color: AppColors.taupeBrown,
                      ),
                    ),
                  ],
                ),
              ),
            ),
            DigitalKeyButton(
              status: controller.service.key.value,
              onPressed: controller.service.activateDigitalKey,
            ),
            Text(
              AppTranslations.digitalKeyHint,
              textAlign: TextAlign.center,
              style: Get.textTheme.bodySmall?.copyWith(
                color: AppColors.taupeBrown,
              ),
            ),
            CustomFilledButton(
              height: 50,
              onPressed: controller.completeAndExit,
              backgroundColor: AppColors.lagoonTeal,
              child: Text(AppTranslations.completeCheckIn),
            ),
          ],
        );
      }),
    );
  }
}
