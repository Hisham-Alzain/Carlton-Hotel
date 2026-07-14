import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:flutter_svg/flutter_svg.dart';

class SegmentItem {
  final String label;
  final String? iconPath;
  final IconData? iconData;

  const SegmentItem({required this.label, this.iconPath, this.iconData});
}

enum _SegmentStyle { outline, track }

class CustomSegmentedToggle extends StatelessWidget {
  static const _kOutlineRadius = Radius.circular(8);

  final List<SegmentItem> segments;
  final int selectedIndex;
  final ValueChanged<int> onChanged;
  final double height;
  final _SegmentStyle _style;

  final Color selectedContentColor;
  final Color unselectedContentColor;

  final Color selectedFillColor;

  final Color unselectedBorderColor;

  final Color? trackColor;

  const CustomSegmentedToggle.outline({
    required this.segments,
    required this.selectedIndex,
    required this.onChanged,
    this.height = 39,
    this.selectedFillColor = AppColors.cream,
    this.selectedContentColor = AppColors.ink,
    this.unselectedContentColor = Colors.white,
    this.unselectedBorderColor = Colors.white,
    super.key,
  }) : _style = _SegmentStyle.outline,
       trackColor = null;

  const CustomSegmentedToggle.track({
    required this.segments,
    required this.selectedIndex,
    required this.onChanged,
    this.height = 48,
    this.selectedContentColor = AppColors.primary,
    this.unselectedContentColor = AppColors.textSecondary,
    super.key,
  }) : _style = _SegmentStyle.track,
       selectedFillColor = Colors.white,
       unselectedBorderColor = Colors.transparent,
       trackColor = AppColors.cardBg;

  @override
  Widget build(BuildContext context) {
    final row = Row(
      children: [
        for (var i = 0; i < segments.length; i++) Expanded(child: _segment(i)),
      ],
    );
    if (_style == _SegmentStyle.outline) return row;
    return Container(
      padding: const EdgeInsets.all(4),
      height: height,
      decoration: BoxDecoration(
        color: trackColor,
        borderRadius: BorderRadius.circular(12),
      ),
      child: row,
    );
  }

  Widget _segment(int index) {
    final segment = segments[index];
    final selected = selectedIndex == index;
    final content = selected ? selectedContentColor : unselectedContentColor;

    if (_style == _SegmentStyle.track) {
      return GestureDetector(
        onTap: () => onChanged(index),
        behavior: HitTestBehavior.opaque,
        child: Container(
          alignment: Alignment.center,
          padding: const EdgeInsets.symmetric(horizontal: 10),
          decoration: BoxDecoration(
            color: selected ? selectedFillColor : Colors.transparent,
            borderRadius: BorderRadius.circular(9),
            boxShadow: selected
                ? const [
                    BoxShadow(
                      color: Color(0x1A000000),
                      blurRadius: 3,
                      offset: Offset(0, 1),
                    ),
                  ]
                : null,
          ),
          // FittedBox shrinks the icon+label as one unit so a long label
          // never cramps on narrow screens.
          child: FittedBox(
            fit: BoxFit.scaleDown,
            child: Row(
              mainAxisSize: MainAxisSize.min,
              spacing: 7,
              children: [
                _icon(segment, content),
                Text(
                  segment.label,
                  maxLines: 1,
                  style: TextStyle(
                    fontSize: 13,
                    fontWeight: selected ? FontWeight.w600 : FontWeight.w400,
                    color: content,
                  ),
                ),
              ],
            ),
          ),
        ),
      );
    }

    final isFirst = index == 0;
    final isLast = index == segments.length - 1;
    return GestureDetector(
      onTap: () => onChanged(index),
      behavior: HitTestBehavior.opaque,
      child: Container(
        height: height,
        alignment: Alignment.center,
        decoration: BoxDecoration(
          color: selected ? selectedFillColor : Colors.transparent,
          borderRadius: BorderRadiusDirectional.horizontal(
            start: isFirst ? _kOutlineRadius : Radius.zero,
            end: isLast ? _kOutlineRadius : Radius.zero,
          ),
          border: selected
              ? null
              : Border.all(color: unselectedBorderColor, width: 1.4),
        ),
        child: Text(
          segment.label,
          textAlign: TextAlign.center,
          style: TextStyle(
            fontSize: 13,
            fontWeight: FontWeight.w500,
            color: content,
          ),
        ),
      ),
    );
  }

  Widget _icon(SegmentItem segment, Color color) {
    if (segment.iconPath != null) {
      return SvgPicture.asset(
        segment.iconPath!,
        width: 16,
        height: 16,
        colorFilter: ColorFilter.mode(color, BlendMode.srcIn),
      );
    }
    return Icon(segment.iconData, size: 16, color: color);
  }
}
