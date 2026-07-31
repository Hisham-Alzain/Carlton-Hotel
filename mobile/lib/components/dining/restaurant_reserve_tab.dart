import 'package:carlton/components/custom_counter_field.dart';
import 'package:carlton/components/dining/custom_time_slot_selector.dart';
import 'package:carlton/constants/demo_data.dart';
import 'package:carlton/controllers/dining/restaurant_controller.dart';
import 'package:carlton/customWidgets/custom_filled_button.dart';
import 'package:carlton/customWidgets/custom_outlined_button.dart';
import 'package:carlton/customWidgets/custom_text_field.dart';
import 'package:carlton/extensions/date_extension.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';

/// Restaurant "Reserve" tab: date field, time-slot grid, guests counter,
/// special requests, and the Confirm CTA.
class RestaurantReserveTab extends StatelessWidget {
  final RestaurantController c;

  const RestaurantReserveTab({required this.c, super.key});

  @override
  Widget build(BuildContext context) {
    final TextTheme textStyle = Get.textTheme;

    // Scrollable because this tab lives in a TabBarView inside an Expanded —
    // the height is bounded, so a bare Column would overflow instead of scroll.
    return SingleChildScrollView(
      padding: const EdgeInsets.all(16),
      child: Column(
        spacing: 10,
        // ListView stretched its children to full width for free; a Column
        // centres them by default, which would shrink every field and the CTA
        // to their content width.
        crossAxisAlignment: CrossAxisAlignment.stretch,
        // No `spacing:` — the gaps are deliberately uneven: 16 between groups,
        // 24 before the CTA, and only the _Label's own bottom: 8 between a
        // label and its field. A uniform spacing would add 16 on top of that 8
        // and break every label/field pairing.
        children: [
          Text(
            'Reserve a Table',
            style: textStyle.titleMedium?.copyWith(
              fontWeight: FontWeight.w700,
              color: AppColors.inkBlack,
            ),
          ),
          const _Label('Date'),
          _DateField(
            value: c.reserveDate.formatDatePicker(),
            onTap: c.pickDate,
          ),
          const _Label('Time'),
          CustomTimeSlotSelector(
            slots: DemoData.reserveTimeSlots,
            selected: c.timeSlot,
            onSelected: c.selectTimeSlot,
          ),
          const _Label('Guests'),
          CustomCounterField(
            title: 'Guests',
            value: c.guests,
            onChanged: c.setGuests,
            minCount: 1,
            maxCount: 20,
          ),
          const _Label('Special Requests (optional)'),
          CustomTextField(
            controller: c.specialRequests,
            textInputType: TextInputType.multiline,
            maxLines: 3,
            hintText: 'Allergies, dietary requirements, occasion...',
            fillColor: AppColors.white,
            borderColor: AppColors.linenGrey,
          ),
          CustomFilledButton(
            width: double.infinity,
            height: 50,
            backgroundColor: AppColors.primary,
            onPressed: c.confirmReservation,
            child: const Text('Confirm Reservation'),
          ),
        ],
      ),
    );
  }
}

class _DateField extends StatelessWidget {
  final String value;
  final VoidCallback onTap;

  const _DateField({required this.value, required this.onTap});

  @override
  Widget build(BuildContext context) {
    // No `width`: CustomOutlinedButton defaults fixedSize to 300 wide, but the
    // parent Column stretches its children, so the tight constraints clamp that
    // back to full width.
    return CustomOutlinedButton(
      height: 50,
      width: double.infinity,
      backgroundColor: AppColors.pearlCream,
      // outlinedButtonTheme's side is white @ 0.8 — sized for the dark surfaces
      // the other call sites live on, and invisible on this light form.
      borderColor: AppColors.linenGrey,
      // Also drives the ripple, which would otherwise splash in pineTeal.
      foregroundColor: AppColors.inkBlack,
      onPressed: onTap,
      child: Row(
        spacing: 10,
        children: [
          const Icon(Icons.calendar_today, color: AppColors.taupeBrown),
          Expanded(
            child: Text(
              value,
              style: Get.textTheme.labelLarge?.copyWith(
                fontWeight: FontWeight.w500,
                color: AppColors.inkBlack,
              ),
            ),
          ),
          const Icon(Icons.arrow_drop_down, color: AppColors.taupeBrown),
        ],
      ),
    );
  }
}

class _Label extends StatelessWidget {
  final String text;

  const _Label(this.text);

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.all(10),
      child: Text(
        text,
        style: Get.textTheme.labelLarge?.copyWith(
          fontWeight: FontWeight.w600,
          color: AppColors.inkBlack,
        ),
      ),
    );
  }
}
