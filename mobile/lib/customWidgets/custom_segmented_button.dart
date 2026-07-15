import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';

class SegmentItem {
  final String label;
  final String? iconPath;
  final IconData? iconData;

  const SegmentItem({required this.label, this.iconPath, this.iconData});
}

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
    this.height = 48,
    super.key,
  }) : backgroundColor = Colors.transparent,
       foregroundColor = AppColors.textSecondary,
       selectedBackgroundColor = Colors.white,
       selectedForegroundColor = AppColors.primary,
       side = BorderSide.none,
       shape = const RoundedRectangleBorder(
         borderRadius: BorderRadius.all(Radius.circular(9)),
       ),
       trackColor = AppColors.cardBg;

  @override
  Widget build(BuildContext context) {
    final button = SegmentedButton<int>(
      segments: [
        for (var i = 0; i < segments.length; i++)
          ButtonSegment(
            value: i,
            label: Padding(
              padding: const EdgeInsets.all(15),
              child: Text(segments[i].label),
            ),
            icon: _icon(segments[i]),
          ),
      ],
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
      padding: const EdgeInsets.all(4),
      decoration: BoxDecoration(
        color: trackColor,
        borderRadius: BorderRadius.circular(12),
      ),
      child: button,
    );
  }

  Widget? _icon(SegmentItem segment) {
    if (segment.iconData != null) {
      return Icon(segment.iconData, size: 16);
    }
    // SegmentedButton's icon doesn't take a color param directly — it
    // inherits from foregroundColor/selectedForegroundColor automatically
    // via IconTheme, so SvgPicture needs no explicit colorFilter here.
    return null;
  }
}
