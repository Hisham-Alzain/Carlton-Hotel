part of '../airport_transfer_sheet.dart';

class _ConfirmStep extends GetView<AirportTransferController> {
  const _ConfirmStep();

  @override
  Widget build(BuildContext context) => Obx(() => _build(context));

  Widget _build(BuildContext context) {
    final textStyle = Get.textTheme;
    final selected = controller.selected.value;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      spacing: 14,
      children: [
        _sectionLabel(AppTranslations.confirmTransfer),
        Container(
          padding: const EdgeInsets.all(17),
          decoration: _card(AppColors.whisperGrey, radius: 14, blur: 1),
          child: Column(
            spacing: 12,
            children: [
              Column(
                children: [
                  for (final (label, value) in [
                    (AppTranslations.pickup, AppTranslations.pickupValue),
                    (
                      AppTranslations.destination,
                      AppTranslations.destinationValue,
                    ),
                    (AppTranslations.flightLabel, controller.flightNumberLabel),
                    (AppTranslations.dateAndTime, controller.dateTimeLabel),
                    (AppTranslations.terminal, controller.terminalLabel),
                    (AppTranslations.passengers, controller.passengersLabel),
                  ])
                    _SummaryRow(label, value),
                  if (selected != null)
                    _SummaryRow(
                      AppTranslations.vehicle,
                      selected.name.value,
                      image: AirportTransferController.vehicleImage(selected),
                    ),
                ],
              ),
              Row(
                children: [
                  Expanded(
                    child: Text(
                      AppTranslations.total,
                      style: textStyle.labelLarge?.copyWith(
                        fontSize: 14,
                        fontWeight: FontWeight.w600,
                        color: AppColors.inkBlack,
                      ),
                    ),
                  ),
                  Text(
                    selected == null
                        ? ''
                        : MoneyFormat.usdString(selected.priceUsd),
                    style: textStyle.titleLarge?.copyWith(
                      fontSize: 20,
                      fontWeight: FontWeight.w700,
                      color: AppColors.lagoonTeal,
                    ),
                  ),
                ],
              ),
            ],
          ),
        ),
        Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          spacing: 6,
          children: [
            Text(AppTranslations.transferInstructions, style: _fieldLabelStyle),
            CustomTextField(
              controller: controller.notesController,
              textInputType: TextInputType.multiline,
              hintText: AppTranslations.transferInstructionsHint,
              maxLines: 3,
              maxLength: AirportTransferController.maxInstructionsLength,
              fillColor: AppColors.whisperGrey,
              borderColor: AppColors.black06,
            ),
          ],
        ),
        Container(
          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 9),
          decoration: _card(AppColors.snowGrey, radius: 8, blur: 2),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            spacing: 4,
            children: [
              Padding(
                padding: const EdgeInsets.only(top: 1),
                child: SvgPicture.asset(
                  'assets/icons/transfer_info.svg',
                  width: 14,
                  height: 14,
                ),
              ),
              Expanded(
                child: Text(
                  AppTranslations.transferFolioNote,
                  style: textStyle.dmBodySmall?.copyWith(
                    fontSize: 11,
                    color: AppColors.inkBlack,
                    height: 1.45,
                  ),
                ),
              ),
            ],
          ),
        ),
        _StepActions(
          onNext: controller.confirm,
          nextLabel: AppTranslations.confirmBooking,
          showArrow: false,
          loading: controller.submitting.value,
        ),
      ],
    );
  }

  /// The summary card and the info note share a white rim and soft shadow.
  static BoxDecoration _card(
    Color color, {
    required double radius,
    required double blur,
  }) => BoxDecoration(
    color: color,
    borderRadius: BorderRadius.circular(radius),
    border: Border.all(color: AppColors.white),
    boxShadow: [
      BoxShadow(
        color: AppColors.silverShadow25,
        blurRadius: blur,
        offset: const Offset(0, 1),
      ),
    ],
  );
}

class _SummaryRow extends StatelessWidget {
  final String label;
  final String value;
  final String? image;

  const _SummaryRow(this.label, this.value, {this.image});

  @override
  Widget build(BuildContext context) {
    final textStyle = Get.textTheme;
    return Container(
      padding: const EdgeInsets.only(top: 8, bottom: 9),
      decoration: const BoxDecoration(
        border: Border(bottom: BorderSide(color: AppColors.black05)),
      ),
      child: Row(
        spacing: 12,
        children: [
          Text(
            label,
            style: textStyle.dmLabelMedium?.copyWith(
              fontSize: 12,
              color: AppColors.steelGrey,
            ),
          ),
          Expanded(
            child: Row(
              mainAxisAlignment: MainAxisAlignment.end,
              children: [
                if (image != null)
                  Image.asset(
                    image!,
                    width: 40,
                    height: 28,
                    fit: BoxFit.contain,
                  ),
                Flexible(
                  child: Text(
                    value,
                    textAlign: TextAlign.end,
                    style: textStyle.labelMedium?.copyWith(
                      fontSize: 12,
                      fontWeight: FontWeight.w500,
                      color: AppColors.inkBlack,
                    ),
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
