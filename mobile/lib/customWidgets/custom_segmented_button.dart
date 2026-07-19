import 'package:carlton/models/segement_item.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:flutter_svg/svg.dart';

class CustomSegmentedButton extends StatelessWidget {
  final List<SegmentItem> segments;
  final int selectedIndex;
  final ValueChanged<int> onChanged;
  final double height;

  /// Per-instance overrides — null falls through to [segmentedButtonTheme].
  final Color? backgroundColor;
  final Color? foregroundColor;
  final Color? selectedBackgroundColor;
  final Color? selectedForegroundColor;
  final BorderSide? side;
  final OutlinedBorder? shape;

  /// Wraps the row in a padded, rounded track behind the segments.
  /// Null = no track (plain adjoined-outline look).
  final Color? trackColor;

  const CustomSegmentedButton({
    required this.segments,
    required this.selectedIndex,
    required this.onChanged,
    this.height = 40,
    this.backgroundColor,
    this.foregroundColor,
    this.selectedBackgroundColor,
    this.selectedForegroundColor,
    this.side,
    this.shape,
    this.trackColor,
    super.key,
  });

  const CustomSegmentedButton.track({
    required this.segments,
    required this.selectedIndex,
    required this.onChanged,
    this.height = 60,
    super.key,
  }) : backgroundColor = Colors.transparent,
       foregroundColor = AppColors.textSecondary,
       selectedBackgroundColor = Colors.white,
       selectedForegroundColor = AppColors.primary,
       side = BorderSide.none,
       shape = const RoundedRectangleBorder(
         borderRadius: BorderRadius.all(Radius.circular(10)),
       ),
       trackColor = AppColors.cardBg;

  @override
  Widget build(BuildContext context) {
    final button = SegmentedButton<int>(
      segments: List.generate(
        segments.length,
        (i) => ButtonSegment(
          value: i,
          label: Padding(
            padding: const EdgeInsets.symmetric(horizontal: 10),
            child: Text(segments[i].label),
          ),
          icon: _icon(segments[i]),
        ),
      ),
      selected: {selectedIndex},
      showSelectedIcon: false,
      emptySelectionAllowed: false,
      multiSelectionEnabled: false,
      onSelectionChanged: (selection) => onChanged(selection.first),
      style: SegmentedButton.styleFrom(
        backgroundColor: backgroundColor,
        foregroundColor: foregroundColor,
        selectedBackgroundColor: selectedBackgroundColor,
        selectedForegroundColor: selectedForegroundColor,
        side: side,
        shape: shape,
        minimumSize: Size(0, trackColor != null ? height - 8 : height),
      ),
    );

    if (trackColor == null) return button;

    return Container(
      height: height,
      padding: const EdgeInsets.all(5),
      decoration: BoxDecoration(
        color: trackColor,
        borderRadius: BorderRadius.circular(8),
      ),
      child: button,
    );
  }

  Widget? _icon(SegmentItem segment) {
    if (segment.iconData != null) {
      return Icon(segment.iconData, size: 14);
    } else if (segment.iconPath != null) {
      return SvgPicture.asset(segment.iconPath!, height: 14, width: 14);
    }
    return null;
  }
}
