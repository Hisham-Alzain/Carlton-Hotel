import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';

/// Drop shadows used by more than one surface. One-off shadows stay inline
/// beside the widget they belong to.
abstract final class AppShadows {
  /// Soft lift under white cards (booking summary, card form).
  static const card = BoxShadow(
    color: AppColors.black06,
    blurRadius: 12,
    offset: Offset(0, 2),
  );

  /// Tight shadow under banners and small panels.
  static const banner = BoxShadow(
    color: AppColors.pebbleGrey32,
    blurRadius: 4,
    offset: Offset(0, 2),
  );
}
