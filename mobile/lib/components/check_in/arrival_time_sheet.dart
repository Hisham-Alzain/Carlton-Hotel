import 'package:carlton/theme/theme.dart';
import 'package:carlton/customWidgets/custom_bottom_sheet.dart';
import 'package:carlton/customWidgets/custom_filled_button.dart';
import 'package:carlton/l10n/app_translations.dart';
import 'package:carlton/models/check_in/arrival_slot.dart';
import 'package:carlton/services/check_in_service.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';

/// Body of the arrival-time sheet: the offered hours grouped under their
/// period headings. The Home checklist offers "Set arrival time · Tap to add
/// your ETA" but the Figma file contains no screen for it, so this is the
/// smallest thing that makes the row functional.
///
/// Content only — the title, subtitle and Confirm button belong to
/// [showArrivalSlotPicker]'s call to [CustomBottomSheet.show].
class ArrivalTimeSheet extends StatelessWidget {
  const ArrivalTimeSheet({
    required this.hours,
    required this.draft,
    this.openEndedHour,
    super.key,
  });

  /// The 24-hour clock hours offered, one chip each.
  final List<int> hours;

  /// The hour that stands for "this time or later" ("After 10:00 PM").
  final int? openEndedHour;

  /// Uncommitted selection, owned by [showArrivalSlotPicker].
  final Rx<int?> draft;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      spacing: 20,
      children: [
        for (final period in ArrivalPeriod.values)
          if (hours.any((hour) => ArrivalPeriod.of(hour) == period))
            _PeriodGroup(
              period: period,
              hours: hours
                  .where((hour) => ArrivalPeriod.of(hour) == period)
                  .toList(),
              openEndedHour: openEndedHour,
              draft: draft,
            ),
      ],
    );
  }
}

/// One heading plus the chips belonging to it.
class _PeriodGroup extends StatelessWidget {
  const _PeriodGroup({
    required this.period,
    required this.hours,
    required this.openEndedHour,
    required this.draft,
  });

  final ArrivalPeriod period;
  final List<int> hours;
  final int? openEndedHour;
  final Rx<int?> draft;

  @override
  Widget build(BuildContext context) {
    final textStyle = Get.textTheme;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      spacing: 10,
      children: [
        Text(
          _headingFor(period),
          style: textStyle.dmLabelMedium?.copyWith(
            color: AppColors.taupeBrown,
            letterSpacing: 0.5,
          ),
        ),
        Wrap(
          spacing: 10,
          runSpacing: 10,
          children: hours
              .map(
                (hour) => _SlotChip(
                  hour: hour,
                  isOpenEnded: hour == openEndedHour,
                  draft: draft,
                ),
              )
              .toList(),
        ),
      ],
    );
  }

  /// Lives here rather than on [ArrivalPeriod] because the models in
  /// `models/check_in/` are plain enums that never reach into `l10n/`.
  String _headingFor(ArrivalPeriod period) => switch (period) {
    ArrivalPeriod.earlyHours => AppTranslations.arrivalEarlyHours,
    ArrivalPeriod.morning => AppTranslations.arrivalMorning,
    ArrivalPeriod.afternoon => AppTranslations.arrivalAfternoon,
    ArrivalPeriod.evening => AppTranslations.arrivalEvening,
    ArrivalPeriod.lateNight => AppTranslations.arrivalLateNight,
  };
}

/// A single selectable hour.
class _SlotChip extends StatelessWidget {
  const _SlotChip({
    required this.hour,
    required this.isOpenEnded,
    required this.draft,
  });

  final int hour;
  final bool isOpenEnded;
  final Rx<int?> draft;

  @override
  Widget build(BuildContext context) {
    final textStyle = Get.textTheme;

    // TimeOfDay.format resolves through MaterialLocalizations, so the label
    // picks up the locale's numerals and its 12/24-hour convention for free.
    final time = TimeOfDay(hour: hour, minute: 0).format(context);
    final label = isOpenEnded ? AppTranslations.arrivalAfterTime(time) : time;

    // Scoped to this chip so selecting an hour repaints two chips, not all of
    // them.
    return Obx(() {
      final selected = draft.value == hour;

      return ChoiceChip(
        label: Text(label),
        selected: selected,
        onSelected: (_) => draft.value = hour,
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

/// Opens the sheet for check-in. Home's checklist row calls this.
Future<void> showArrivalTimeSheet() => showArrivalSlotPicker(
  hours: ArrivalSlot.values.map((slot) => slot.hour).toList(),
  openEndedHour: ArrivalSlot.afterTenPm.hour,
  initialHour: CheckInService.find.arrivalSlot.value?.hour,
  onConfirm: (hour) =>
      CheckInService.find.submitArrivalTime(ArrivalSlot.fromHour(hour)),
);

/// The arrival-hour picker itself, shared by check-in and the airport
/// transfer's Arrival Time so both ask the same question the same way. Each
/// passes the [hours] it accepts; [onConfirm] receives the hour only when the
/// guest taps Confirm.
Future<void> showArrivalSlotPicker({
  required List<int> hours,
  required ValueChanged<int> onConfirm,
  int? initialHour,
  int? openEndedHour,
  String? title,
  String? subtitle,
}) {
  // Draft rather than writing straight through: a mistap on a chip is undone
  // by picking another, and nothing is committed until Confirm.
  final draft = Rx<int?>(hours.contains(initialHour) ? initialHour : null);

  return CustomBottomSheet.show<void>(
    title: title ?? AppTranslations.selectArrivalTime,
    subtitle: subtitle ?? AppTranslations.arrivalTimeSubtitle,
    heightFactor: 0.6,
    child: ArrivalTimeSheet(
      hours: hours,
      openEndedHour: openEndedHour,
      draft: draft,
    ),
    actions: Obx(
      () => CustomFilledButton(
        width: double.infinity,
        // Disabled until something is picked — the sheet opens with no
        // selection the first time it is used.
        onPressed: draft.value == null
            ? null
            : () {
                onConfirm(draft.value!);
                Get.back();
              },
        child: Text(AppTranslations.confirmArrivalTime),
      ),
    ),
  );
}
