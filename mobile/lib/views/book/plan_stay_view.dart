import 'package:carlton/customWidgets/custom_containers.dart';
import 'package:carlton/components/custom_booking_app_bar.dart';
import 'package:carlton/controllers/booking/booking_flow_controller.dart';
import 'package:carlton/customWidgets/custom_counter_field.dart';
import 'package:carlton/customWidgets/custom_date_box.dart';
import 'package:carlton/customWidgets/custom_date_range_calendar.dart';
import 'package:carlton/customWidgets/custom_filled_button.dart';
import 'package:carlton/customWidgets/custom_scaffold.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:intl/intl.dart';

/// Step 1 — pick dates + guests (Figma "Booking / Step 1").
class PlanStayView extends StatelessWidget {
  const PlanStayView({super.key});

  @override
  Widget build(BuildContext context) {
    final f = DateFormat('MMM d, yyyy');
    return CustomScaffold(
      appBar: const CustomBookingAppBar(
        title: 'Plan Your Stay',
        currentStep: 1,
      ),
      body: GetBuilder<BookingFlowController>(
        builder: (c) => Column(
          children: [
            Expanded(
              child: ListView(
                padding: const EdgeInsets.all(20),
                children: [
                  const Text(
                    'Select Dates & Guests',
                    style: TextStyle(
                      fontFamily: 'Plus Jakarta Sans',
                      fontSize: 14,
                      fontWeight: FontWeight.w600,
                      color: AppColors.inkBlack,
                    ),
                  ),
                  const SizedBox(height: 14),
                  Row(
                    children: [
                      Expanded(
                        child: CustomDateBox(
                          label: 'Check-in',
                          value: c.rangeStart == null
                              ? 'Select'
                              : f.format(c.rangeStart!),
                          selected: true,
                          onTap: c.restartDateSelection,
                        ),
                      ),
                      const SizedBox(width: 10),
                      Expanded(
                        child: CustomDateBox(
                          label: 'Check-out',
                          value: c.rangeEnd == null
                              ? 'Select'
                              : f.format(c.rangeEnd!),
                          onTap: c.restartDateSelection,
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 16),
                  CustomDateRangeCalendar(
                    firstDay: c.firstDay,
                    lastDay: c.lastDay,
                    focusedDay: c.focusedDay,
                    rangeStart: c.rangeStart,
                    rangeEnd: c.rangeEnd,
                    onRangeSelected: c.onRangeSelected,
                    onPageChanged: c.onPageChanged,
                  ),
                  const SizedBox(height: 16),
                  CustomCounterField(
                    title: 'Adults',
                    subtitle: 'Ages 18+',
                    value: c.adults,
                    min: 1,
                    max: 10,
                    onChanged: c.setAdults,
                  ),
                  const SizedBox(height: 12),
                  CustomCounterField(
                    title: 'Children',
                    subtitle: 'Ages 0–17',
                    value: c.children,
                    max: 10,
                    onChanged: c.setChildren,
                  ),
                ],
              ),
            ),
            _Footer(controller: c),
          ],
        ),
      ),
    );
  }
}

class _Footer extends StatelessWidget {
  final BookingFlowController controller;
  const _Footer({required this.controller});

  @override
  Widget build(BuildContext context) {
    return SafeArea(
      top: false,
      child: Padding(
        padding: const EdgeInsets.fromLTRB(20, 8, 20, 12),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            PillContainer(
              padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
              backgroundColor: AppColors.cream,
              child: Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Flexible(
                    child: Text(
                      controller.dateSummary,
                      style: const TextStyle(
                        fontFamily: 'DM Sans',
                        fontSize: 13,
                        color: AppColors.inkBlack,
                      ),
                    ),
                  ),
                  Text(
                    controller.guestSummary,
                    style: const TextStyle(
                      fontFamily: 'Plus Jakarta Sans',
                      fontSize: 13,
                      color: AppColors.walnutGold,
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 12),
            CustomFilledButton(
              width: double.infinity,
              backgroundColor: AppColors.lagoonTeal,
              onPressed: controller.searchRooms,
              child: Text(
                controller.roomPreselected ? 'Continue' : 'Search Rooms',
              ),
            ),
          ],
        ),
      ),
    );
  }
}
