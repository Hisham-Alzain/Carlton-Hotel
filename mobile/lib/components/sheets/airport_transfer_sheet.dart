import 'package:carlton/components/custom_counter_field.dart';
import 'package:carlton/components/custom_info_banner.dart';
import 'package:carlton/components/custom_price_summary.dart';
import 'package:carlton/components/custom_selectable_card.dart';
import 'package:carlton/controllers/services/airport_transfer_controller.dart';
import 'package:carlton/customWidgets/custom_bottom_sheet.dart';
import 'package:carlton/customWidgets/custom_filled_button.dart';
import 'package:carlton/customWidgets/custom_image.dart';
import 'package:carlton/customWidgets/custom_indicators.dart';
import 'package:carlton/customWidgets/custom_outlined_button.dart';
import 'package:carlton/customWidgets/custom_segmented_button.dart';
import 'package:carlton/customWidgets/custom_text_field.dart';
import 'package:carlton/enums/enums.dart';
import 'package:carlton/extensions/date_extension.dart';
import 'package:carlton/extensions/price_extension.dart';
import 'package:carlton/l10n/app_translations.dart';
import 'package:carlton/models/segement_item.dart';
import 'package:carlton/models/transfer.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';

/// The three-step airport-transfer flow (Figma frames "Airport Transfer",
/// steps 1–3), presented as one sheet whose body swaps per step.
///
/// One sheet rather than three stacked ones: the design keeps the same header
/// and progress strip across all three, and popping/pushing sheets would
/// re-run the entrance animation on every Back.
Future<void> showAirportTransferSheet() {
  Get.find<AirportTransferController>().goTo(0);
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
    return Obx(
      () => Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        spacing: 16,
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
    );
  }
}

/// Transfer artwork, title, route and the close affordance.
class _Header extends GetView<AirportTransferController> {
  const _Header();

  @override
  Widget build(BuildContext context) {
    final textStyle = Get.textTheme;
    return Row(
      spacing: 12,
      children: [
        const CustomImage(
          source: 'assets/images/airport_transfer.png',
          width: 44,
          height: 36,
          fit: BoxFit.contain,
        ),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                AppTranslations.transferTitle,
                style: textStyle.titleMedium?.copyWith(
                  color: AppColors.inkBlack,
                ),
              ),
              Text(
                AppTranslations.transferRoute,
                style: textStyle.bodySmall?.copyWith(
                  fontFamily: 'DM Sans',
                  color: AppColors.mediumGrey,
                ),
              ),
            ],
          ),
        ),
        IconButton(
          onPressed: Get.back,
          icon: const Icon(Icons.close, color: AppColors.mediumGrey),
        ),
      ],
    );
  }
}

/// Three bare segments that fill as the guest advances.
///
/// Not [CheckInTabBar] — that one is labelled and tap-navigable, whereas this
/// strip is inert and label-less. Sharing them would mean a widget that is
/// mostly flags.
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
              color: i <= step ? AppColors.primary : AppColors.iceBlue,
              borderRadius: BorderRadius.circular(2),
            ),
          ),
        ),
      ),
    );
  }
}

/// Step 1 — flight number, date, arrival time, terminal, party size.
class _FlightDetailsStep extends GetView<AirportTransferController> {
  const _FlightDetailsStep();

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      spacing: 16,
      children: [
        _SectionLabel(AppTranslations.flightDetails),
        _Field(
          label: AppTranslations.flightNumber,
          child: CustomTextField(
            controller: controller.flightNumberController,
            textInputType: TextInputType.text,
            hintText: AppTranslations.flightNumberHint,
            fillColor: AppColors.whisperGrey,
          ),
        ),
        Row(
          spacing: 12,
          children: [
            Expanded(
              child: _Field(
                label: AppTranslations.transferDate,
                child: _PickerBox(
                  value: controller.date.value.formatDatePicker(),
                  onTap: controller.pickDate,
                ),
              ),
            ),
            Expanded(
              child: _Field(
                label: AppTranslations.arrivalTime,
                child: _PickerBox(
                  value: controller.arrivalTimeLabel,
                  onTap: controller.pickArrivalTime,
                  trailing: Icons.chevron_right,
                ),
              ),
            ),
          ],
        ),
        _Field(
          label: AppTranslations.terminal,
          child: CustomSegmentedButton(
            expanded: true,
            segments: AirportTransferController.terminals
                .map((t) => SegmentItem(label: t))
                .toList(),
            selectedIndex: controller.terminalIndex.value,
            onChanged: controller.setTerminal,
            selectedBackgroundColor: AppColors.frostTeal,
            selectedForegroundColor: AppColors.primary,
          ),
        ),
        CustomCounterField(
          title: AppTranslations.passengers,
          subtitle: AppTranslations.maxPassengers(
            AirportTransferController.maxPassengers,
          ),
          value: controller.passengers.value,
          onChanged: controller.setPassengers,
          minCount: 1,
          maxCount: AirportTransferController.maxPassengers,
        ),
        CustomFilledButton(
          height: 50,
          onPressed: controller.toVehicles,
          backgroundColor: AppColors.lagoonTeal,
          child: _CtaLabel(AppTranslations.chooseYourCar),
        ),
      ],
    );
  }
}

/// Step 2 — the vehicle catalogue from `GET /transfers`.
class _SelectVehicleStep extends GetView<AirportTransferController> {
  const _SelectVehicleStep();

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      spacing: 16,
      children: [
        _SectionLabel(AppTranslations.selectVehicle),
        if (controller.loading.value)
          const Padding(
            padding: EdgeInsets.all(24),
            child: Center(child: SpinningIconIndicator()),
          )
        else if (controller.loadFailed.value)
          _Notice(
            message: AppTranslations.transferLoadFailed,
            actionLabel: AppTranslations.retry,
            onAction: controller.loadTransfers,
          )
        else if (controller.transfers.isEmpty)
          _Notice(message: AppTranslations.noTransfers)
        else
          ...controller.transfers.map((t) => _VehicleCard(transfer: t)),
        _StepActions(
          onNext: controller.toConfirm,
          nextLabel: AppTranslations.reviewBookingLabel,
          // Greyed until a car is picked; tapping still explains why rather
          // than being inert.
          enabled: controller.canConfirm,
        ),
      ],
    );
  }
}

class _VehicleCard extends GetView<AirportTransferController> {
  final Transfer transfer;

  const _VehicleCard({required this.transfer});

  @override
  Widget build(BuildContext context) {
    final seats = controller.seatsParty(transfer);
    // Capacity and description are absent from TransferResource today, so both
    // lines are conditional rather than placeholder text.
    final lines = <String>[
      if (transfer.description?.isNotEmpty ?? false)
        transfer.description!.value,
      if (transfer.maxPassengers != null)
        AppTranslations.upToPassengers(transfer.maxPassengers!),
    ];

    return Opacity(
      // A car that cannot seat the party stays visible but unselectable —
      // hiding it would make the list silently change length as the counter
      // moves, which reads as a bug.
      opacity: seats ? 1 : 0.45,
      child: IgnorePointer(
        ignoring: !seats,
        child: CustomSelectableCard(
          title: transfer.name.value,
          subtitle: lines.isEmpty ? null : lines.join('\n'),
          selected: controller.selected.value?.uuid == transfer.uuid,
          onTap: () => controller.selectTransfer(transfer),
          controlType: SelectableControl.checkbox,
          leading: const CustomImage(
            source: 'assets/images/airport_transfer.png',
            width: 56,
            height: 36,
            fit: BoxFit.contain,
          ),
          trailingText: MoneyFormat.usdString(transfer.priceUsd),
        ),
      ),
    );
  }
}

/// Step 3 — read-back summary, instructions, and the (unwired) confirm.
class _ConfirmStep extends GetView<AirportTransferController> {
  const _ConfirmStep();

  @override
  Widget build(BuildContext context) {
    final flight = controller.flightNumberController.text.trim();
    final selected = controller.selected.value;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      spacing: 16,
      children: [
        _SectionLabel(AppTranslations.confirmTransfer),
        Container(
          padding: const EdgeInsets.all(15),
          decoration: BoxDecoration(
            color: AppColors.whisperGrey,
            borderRadius: BorderRadius.circular(12),
          ),
          child: Column(
            spacing: 10,
            children: [
              _SummaryRow(AppTranslations.pickup, AppTranslations.pickupValue),
              _SummaryRow(
                AppTranslations.destination,
                AppTranslations.destinationValue,
              ),
              _SummaryRow(
                AppTranslations.flightLabel,
                flight.isEmpty ? AppTranslations.notSpecified : flight,
              ),
              _SummaryRow(
                AppTranslations.dateAndTime,
                controller.dateTimeLabel,
              ),
              _SummaryRow(
                AppTranslations.terminal,
                AppTranslations.terminalValue(controller.terminal),
              ),
              _SummaryRow(
                AppTranslations.passengers,
                AppTranslations.passengerCount(controller.passengers.value),
              ),
              if (selected != null)
                _SummaryRow(AppTranslations.vehicle, selected.name.value),
              const Divider(color: AppColors.black06),
              CustomPriceSummaryRow(
                title: AppTranslations.total,
                value: selected == null
                    ? ''
                    : MoneyFormat.usdString(selected.priceUsd),
                isTotal: true,
                valueColor: AppColors.lagoonTeal,
              ),
            ],
          ),
        ),
        _Field(
          label: AppTranslations.transferInstructions,
          child: CustomTextField(
            controller: controller.notesController,
            textInputType: TextInputType.multiline,
            hintText: AppTranslations.transferInstructionsHint,
            maxLines: 3,
            fillColor: AppColors.whisperGrey,
          ),
        ),
        CustomInfoBanner(
          message: AppTranslations.transferFolioNote,
          tone: InfoBannerTone.warning,
        ),
        _StepActions(
          onNext: controller.confirm,
          nextLabel: AppTranslations.confirmBooking,
          showArrow: false,
        ),
      ],
    );
  }
}

// ── Shared bits ─────────────────────────────────────────────────────────────

class _SectionLabel extends StatelessWidget {
  final String text;

  const _SectionLabel(this.text);

  @override
  Widget build(BuildContext context) => Text(
    text,
    style: Get.textTheme.titleSmall?.copyWith(color: AppColors.inkBlack),
  );
}

/// A labelled form row — the design puts every control under a small caption.
class _Field extends StatelessWidget {
  final String label;
  final Widget child;

  const _Field({required this.label, required this.child});

  @override
  Widget build(BuildContext context) => Column(
    crossAxisAlignment: CrossAxisAlignment.start,
    spacing: 8,
    children: [
      Text(
        label,
        style: Get.textTheme.labelMedium?.copyWith(
          fontFamily: 'DM Sans',
          color: AppColors.steelGrey,
        ),
      ),
      child,
    ],
  );
}

/// Tap target that looks like a filled field but opens a picker.
class _PickerBox extends StatelessWidget {
  final String value;
  final VoidCallback onTap;
  final IconData? trailing;

  const _PickerBox({required this.value, required this.onTap, this.trailing});

  @override
  Widget build(BuildContext context) => InkWell(
    onTap: onTap,
    borderRadius: BorderRadius.circular(10),
    child: Container(
      height: 48,
      padding: const EdgeInsetsDirectional.only(start: 12, end: 8),
      decoration: BoxDecoration(
        color: AppColors.whisperGrey,
        borderRadius: BorderRadius.circular(10),
      ),
      child: Row(
        children: [
          Expanded(
            child: Text(
              value,
              style: Get.textTheme.bodyMedium?.copyWith(
                fontFamily: 'DM Sans',
                color: AppColors.inkBlack,
              ),
            ),
          ),
          if (trailing != null)
            Icon(trailing, size: 20, color: AppColors.mediumGrey),
        ],
      ),
    ),
  );
}

class _SummaryRow extends StatelessWidget {
  final String label;
  final String value;

  const _SummaryRow(this.label, this.value);

  @override
  Widget build(BuildContext context) {
    final textStyle = Get.textTheme;
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      spacing: 12,
      children: [
        Text(
          label,
          style: textStyle.labelMedium?.copyWith(
            fontFamily: 'DM Sans',
            color: AppColors.steelGrey,
          ),
        ),
        Expanded(
          child: Text(
            value,
            textAlign: TextAlign.end,
            style: textStyle.labelMedium?.copyWith(
              fontFamily: 'DM Sans',
              color: AppColors.inkBlack,
            ),
          ),
        ),
      ],
    );
  }
}

/// Back + primary pair. Back is narrower, matching the design.
class _StepActions extends GetView<AirportTransferController> {
  final VoidCallback onNext;
  final String nextLabel;
  final bool showArrow;
  final bool enabled;

  const _StepActions({
    required this.onNext,
    required this.nextLabel,
    this.showArrow = true,
    this.enabled = true,
  });

  @override
  Widget build(BuildContext context) => Row(
    spacing: 10,
    children: [
      Expanded(
        child: CustomOutlinedButton(
          height: 50,
          onPressed: controller.back,
          backgroundColor: AppColors.whisperGrey,
          child: Text(AppTranslations.back),
        ),
      ),
      Expanded(
        flex: 2,
        child: Opacity(
          opacity: enabled ? 1 : 0.45,
          child: CustomFilledButton(
            height: 50,
            onPressed: onNext,
            backgroundColor: AppColors.lagoonTeal,
            child: showArrow ? _CtaLabel(nextLabel) : Text(nextLabel),
          ),
        ),
      ),
    ],
  );
}

/// CTA text with the design's trailing arrow. Uses a directional icon so the
/// arrow points the way the guest is actually travelling in Arabic.
class _CtaLabel extends StatelessWidget {
  final String text;

  const _CtaLabel(this.text);

  @override
  Widget build(BuildContext context) => Row(
    mainAxisSize: MainAxisSize.min,
    spacing: 6,
    children: [
      Flexible(child: Text(text, overflow: TextOverflow.ellipsis)),
      const Icon(Icons.arrow_forward, size: 18),
    ],
  );
}

class _Notice extends StatelessWidget {
  final String message;
  final String? actionLabel;
  final VoidCallback? onAction;

  const _Notice({required this.message, this.actionLabel, this.onAction});

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.symmetric(vertical: 24),
    child: Column(
      spacing: 12,
      children: [
        Text(
          message,
          textAlign: TextAlign.center,
          style: Get.textTheme.bodyMedium?.copyWith(
            fontFamily: 'DM Sans',
            color: AppColors.mediumGrey,
          ),
        ),
        if (actionLabel != null)
          CustomOutlinedButton(
            height: 44,
            onPressed: onAction,
            child: Text(actionLabel!),
          ),
      ],
    ),
  );
}
