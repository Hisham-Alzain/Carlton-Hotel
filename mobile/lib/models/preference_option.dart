import 'package:flutter/material.dart';

/// One selectable option in a preference picker (bed type, mattress, pillow,
/// language, currency). Rendered as a row with an exported Figma SVG
/// ([iconAsset]), a text mark ([symbol]) or a Material [icon] fallback, in
/// that order.
class PreferenceOption {
  final String id;
  final String label;
  final String? iconAsset;

  /// A currency's own sign (`$`, `£S`, `₺`), drawn in place of an icon.
  final String? symbol;
  final IconData? icon;

  const PreferenceOption({
    required this.id,
    required this.label,
    this.iconAsset,
    this.symbol,
    this.icon,
  });
}
