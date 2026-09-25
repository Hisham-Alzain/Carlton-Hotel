import 'package:flutter/material.dart';

extension TextStyleExtensions on TextStyle {
  /// Wide letter-spacing for uppercase eyebrows and card numerals — the
  /// typographic device the Loyalty screen leans on — applied only in LTR.
  ///
  /// Arabic shapes its letters into connected forms, and tracking at these
  /// values (1–3) prises those connections apart into something that reads as
  /// a rendering fault. Passing null leaves the theme slot's own spacing
  /// intact rather than clearing it, so the Arabic build simply keeps the
  /// house default.
  TextStyle tracked(BuildContext context, double spacing) => copyWith(
    letterSpacing: Directionality.of(context) == TextDirection.rtl
        ? null
        : spacing,
  );
}
