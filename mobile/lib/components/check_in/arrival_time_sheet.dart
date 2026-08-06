import 'package:carlton/customWidgets/custom_bottom_sheet.dart';
import 'package:carlton/l10n/app_translations.dart';
import 'package:carlton/services/check_in_service.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';

/// The Home checklist offers "Set arrival time · Tap to add your ETA" but the
/// Figma file contains no screen for it. A slot picker is the smallest thing
/// that makes the row functional.
class ArrivalTimeSheet extends StatelessWidget {
  const ArrivalTimeSheet({super.key});

  static const _slots = <String>[
    '12:00 PM',
    '1:00 PM',
    '2:00 PM',
    '3:00 PM',
    '4:00 PM',
    '6:00 PM',
    '8:00 PM',
    'After 10:00 PM',
  ];

  @override
  Widget build(BuildContext context) {
    return CustomBottomSheet(
      title: AppTranslations.selectArrivalTime,
      heightFactor: 0.6,
      child: Obx(() {
        final selected = CheckInService.find.arrivalTimeLabel.value;
        return Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          spacing: 10,
          children: _slots.map((slot) {
            final active = slot == selected;
            return InkWell(
              onTap: () => CheckInService.find.markArrivalTime(slot),
              child: Card(
                color: active ? AppColors.primary08 : AppColors.primary06,
                elevation: 0,
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(10),
                  side: BorderSide(
                    color: active ? AppColors.lagoonTeal : AppColors.primary00,
                  ),
                ),
                child: Padding(
                  padding: const EdgeInsets.all(15),
                  child: Text(
                    slot,
                    style: Get.textTheme.titleSmall?.copyWith(
                      color: AppColors.primary,
                    ),
                  ),
                ),
              ),
            );
          }).toList(),
        );
      }),
    );
  }
}

/// Opens the sheet. Home's checklist row calls this.
Future<void> showArrivalTimeSheet() =>
    Get.bottomSheet<void>(const ArrivalTimeSheet(), isScrollControlled: true);
