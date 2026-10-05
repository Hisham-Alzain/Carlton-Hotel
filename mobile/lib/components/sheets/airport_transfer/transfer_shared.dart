part of '../airport_transfer_sheet.dart';

/// Runs a step change with the keyboard closed. Left open from the flight
/// number or instructions field, it covers the step's buttons and squeezes
/// the next step until its list overflows.
void _closingKeyboard(VoidCallback action) {
  FocusManager.instance.primaryFocus?.unfocus();
  action();
}

RoundedRectangleBorder _outline(double radius, Color color) =>
    RoundedRectangleBorder(
      borderRadius: BorderRadius.circular(radius),
      side: BorderSide(color: color),
    );

Widget _sectionLabel(String text) => Text(
  text,
  style: Get.textTheme.titleSmall?.copyWith(
    fontSize: 13,
    fontWeight: FontWeight.w600,
    color: AppColors.inkBlack,
  ),
);

TextStyle? get _fieldLabelStyle => Get.textTheme.dmLabelMedium?.copyWith(
  fontSize: 12,
  fontWeight: FontWeight.w500,
  color: AppColors.graphite,
);

/// A form row: the Figma icon + caption above its control.
Widget _field({
  required String icon,
  required String label,
  required Widget child,
}) => Column(
  crossAxisAlignment: CrossAxisAlignment.start,
  spacing: 6,
  children: [
    RowTextComponent(
      text: label,
      iconPath: icon,
      iconSize: 14,
      spacing: 4,
      textStyle: _fieldLabelStyle,
    ),
    child,
  ],
);

/// Looks like a filled field but opens a picker.
class _PickerBox extends StatelessWidget {
  final String value;
  final VoidCallback onTap;
  final bool showArrow;

  const _PickerBox({
    required this.value,
    required this.onTap,
    this.showArrow = false,
  });

  @override
  Widget build(BuildContext context) => Material(
    color: AppColors.whisperGrey,
    shape: _outline(10, AppColors.black06),
    child: InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(10),
      child: Container(
        height: 46,
        padding: const EdgeInsetsDirectional.only(start: 15, end: 10),
        child: Row(
          children: [
            Expanded(
              child: Text(
                value,
                style: Get.textTheme.labelLarge?.copyWith(
                  fontSize: 13,
                  fontWeight: FontWeight.w500,
                  color: AppColors.inkBlack,
                ),
              ),
            ),
            if (showArrow)
              // chevron_right mirrors itself in RTL (matchTextDirection).
              const Icon(
                Icons.chevron_right,
                size: 24,
                color: AppColors.inkBlack,
              ),
          ],
        ),
      ),
    ),
  );
}

/// The design's teal primary and grey secondary buttons.
class _SheetButton extends StatelessWidget {
  final String label;
  final VoidCallback? onPressed;
  final bool primary;
  final bool showArrow;
  final bool loading;

  const _SheetButton({
    required this.label,
    required this.onPressed,
    this.primary = true,
    this.showArrow = false,
    this.loading = false,
  });

  @override
  Widget build(BuildContext context) => CustomFilledButton(
    height: 52,
    onPressed: onPressed,
    isLoading: loading,
    backgroundColor: primary ? AppColors.lagoonTeal : AppColors.whisperGrey,
    foregroundColor: primary ? AppColors.white : AppColors.inkBlack,
    elevation: primary ? null : 0,
    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
    textStyle: Get.textTheme.dmLabelLarge?.copyWith(
      fontSize: 14,
      fontWeight: primary ? FontWeight.w600 : FontWeight.w500,
    ),
    // The design's "→" is text; a directional icon keeps it pointing forward
    // in Arabic too.
    child: showArrow
        ? Row(
            mainAxisSize: MainAxisSize.min,
            spacing: 6,
            children: [
              Flexible(child: Text(label, overflow: TextOverflow.ellipsis)),
              const Icon(Icons.arrow_forward, size: 16),
            ],
          )
        : Text(label),
  );
}

/// Back (1 part, grey) + primary (2 parts, teal), as in the design.
class _StepActions extends GetView<AirportTransferController> {
  final VoidCallback onNext;
  final String nextLabel;
  final bool showArrow;
  final bool enabled;
  final bool loading;

  const _StepActions({
    required this.onNext,
    required this.nextLabel,
    this.showArrow = true,
    this.enabled = true,
    this.loading = false,
  });

  @override
  Widget build(BuildContext context) => Row(
    spacing: 10,
    children: [
      Expanded(
        child: _SheetButton(
          label: AppTranslations.back,
          primary: false,
          onPressed: loading ? null : () => _closingKeyboard(controller.back),
        ),
      ),
      Expanded(
        flex: 2,
        child: Opacity(
          opacity: enabled ? 1 : 0.45,
          child: _SheetButton(
            label: nextLabel,
            showArrow: showArrow,
            loading: loading,
            onPressed: () => _closingKeyboard(onNext),
          ),
        ),
      ),
    ],
  );
}
