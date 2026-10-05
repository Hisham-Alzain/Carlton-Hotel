part of '../airport_transfer_sheet.dart';

class _FlightDetailsStep extends GetView<AirportTransferController> {
  const _FlightDetailsStep();

  @override
  Widget build(BuildContext context) => Obx(() => _build(context));

  Widget _build(BuildContext context) {
    const terminals = AirportTransferController.terminals;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      spacing: 14,
      children: [
        _sectionLabel(AppTranslations.flightDetails),
        _field(
          icon: 'assets/icons/transfer_ticket.svg',
          label: AppTranslations.flightNumber,
          child: CustomTextField(
            controller: controller.flightNumberController,
            textInputType: TextInputType.text,
            hintText: AppTranslations.flightNumberHint,
            fillColor: AppColors.whisperGrey,
            borderColor: AppColors.black06,
          ),
        ),
        Row(
          spacing: 10,
          children: [
            Expanded(
              child: _field(
                icon: 'assets/icons/transfer_calendar_dots.svg',
                label: AppTranslations.transferDate,
                child: _PickerBox(
                  value: controller.date.value.formatDatePicker(),
                  onTap: controller.pickDate,
                ),
              ),
            ),
            Expanded(
              child: _field(
                icon: 'assets/icons/transfer_airplane_landing.svg',
                label: AppTranslations.arrivalTime,
                child: _PickerBox(
                  value: controller.arrivalTimeLabel,
                  onTap: controller.pickArrivalTime,
                  showArrow: true,
                ),
              ),
            ),
          ],
        ),
        _field(
          icon: 'assets/icons/transfer_air_traffic_control.svg',
          label: AppTranslations.terminal,
          child: Row(
            spacing: 8,
            children: [
              for (var i = 0; i < terminals.length; i++)
                Expanded(
                  child: _TerminalChip(
                    label: terminals[i],
                    selected: controller.terminalIndex.value == i,
                    onTap: () => controller.setTerminal(i),
                  ),
                ),
            ],
          ),
        ),
        _field(
          icon: 'assets/icons/transfer_user_rectangle.svg',
          label: AppTranslations.passengers,
          child: _PassengerCounter(
            value: controller.passengers.value,
            onChanged: controller.setPassengers,
          ),
        ),
        _SheetButton(
          label: AppTranslations.chooseYourCar,
          showArrow: true,
          onPressed: () => _closingKeyboard(controller.toVehicles),
        ),
      ],
    );
  }
}

class _TerminalChip extends StatelessWidget {
  final String label;
  final bool selected;
  final VoidCallback onTap;

  const _TerminalChip({
    required this.label,
    required this.selected,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) => Material(
    color: selected
        ? AppColors.lagoonTeal.withValues(alpha: 0.35)
        : AppColors.white,
    shape: _outline(10, selected ? AppColors.charcoalTeal : AppColors.black10),
    child: InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(10),
      child: SizedBox(
        height: 44,
        child: Center(
          child: Text(
            label,
            style: Get.textTheme.labelLarge?.copyWith(
              fontFamily: selected ? null : 'DM Sans',
              fontSize: 13,
              fontWeight: selected ? FontWeight.w600 : FontWeight.w500,
              color: AppColors.onyxBlack,
            ),
          ),
        ),
      ),
    ),
  );
}

/// Figma's counter: grey minus, the count in teal, a teal plus, then the cap.
class _PassengerCounter extends StatelessWidget {
  final int value;
  final ValueChanged<int> onChanged;

  const _PassengerCounter({required this.value, required this.onChanged});

  @override
  Widget build(BuildContext context) {
    const max = AirportTransferController.maxPassengers;
    final textStyle = Get.textTheme;
    return Row(
      spacing: 14,
      children: [
        _RoundButton(
          icon: Icons.remove,
          iconColor: AppColors.inkBlack,
          background: AppColors.whisperGrey,
          onTap: value > 1 ? () => onChanged(value - 1) : null,
        ),
        ConstrainedBox(
          constraints: const BoxConstraints(minWidth: 20),
          child: Text(
            '$value',
            textAlign: TextAlign.center,
            style: textStyle.titleMedium?.copyWith(
              fontSize: 18,
              fontWeight: FontWeight.w600,
              color: AppColors.primary,
            ),
          ),
        ),
        _RoundButton(
          icon: Icons.add,
          iconColor: AppColors.white,
          background: AppColors.primary,
          onTap: value < max ? () => onChanged(value + 1) : null,
        ),
        Text(
          AppTranslations.maxPassengers(max),
          style: textStyle.dmBodySmall?.copyWith(
            fontSize: 12,
            color: AppColors.graphite,
          ),
        ),
      ],
    );
  }
}

class _RoundButton extends StatelessWidget {
  final IconData icon;
  final Color iconColor;
  final Color background;
  final VoidCallback? onTap;

  const _RoundButton({
    required this.icon,
    required this.iconColor,
    required this.background,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) => Opacity(
    opacity: onTap == null ? 0.4 : 1,
    child: Material(
      color: background,
      shape: const CircleBorder(),
      child: InkWell(
        onTap: onTap,
        customBorder: const CircleBorder(),
        child: SizedBox.square(
          dimension: 32,
          child: Icon(icon, size: 16, color: iconColor),
        ),
      ),
    ),
  );
}
