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

  const CustomDateRangeCalendar({
    required this.firstDay,
    required this.lastDay,
    required this.focusedDay,
    required this.onRangeSelected,
    this.rangeStart,
    this.rangeEnd,
    this.onPageChanged,
    super.key,
  });

  Widget _cell(
    String day, {
    Color? backgroundColor,
    Color? borderColor,
    required Color textColor,
    double radius = 4,
    FontWeight weight = FontWeight.w400,
  }) {
    final TextTheme textStyle = Get.textTheme;
    return Center(
      child: Container(
        width: 50,
        height: 40,
        alignment: Alignment.center,
        decoration: backgroundColor == null && borderColor == null
            ? null
            : BoxDecoration(
                color: backgroundColor,
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
            color: textColor,
          ),
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final TextTheme textStyle = Get.textTheme;
    return Card(
      color: AppColors.white,
      shape: ContinuousRectangleBorder(
        borderRadius: BorderRadiusGeometry.circular(12),
        side: BorderSide(color: AppColors.black06, width: 1),
      ),
      elevation: 1,
      child: TableCalendar<void>(
        firstDay: firstDay,
        lastDay: lastDay,
        focusedDay: focusedDay,
        rangeStartDay: rangeStart,
        rangeEndDay: rangeEnd,
        rangeSelectionMode: RangeSelectionMode.enforced,
        calendarFormat: CalendarFormat.month,
        availableGestures: AvailableGestures.horizontalSwipe,
        rowHeight: 50,
        daysOfWeekHeight: 20,
        onRangeSelected: onRangeSelected,
        onPageChanged: onPageChanged,
        calendarStyle: const CalendarStyle(
          rangeHighlightColor: Colors.transparent,
        ),
        headerStyle: HeaderStyle(
          formatButtonVisible: false,
          titleCentered: true,
          leftChevronVisible: false,
          rightChevronVisible: false,
          titleTextStyle: textStyle.labelLarge!.copyWith(
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
            const weekdayInitials = ['S', 'M', 'T', 'W', 'T', 'F', 'S'];
            return Center(
              child: Text(
                weekdayInitials[day.weekday % 7],
                style: textStyle.labelSmall?.copyWith(
                  fontFamily: 'DM Sans',
                  color: AppColors.taupeBrown,
                ),
              ),
            );
          },
          defaultBuilder: (context, day, focused) =>
              _cell('${day.day}', textColor: AppColors.inkBlack),
          todayBuilder: (context, day, focused) =>
              _cell('${day.day}', textColor: AppColors.inkBlack),
          outsideBuilder: (context, day, focused) => const SizedBox.shrink(),
          disabledBuilder: (context, day, focused) => Opacity(
            opacity: 0.4,
            child: _cell('${day.day}', textColor: AppColors.inkBlack),
          ),
          rangeStartBuilder: (context, day, focused) => _cell(
            '${day.day}',
            backgroundColor: AppColors.primary,
            textColor: AppColors.white,
            radius: 8,
            weight: FontWeight.w600,
          ),
          rangeEndBuilder: (context, day, focused) => _cell(
            '${day.day}',
            backgroundColor: AppColors.primary,
            textColor: AppColors.white,
            radius: 8,
            weight: FontWeight.w600,
          ),
          withinRangeBuilder: (context, day, focused) => _cell(
            '${day.day}',
            backgroundColor: AppColors.primary08,
            textColor: AppColors.inkBlack,
          ),
        ),
      ),
    );
  }
}
