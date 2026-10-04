import 'package:carlton/theme/theme.dart';
import 'package:carlton/controllers/services/airport_transfer_controller.dart';
import 'package:carlton/customWidgets/custom_bottom_sheet.dart';
import 'package:carlton/customWidgets/custom_filled_button.dart';
import 'package:carlton/customWidgets/custom_indicators.dart';
import 'package:carlton/customWidgets/custom_snackbar.dart';
import 'package:carlton/customWidgets/custom_text_field.dart';
import 'package:carlton/customWidgets/custom_texts.dart';
import 'package:carlton/extensions/date_extension.dart';
import 'package:carlton/extensions/price_extension.dart';
import 'package:carlton/l10n/app_translations.dart';
import 'package:carlton/models/transfer.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:flutter_svg/flutter_svg.dart';
import 'package:get/get.dart';

part 'airport_transfer/flight_details_step.dart';
part 'airport_transfer/select_vehicle_step.dart';
part 'airport_transfer/confirm_step.dart';
part 'airport_transfer/transfer_shared.dart';

/// The three-step airport-transfer flow (Figma "AirportTransferSheet",
/// `2248:490` flight details → `2248:1327` select vehicle → `2248:908`
/// confirm), presented as one sheet whose body swaps per step.
///
/// One sheet rather than three stacked ones: the design keeps the same header
/// and progress strip across all three, and popping/pushing sheets would
/// re-run the entrance animation on every Back.
Future<void> showAirportTransferSheet() async {
  final controller = Get.find<AirportTransferController>();
  final reason = controller.notOpenReason;
  if (reason != null) {
    CustomSnackbars.showInfo(message: reason);
    return;
  }
  controller.goTo(0);
  return CustomBottomSheet.show<void>(
    // The header is drawn in the body so the progress strip can sit directly
    // under the title, which the shell's own header does not support.
    showClose: false,
    child: const AirportTransferSheet(),
  );
}

class AirportTransferSheet extends GetView<AirportTransferController> {
  const AirportTransferSheet({super.key});

  @override
  Widget build(BuildContext context) {
    // Tapping anywhere outside a field closes the keyboard, which otherwise
    // covers the step's buttons.
    return GestureDetector(
      behavior: HitTestBehavior.translucent,
      onTap: () => FocusManager.instance.primaryFocus?.unfocus(),
      child: Obx(
        () => Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          spacing: 14,
          children: [
            const _Header(),
            _StepProgress(step: controller.step.value),
            switch (controller.step.value) {
              0 => const _FlightDetailsStep(),
              1 => const _SelectVehicleStep(),
              _ => const _ConfirmStep(),
            },
          ],
        ),
      ),
    );
  }
}

/// Car artwork in a soft teal tile, title + route, and the grey close button.
class _Header extends StatelessWidget {
  const _Header();

  @override
  Widget build(BuildContext context) {
    final textStyle = Get.textTheme;
    return Row(
      spacing: 10,
      children: [
        Container(
          width: 38,
          height: 38,
          alignment: Alignment.center,
          decoration: BoxDecoration(
            color: AppColors.primary06,
            borderRadius: BorderRadius.circular(10),
          ),
          child: Image.asset(
            'assets/images/transfer_header.png',
            width: 36,
            height: 30,
            fit: BoxFit.cover,
          ),
        ),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                AppTranslations.transferTitle,
                style: textStyle.titleMedium?.copyWith(
                  fontSize: 16,
                  fontWeight: FontWeight.w600,
                  color: AppColors.inkBlack,
                ),
              ),
              Text(
                AppTranslations.transferRoute,
                style: textStyle.dmBodySmall?.copyWith(
                  fontSize: 12,
                  color: AppColors.steelGrey,
                ),
              ),
            ],
          ),
        ),
        Material(
          color: AppColors.whisperGrey,
          borderRadius: BorderRadius.circular(8),
          child: InkWell(
            onTap: Get.back,
            borderRadius: BorderRadius.circular(8),
            child: Padding(
              padding: const EdgeInsets.all(7),
              child: Icon(Icons.close, size: 17, color: AppColors.taupeBrown),
            ),
          ),
        ),
      ],
    );
  }
}

/// Three bare segments that fill as the guest advances.
class _StepProgress extends StatelessWidget {
  final int step;

  const _StepProgress({required this.step});

  @override
  Widget build(BuildContext context) {
    return Row(
      spacing: 6,
      children: List<Widget>.generate(
        3,
        (i) => Expanded(
          child: Container(
            height: 3,
            decoration: BoxDecoration(
              color: i <= step ? AppColors.lagoonTeal : AppColors.pearlSilver,
              borderRadius: BorderRadius.circular(2),
            ),
          ),
        ),
      ),
    );
  }
}
