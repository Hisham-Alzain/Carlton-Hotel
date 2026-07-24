import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:table_calendar/table_calendar.dart';

class CustomDateRangeCalendar extends StatelessWidget {
  final DateTime firstDay;
  final DateTime lastDay;
  final DateTime focusedDay;
  final DateTime? rangeStart;
  final DateTime? rangeEnd;
  final void Function(DateTime? start, DateTime? end, DateTime focusedDay)
  onRangeSelected;
  final ValueChanged<DateTime>? onPageChanged;
  final bool showNavigation;

  const CustomDateRangeCalendar({
    required this.firstDay,
    required this.lastDay,
    required this.focusedDay,
    required this.onRangeSelected,
    this.rangeStart,
    this.rangeEnd,
    this.onPageChanged,
    this.showNavigation = false,
    super.key,
  });

  Widget _cell(
    String day, {
    Color? bg,
    Color? borderColor,
    required Color text,
    double radius = 4,
    FontWeight weight = FontWeight.w400,
  }) {
    final TextTheme textStyle = Get.textTheme;
    return Center(
      child: Container(
        width: 42,
        height: 32,
        alignment: Alignment.center,
        decoration: bg == null && borderColor == null
            ? null
            : BoxDecoration(
                color: bg,
                borderRadius: BorderRadius.circular(radius),
                border: borderColor == null
                    ? null
                    : Border.all(color: borderColor, width: 1.5),
              ),
        child: Text(
          day,
          style: textStyle.labelMedium?.copyWith(
            fontFamily: 'DM Sans',
            fontWeight: weight,
            color: text,
          ),
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final TextTheme textStyle = Get.textTheme;
    return Container(
      padding: const EdgeInsets.all(15.18),
      decoration: BoxDecoration(
        color: AppColors.white,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: AppColors.black06, width: 1.18),
        boxShadow: const [
          BoxShadow(
            color: AppColors.slateShadow04,
            blurRadius: 4,
            offset: Offset(0, 2),
          ),
        ],
      ),
      child: TableCalendar<void>(
        firstDay: firstDay,
        lastDay: lastDay,
        focusedDay: focusedDay,
        rangeStartDay: rangeStart,
        rangeEndDay: rangeEnd,
        rangeSelectionMode: RangeSelectionMode.enforced,
        calendarFormat: CalendarFormat.month,
        availableGestures: AvailableGestures.horizontalSwipe,
        rowHeight: 36,
        daysOfWeekHeight: 20,
        onRangeSelected: onRangeSelected,
        onPageChanged: onPageChanged,
        headerStyle: HeaderStyle(
          formatButtonVisible: false,
          titleCentered: true,
          leftChevronVisible: showNavigation,
          rightChevronVisible: showNavigation,
          headerPadding: const EdgeInsets.only(bottom: 12),
          titleTextStyle: textStyle.labelMedium!.copyWith(
            fontFamily: 'Plus Jakarta Sans',
            fontWeight: FontWeight.w600,
            color: AppColors.inkBlack,
          ),
        ),
        daysOfWeekStyle: DaysOfWeekStyle(
          weekdayStyle: textStyle.labelSmall!.copyWith(
            fontFamily: 'DM Sans',
            color: AppColors.taupeBrown,
          ),
          weekendStyle: textStyle.labelSmall!.copyWith(
            fontFamily: 'DM Sans',
            color: AppColors.taupeBrown,
          ),
        ),
        calendarBuilders: CalendarBuilders<void>(
          dowBuilder: (context, day) {
            const labels = ['S', 'M', 'T', 'W', 'T', 'F', 'S'];
            return Center(
              child: Text(
                labels[day.weekday % 7],
                style: textStyle.labelSmall?.copyWith(
                  fontFamily: 'DM Sans',
                  color: AppColors.taupeBrown,
                ),
              ),
            );
          },
          defaultBuilder: (context, day, focused) =>
              _cell('${day.day}', text: AppColors.inkBlack),
          todayBuilder: (context, day, focused) =>
              _cell('${day.day}', text: AppColors.inkBlack),
          outsideBuilder: (context, day, focused) => const SizedBox.shrink(),
          disabledBuilder: (context, day, focused) =>
              _cell('${day.day}', text: AppColors.pearlGrey),
          rangeStartBuilder: (context, day, focused) => _cell(
            '${day.day}',
            bg: AppColors.primary,
            text: AppColors.white,
            radius: 8,
            weight: FontWeight.w600,
          ),
          rangeEndBuilder: (context, day, focused) => _cell(
            '${day.day}',
            bg: AppColors.primary,
            text: AppColors.white,
            radius: 8,
            weight: FontWeight.w600,
          ),
          withinRangeBuilder: (context, day, focused) => _cell(
            '${day.day}',
            bg: AppColors.primary08,
            text: AppColors.inkBlack,
          ),
        ),
      ),
    );
  }
}
