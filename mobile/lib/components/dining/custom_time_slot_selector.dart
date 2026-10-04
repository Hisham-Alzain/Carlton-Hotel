import 'package:carlton/theme/theme.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:intl/intl.dart';

/// The reservation time-slot picker (Figma): content-sized chips that wrap onto
/// as many rows as they need. The selected slot fills primary with white text;
/// the rest are grey outlined pills.
///
/// Wrapping rather than a horizontal scroller is deliberate — this is a booking
/// form, so every slot has to be visible without the guest hunting for it.
class CustomTimeSlotSelector extends StatelessWidget {
  final List<String> slots;
  final String? selected;
  final ValueChanged<String> onSelected;

  const CustomTimeSlotSelector({
    required this.slots,
    required this.selected,
    required this.onSelected,
    super.key,
  });

  @override
  Widget build(BuildContext context) {
    final TextTheme textStyle = Get.textTheme;

    return Wrap(
      spacing: 10,
      runSpacing: 10,
      children: [
        ...slots.map((slot) {
          final bool isSelected = slot == selected;
          // The global chipTheme is tuned for a different chip (radius 12, a
          // primary08 fill, a white border), so the pill styling is spelled out
          // here; only showCheckmark/labelPadding are inherited.
          return ChoiceChip(
            label: Text(_label(slot)),
            selected: isSelected,
            // The bool is ignored: tapping the selected slot re-selects it
            // rather than leaving the form with no time at all.
            onSelected: (_) => onSelected(slot),
            padding: const EdgeInsets.all(10),
            // Chips default to a 48px padded tap target, which would inflate
            // every row by 8px on top of the Wrap's runSpacing.
            // materialTapTargetSize: MaterialTapTargetSize.shrinkWrap,
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(20),
            ),
            side: BorderSide(
              color: isSelected ? AppColors.primary : AppColors.black10,
            ),
            labelStyle: textStyle.dmLabelMedium?.copyWith(
              fontWeight: isSelected ? FontWeight.w600 : FontWeight.w400,
              color: isSelected ? AppColors.white : AppColors.graphite,
            ),
          );
        }),
      ],
    );
  }

  /// `7:30 PM` in the app language (`19:30`, `7:30 م`, …). The slot string
  /// itself is never changed: RestaurantController converts it for the API.
  static String _label(String slot) {
    try {
      return DateFormat.jm().format(DateFormat('h:mm a', 'en').parse(slot));
    } on FormatException {
      return slot;
    }
  }
}
