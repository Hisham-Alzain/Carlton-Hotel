import 'package:carlton/components/custom_counter_field.dart';
import 'package:carlton/components/dining/custom_time_slot_selector.dart';
import 'package:carlton/constants/demo_data.dart';
import 'package:carlton/controllers/dining/restaurant_controller.dart';
import 'package:carlton/customWidgets/custom_filled_button.dart';
import 'package:carlton/customWidgets/custom_text_field.dart';
import 'package:carlton/extensions/date_extension.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:flutter_svg/flutter_svg.dart';
import 'package:get/get.dart';

/// Restaurant "Reserve" tab: date field, time-slot grid, guests counter,
/// special requests, and the Confirm CTA.
class RestaurantReserveTab extends StatelessWidget {
  final RestaurantController c;

  const RestaurantReserveTab({required this.c, super.key});

  @override
  Widget build(BuildContext context) {
    final TextTheme textStyle = Get.textTheme;

    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        Text(
          'Reserve a Table',
          style: textStyle.titleMedium?.copyWith(
            fontWeight: FontWeight.w700,
            color: AppColors.inkBlack,
          ),
        ),
        const SizedBox(height: 16),
        const _Label('Date'),
        _DateField(value: c.reserveDate.formatDatePicker(), onTap: c.pickDate),
        const SizedBox(height: 16),
        const _Label('Time'),
        CustomTimeSlotSelector(
          slots: DemoData.reserveTimeSlots,
          selected: c.timeSlot,
          onSelected: c.selectTimeSlot,
        ),
        const SizedBox(height: 16),
        const _Label('Guests'),
        CustomCounterField(
          title: 'Guests',
          value: c.guests,
          onChanged: c.setGuests,
          min: 1,
          max: 20,
        ),
        const SizedBox(height: 16),
        const _Label('Special Requests (optional)'),
        CustomTextField(
          controller: c.specialRequests,
          textInputType: TextInputType.multiline,
          maxLines: 3,
          hintText: 'Allergies, dietary requirements, occasion...',
          fillColor: AppColors.white,
          borderColor: AppColors.linenGrey,
        ),
        const SizedBox(height: 24),
        CustomFilledButton(
          width: double.infinity,
          height: 52,
          backgroundColor: AppColors.primary,
          onPressed: c.confirmReservation,
          child: const Text('Confirm Reservation'),
        ),
      ],
    );
  }
}

class _DateField extends StatelessWidget {
  final String value;
  final VoidCallback onTap;

  const _DateField({required this.value, required this.onTap});

  @override
  Widget build(BuildContext context) {
    return Material(
      color: AppColors.pearlCream,
      borderRadius: BorderRadius.circular(10),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(10),
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
          child: Row(
            children: [
              SvgPicture.asset(
                'assets/icons/calendar.svg',
                width: 15,
                height: 15,
                colorFilter: const ColorFilter.mode(
                  AppColors.antiqueGold,
                  BlendMode.srcIn,
                ),
              ),
              const SizedBox(width: 8),
              Expanded(
                child: Text(
                  value,
                  style: Get.textTheme.labelLarge?.copyWith(
                    fontWeight: FontWeight.w500,
                    color: AppColors.inkBlack,
                  ),
                ),
              ),
              SvgPicture.asset(
                'assets/icons/date_chevron.svg',
                width: 15,
                height: 15,
                colorFilter: const ColorFilter.mode(
                  AppColors.taupeBrown,
                  BlendMode.srcIn,
                ),
              ),
            ],
          ),
        ),
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
      padding: const EdgeInsets.only(bottom: 8),
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
