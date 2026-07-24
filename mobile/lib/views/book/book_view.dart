import 'package:carlton/controllers/booking/booking_flow_controller.dart';
import 'package:carlton/customWidgets/custom_containers.dart';
import 'package:carlton/customWidgets/custom_counter_field.dart';
import 'package:carlton/customWidgets/custom_date_box.dart';
import 'package:carlton/customWidgets/custom_date_range_calendar.dart';
import 'package:carlton/customWidgets/custom_filled_button.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:intl/intl.dart';
import 'package:smooth_page_indicator/smooth_page_indicator.dart';

/// Book tab — the date/guest planning editor. The surrounding `MainView` shell
/// supplies the app bar and bottom nav; the draft is reset each time the tab is
/// opened (see `MainController.changeTab`).
class BookView extends StatelessWidget {
  const BookView({super.key});

  @override
  Widget build(BuildContext context) {
    final TextTheme textStyle = Get.textTheme;

    ///TODO: make date format expension
    final f = DateFormat('MMM d, yyyy');
    return GetBuilder<BookingFlowController>(
      builder: (c) => Padding(
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

              Row(
                spacing: 10,
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

              //TODO: check if there a better widget
              CustomDateRangeCalendar(
                firstDay: c.firstDay,
                lastDay: c.lastDay,
                focusedDay: c.focusedDay,
                rangeStart: c.rangeStart,
                rangeEnd: c.rangeEnd,
                onRangeSelected: c.onRangeSelected,
                onPageChanged: c.onPageChanged,
              ),

              //TODO: make sure it does not go negative
              CustomCounterField(
                title: 'Adults',
                subtitle: 'Ages 18+',
                value: c.adults,
                min: 1,
                max: 10,
                onChanged: c.setAdults,
              ),

              CustomCounterField(
                title: 'Children',
                subtitle: 'Ages 0–17',
                value: c.children,
                max: 10,
                onChanged: c.setChildren,
              ),

              PillContainer(
                padding: const EdgeInsets.all(10),
                backgroundColor: AppColors.cream,
                child: Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    Flexible(
                      child: Text(
                        c.dateSummary,
                        style: textStyle.labelMedium?.copyWith(
                          fontFamily: 'DM Sans',
                          color: AppColors.inkBlack,
                        ),
                      ),
                    ),
                    Text(
                      c.guestSummary,
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
                onPressed: c.searchRooms,
                child: Text(c.roomPreselected ? 'Continue' : 'Search Rooms'),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
