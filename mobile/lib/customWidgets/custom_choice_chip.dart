import 'package:flutter/material.dart';

class CustomChoiceChip extends StatelessWidget {
  final String label;
  final bool isSelected;
  final VoidCallback onSelected;
  final Color? selectedBgColor;
  final Color? selectedTextColor;
  final Color? unselectedBgColor;
  final Color? unselectedTextColor;

  const CustomChoiceChip({
    super.key,
    required this.label,
    required this.isSelected,
    required this.onSelected,
    this.selectedBgColor,
    this.selectedTextColor,
    this.unselectedBgColor,
    this.unselectedTextColor,
  });

  @override
  Widget build(BuildContext context) {
    // Access the global chip theme
    final theme = Theme.of(context).chipTheme;

    return ChoiceChip(
      label: Text(label),
      selected: isSelected,
      onSelected: (_) => onSelected(),

      // If parameter is null, it uses the theme's defined color
      selectedColor: selectedBgColor ?? theme.selectedColor,
      backgroundColor: unselectedBgColor ?? theme.backgroundColor,

      labelStyle: TextStyle(
        // Merges theme font settings with dynamic color/weight
        inherit: true,
        color: isSelected
            ? (selectedTextColor ?? Colors.white)
            : (unselectedTextColor ?? theme.labelStyle?.color),
        fontWeight: isSelected ? FontWeight.bold : theme.labelStyle?.fontWeight,
      ),
    );
  }
}
