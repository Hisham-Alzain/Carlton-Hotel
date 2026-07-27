import 'package:flutter/material.dart';

/// One selectable option in a preference picker (bed type, mattress, pillow,
/// language, currency). Rendered as a row with either an exported Figma SVG
/// ([iconAsset]) or a Material [icon] fallback.
class PreferenceOption {
  final String id;
  final String label;
  final String? iconAsset;
  final IconData? icon;

  const PreferenceOption({
    required this.id,
    required this.label,
    this.iconAsset,
    this.icon,
  });
}
