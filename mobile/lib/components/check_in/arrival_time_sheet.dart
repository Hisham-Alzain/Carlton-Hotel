import 'package:carlton/customWidgets/custom_bottom_sheet.dart';
import 'package:carlton/customWidgets/custom_filled_button.dart';
import 'package:carlton/l10n/app_translations.dart';
import 'package:carlton/models/check_in/arrival_slot.dart';
import 'package:carlton/services/check_in_service.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';

/// Body of the arrival-time sheet: the eight ETA slots grouped under their
/// period headings. The Home checklist offers "Set arrival time · Tap to add
/// your ETA" but the Figma file contains no screen for it, so this is the
/// smallest thing that makes the row functional.
///
/// Content only — the title, subtitle and Confirm button belong to
/// [showArrivalTimeSheet]'s call to [CustomBottomSheet.show].
class ArrivalTimeSheet extends StatelessWidget {
  const ArrivalTimeSheet({required this.draft, super.key});

  /// Uncommitted selection, owned by [showArrivalTimeSheet].
  final Rx<ArrivalSlot?> draft;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      spacing: 20,
      children: ArrivalPeriod.values
          .map((period) => _PeriodGroup(period: period, draft: draft))
          .toList(),
    );
  }
}

/// One heading plus the chips belonging to it.
class _PeriodGroup extends StatelessWidget {
  const _PeriodGroup({required this.period, required this.draft});

  final ArrivalPeriod period;
  final Rx<ArrivalSlot?> draft;

  @override
  Widget build(BuildContext context) {
    final textStyle = Get.textTheme;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      spacing: 10,
      children: [
        Text(
          _headingFor(period),
          style: textStyle.labelMedium?.copyWith(
            fontFamily: 'DM Sans',
            color: AppColors.taupeBrown,
            letterSpacing: 0.5,
          ),
        ),
        Wrap(
          spacing: 10,
          runSpacing: 10,
          children: ArrivalSlot.values
              .where((slot) => slot.period == period)
              .map((slot) => _SlotChip(slot: slot, draft: draft))
              .toList(),
        ),
      ],
    );
  }

  /// Lives here rather than on [ArrivalPeriod] because the models in
  /// `models/check_in/` are plain enums that never reach into `l10n/`.
  String _headingFor(ArrivalPeriod period) => switch (period) {
    ArrivalPeriod.afternoon => AppTranslations.arrivalAfternoon,
    ArrivalPeriod.evening => AppTranslations.arrivalEvening,
    ArrivalPeriod.lateNight => AppTranslations.arrivalLateNight,
  };
}

/// A single selectable slot.
class _SlotChip extends StatelessWidget {
  const _SlotChip({required this.slot, required this.draft});

  final ArrivalSlot slot;
  final Rx<ArrivalSlot?> draft;

  @override
  Widget build(BuildContext context) {
    final textStyle = Get.textTheme;

    // TimeOfDay.format resolves through MaterialLocalizations, so the label
    // picks up the locale's numerals and its 12/24-hour convention for free.
    final time = TimeOfDay(hour: slot.hour, minute: 0).format(context);
    final label = slot.isOpenEnded
        ? AppTranslations.arrivalAfterTime(time)
        : time;

    // Scoped to this chip so selecting a slot repaints two chips, not all
    // eight.
    return Obx(() {
      final selected = draft.value == slot;

      return ChoiceChip(
        label: Text(label),
        selected: selected,
        onSelected: (_) => draft.value = slot,
        // chipTheme suppresses the checkmark for the read-only filter chips it
        // was written for; here it is the selection affordance.
        showCheckmark: true,
        checkmarkColor: AppColors.white,
        // chipTheme's label is oliveTaupe, which vanishes against the selected
        // chip's solid primary fill.
        labelStyle: textStyle.labelSmall?.copyWith(
          color: selected ? AppColors.white : AppColors.primary,
          fontWeight: selected ? FontWeight.w600 : FontWeight.w400,
        ),
        labelPadding: const EdgeInsetsDirectional.only(start: 5),
        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 10),
        // chipTheme's border is white — sized for the tinted surfaces its
        // chips sit on, and invisible on this sheet's white background.
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(12),
          side: BorderSide(
            color: selected ? AppColors.primary : AppColors.linenGrey,
          ),
        ),
      );
    });
  }
}

/// Opens the sheet. Home's checklist row calls this.
Future<void> showArrivalTimeSheet() {
  // Draft rather than writing straight through to CheckInService: a mistap on
  // a chip is undone by picking another, and nothing is committed until
  // Confirm. Scoped to this call so the service never holds provisional UI
  // state.
  final draft = Rx<ArrivalSlot?>(CheckInService.find.arrivalSlot.value);

  return CustomBottomSheet.show<void>(
    title: AppTranslations.selectArrivalTime,
    subtitle: AppTranslations.arrivalTimeSubtitle,
    heightFactor: 0.6,
    child: ArrivalTimeSheet(draft: draft),
    actions: Obx(
      () => CustomFilledButton(
        width: double.infinity,
        // Disabled until something is picked — the sheet opens with no
        // selection the first time it is used.
        onPressed: draft.value == null
            ? null
            : () {
                CheckInService.find.markArrivalTime(draft.value!);
                Get.back();
              },
        child: Text(AppTranslations.confirmArrivalTime),
      ),
    ),
  );
}
