part of '../airport_transfer_sheet.dart';

class _SelectVehicleStep extends GetView<AirportTransferController> {
  const _SelectVehicleStep();

  @override
  Widget build(BuildContext context) => Obx(() => _build(context));

  Widget _build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      spacing: 10,
      children: [
        _sectionLabel(AppTranslations.selectVehicle),
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
  Widget build(BuildContext context) => Obx(() => _build(context));

  Widget _build(BuildContext context) {
    final textStyle = Get.textTheme;
    final seats = controller.seatsParty(transfer);
    final selected = controller.selected.value?.uuid == transfer.uuid;
    final description = transfer.description?.value ?? '';
    final caption = textStyle.dmBodySmall?.copyWith(
      fontSize: 12,
      color: AppColors.steelGrey,
    );

    return Opacity(
      // A car that cannot seat the party stays visible but unselectable —
      // hiding it would make the list silently change length as the counter
      // moves, which reads as a bug.
      opacity: seats ? 1 : 0.45,
      child: Material(
        color: AppColors.white,
        shape: _outline(12, selected ? AppColors.primary : AppColors.black06),
        child: InkWell(
          onTap: seats ? () => controller.selectTransfer(transfer) : null,
          borderRadius: BorderRadius.circular(12),
          child: Padding(
            padding: const EdgeInsets.symmetric(horizontal: 17, vertical: 15),
            child: Row(
              spacing: 14,
              children: [
                Image.asset(
                  AirportTransferController.vehicleImage(transfer),
                  width: 72,
                  height: 49,
                  fit: BoxFit.contain,
                ),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    spacing: 2,
                    children: [
                      Text(
                        transfer.name.value,
                        style: textStyle.labelLarge?.copyWith(
                          fontSize: 14,
                          fontWeight: FontWeight.w600,
                          color: AppColors.inkBlack,
                        ),
                      ),
                      if (description.isNotEmpty)
                        Text(description, style: caption),
                      if (transfer.maxPassengers != null)
                        RowTextComponent(
                          text: AppTranslations.upToPassengers(
                            transfer.maxPassengers!,
                          ),
                          iconPath: 'assets/icons/transfer_user_rectangle.svg',
                          iconSize: 14,
                          spacing: 4,
                          textStyle: caption?.copyWith(fontSize: 11),
                        ),
                    ],
                  ),
                ),
                Column(
                  crossAxisAlignment: CrossAxisAlignment.end,
                  children: [
                    Text(
                      MoneyFormat.usdString(transfer.priceUsd),
                      style: textStyle.titleMedium?.copyWith(
                        fontSize: 16,
                        fontWeight: FontWeight.w700,
                        color: AppColors.lagoonTeal,
                      ),
                    ),
                    Text(
                      AppTranslations.oneWay,
                      style: textStyle.dmLabelSmall?.copyWith(
                        fontSize: 10,
                        color: AppColors.taupeBrown,
                      ),
                    ),
                  ],
                ),
                _Checkbox(checked: selected),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class _Checkbox extends StatelessWidget {
  final bool checked;

  const _Checkbox({required this.checked});

  @override
  Widget build(BuildContext context) => Container(
    width: 22,
    height: 22,
    alignment: Alignment.center,
    decoration: BoxDecoration(
      color: checked ? AppColors.primary : AppColors.whisperGrey,
      borderRadius: BorderRadius.circular(6),
      border: checked ? null : Border.all(color: AppColors.black10),
    ),
    child: checked ? Icon(Icons.check, size: 13, color: AppColors.white) : null,
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
          style: Get.textTheme.dmBodyMedium?.copyWith(
            color: AppColors.mediumGrey,
          ),
        ),
        if (actionLabel != null)
          CustomFilledButton(
            height: 44,
            onPressed: onAction,
            backgroundColor: AppColors.whisperGrey,
            foregroundColor: AppColors.inkBlack,
            child: Text(actionLabel!),
          ),
      ],
    ),
  );
}
