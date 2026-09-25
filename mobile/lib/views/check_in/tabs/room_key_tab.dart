import 'package:carlton/components/check_in/digital_key_button.dart';
import 'package:carlton/controllers/check_in/check_in_controller.dart';
import 'package:carlton/customWidgets/custom_containers.dart';
import 'package:carlton/customWidgets/custom_filled_button.dart';
import 'package:carlton/l10n/app_translations.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:flutter_svg/flutter_svg.dart';
import 'package:get/get.dart';

/// Room Key tab — the "CheckInfinal" frame (Figma `2237:5032`).
///
/// Three stacked blocks separated by 25: the centred hero (glyph, title,
/// subtitle), the card group (room card, key-card panel, digital-key button and
/// its hint), and the Complete CTA.
class RoomKeyTab extends GetView<CheckInController> {
  const RoomKeyTab({super.key});

  @override
  Widget build(BuildContext context) {
    final textStyle = Get.textTheme;

    return SingleChildScrollView(
      padding: const EdgeInsets.all(20),
      child: Obx(() {
        final reservation = controller.service.reservation.value;
        return Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          spacing: 25,
          children: [
            Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.stretch,
              spacing: 15,
              children: [
                Center(
                  // Figma 98.6/50.6 — snapped to the 100/50 the rest of the app
                  // uses; the design's decimals are export scaling noise.
                  child: CustomIconChip.circle(
                    size: 100,
                    backgroundColor: AppColors.featherGrey,
                    child: SvgPicture.asset(
                      'assets/icons/chk_key.svg',
                      width: 50,
                      colorFilter: const ColorFilter.mode(
                        AppColors.inkBlack,
                        BlendMode.srcIn,
                      ),
                    ),
                  ),
                ),
                Text(
                  AppTranslations.yourRoomKey,
                  textAlign: TextAlign.center,
                  style: textStyle.headlineSmall?.copyWith(
                    color: AppColors.inkBlack,
                  ),
                ),
                Text(
                  AppTranslations.roomKeySubtitle,
                  textAlign: TextAlign.center,
                  style: textStyle.bodyMedium?.copyWith(
                    color: AppColors.mediumGrey,
                  ),
                ),
              ],
            ),
            Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.stretch,
              spacing: 15,
              children: [
                Card(
                  color: AppColors.linenCream,
                  elevation: 0,
                  margin: EdgeInsets.zero,
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(14),
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
                              Row(
                                children: [
                                  Expanded(
                                    child: Text(
                                      reservation.suiteName,
                                      style: textStyle.titleSmall?.copyWith(
                                        fontFamily: 'DM Sans',
                                        color: AppColors.inkBlack,
                                      ),
                                    ),
                                  ),
                                  // The stay dates sit opposite the suite name
                                  // on the same baseline row.
                                  Text(
                                    reservation.stayRangeLabel,
                                    style: textStyle.labelMedium?.copyWith(
                                      fontFamily: 'DM Sans',
                                      color: AppColors.inkBlack,
                                    ),
                                  ),
                                ],
                              ),
                              Row(
                                spacing: 5,
                                crossAxisAlignment: CrossAxisAlignment.end,
                                children: [
                                  Text(
                                    reservation.roomNumber,
                                    style: textStyle.headlineMedium?.copyWith(
                                      fontFamily: 'DM Sans',
                                      color: AppColors.inkBlack,
                                    ),
                                  ),
                                  Padding(
                                    // Nudges the floor label onto the room
                                    // number's baseline rather than its descender.
                                    padding: const EdgeInsets.only(bottom: 5),
                                    child: Text(
                                      reservation.floorLabel,
                                      style: textStyle.bodySmall?.copyWith(
                                        color: AppColors.inkBlack,
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
                // PillContainer rather than Card: this one needs a shadow as
                // well as a fill, and Card's elevation paints a Material
                // shadow that does not match the design's soft offset.
                //
                // Figma also gives it a pure-white 25% border, which is
                // invisible against a white fill on the ghostWhite scaffold —
                // the shadow is what actually separates it, so the border is
                // deliberately not reproduced.
                PillContainer(
                  backgroundColor: AppColors.white,
                  radius: 14,
                  padding: const EdgeInsets.all(15),
                  boxShadow: const [
                    BoxShadow(
                      color: AppColors.pebbleGrey32,
                      blurRadius: 4,
                      offset: Offset(0, 2),
                    ),
                  ],
                  child: Row(
                    spacing: 15,
                    children: [
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          spacing: 5,
                          children: [
                            Text(
                              AppTranslations.keyCardWaitingTitle,
                              style: textStyle.titleSmall?.copyWith(
                                color: AppColors.inkBlack,
                              ),
                            ),
                            Text(
                              AppTranslations.keyCardWaitingBody,
                              style: textStyle.bodySmall?.copyWith(
                                color: AppColors.taupeBrown,
                              ),
                            ),
                          ],
                        ),
                      ),
                      // Exported from Figma (`2237:5109`) rather than rebuilt in
                      // Dart: the artwork is a rotated key card composited over
                      // a photo with a gold tile pattern, so any hand-drawn
                      // version would be an approximation.
                      Image.asset(
                        'assets/images/key_card_art.png',
                        width: 53,
                        height: 65,
                        fit: BoxFit.contain,
                      ),
                    ],
                  ),
                ),
                Column(
                  mainAxisSize: MainAxisSize.min,
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  spacing: 10,
                  children: [
                    DigitalKeyButton(
                      status: controller.service.key.value,
                      onPressed: controller.service.activateDigitalKey,
                    ),
                    Text(
                      AppTranslations.digitalKeyHint,
                      textAlign: TextAlign.center,
                      style: textStyle.bodySmall?.copyWith(
                        color: AppColors.mediumGrey,
                      ),
                    ),
                  ],
                ),
              ],
            ),
            CustomFilledButton(
              height: 52,
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
