import 'package:carlton/controllers/booking/booking_flow_controller.dart';
import 'package:carlton/customWidgets/custom_containers.dart';
import 'package:carlton/components/custom_counter_field.dart';
import 'package:carlton/components/custom_date_box.dart';
import 'package:carlton/customWidgets/custom_date_range_calendar.dart';
import 'package:carlton/customWidgets/custom_filled_button.dart';
import 'package:carlton/extensions/date_extension.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:smooth_page_indicator/smooth_page_indicator.dart';

/// Book tab — the date/guest planning editor. The surrounding `MainView` shell
/// supplies the app bar and bottom nav; the draft is reset each time the tab is
/// opened (see `MainController.changeTab`).
class BookView extends StatelessWidget {
  const BookView({super.key});

  @override
  Widget build(BuildContext context) {
    final TextTheme textStyle = Get.textTheme;

    // Two observers, not one. Each Obx subscribes only to the fields its own
    // block reads, so bumping a guest counter no longer rebuilds the date-range
    // calendar — the most expensive widget on the screen. The heading and the
    // step indicator are static, so they sit outside both.
    final controller = Get.find<BookingFlowController>();
    return Padding(
      padding: const EdgeInsets.all(10),
      child: SingleChildScrollView(
        child: Column(
          spacing: 10,
          children: [
            Text(
              'Select Dates & Guests',
              style: Get.textTheme.labelLarge?.copyWith(
                fontWeight: FontWeight.w600,
                color: AppColors.inkBlack,
              ),
            ),
            AnimatedSmoothIndicator(
              activeIndex: 0,
              count: 6,
              effect: SlideEffect(
                dotHeight: 5,
                dotWidth: 50,
                spacing: 20,
                activeDotColor: AppColors.primary,
                dotColor: AppColors.iceBlue,
              ),
            ),

            Obx(() => _dates(controller)),

            Obx(() => _plan(controller, textStyle)),
          ],
        ),
      ),
    );
  }

  /// Check-in/check-out boxes + the calendar. Repaints only on a date pick.
  Widget _dates(BookingFlowController controller) {
    return Column(
      spacing: 10,
      children: [
        Row(
          spacing: 10,
          children: [
            Expanded(
              child: CustomDateBox(
                label: 'Check-in',
                value: controller.rangeStart.value?.formatDatePicker() ??
                    'Select',
                selected: true,
              ),
            ),

            Expanded(
              child: CustomDateBox(
                label: 'Check-out',
                value:
                    controller.rangeEnd.value?.formatDatePicker() ?? 'Select',
              ),
            ),
          ],
        ),

        CustomDateRangeCalendar(
          firstDay: controller.firstDay,
          lastDay: controller.lastDay,
          focusedDay: controller.focusedDay,
          rangeStart: controller.rangeStart.value,
          rangeEnd: controller.rangeEnd.value,
          onRangeSelected: controller.onRangeSelected,
          onPageChanged: controller.onPageChanged,
        ),
      ],
    );
  }

  /// Guest counters, the summary pill and the CTA — the summary reads both
  /// dates and guests, so a date pick repaints this block too.
  Widget _plan(BookingFlowController controller, TextTheme textStyle) {
    return Column(
      spacing: 10,
      children: [
        CustomCounterField(
          title: 'Adults',
          subtitle: 'Ages 18+',
          value: controller.adults.value,
          minCount: 1,
          maxCount: 10,
          onChanged: controller.setAdults,
        ),

        CustomCounterField(
          title: 'Children',
          subtitle: 'Ages 0–17',
          value: controller.children.value,
          maxCount: 10,
          onChanged: controller.setChildren,
        ),

        PillContainer(
          padding: const EdgeInsets.all(10),
          backgroundColor: AppColors.cream,
          child: Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Flexible(
                child: Text(
                  controller.dateSummary,
                  style: textStyle.labelMedium?.copyWith(
                    fontFamily: 'DM Sans',
                    color: AppColors.inkBlack,
                  ),
                ),
              ),

              Text(
                controller.guestSummary,
                style: textStyle.labelMedium?.copyWith(
                  color: AppColors.walnutGold,
                ),
              ),
            ],
          ),
        ),

        CustomFilledButton(
          width: double.infinity,
          backgroundColor: AppColors.lagoonTeal,
          onPressed: controller.searchRooms,
          child: Text(
            controller.roomPreselected.value ? 'Continue' : 'Search Rooms',
          ),
        ),
      ],
    );
  }
}
