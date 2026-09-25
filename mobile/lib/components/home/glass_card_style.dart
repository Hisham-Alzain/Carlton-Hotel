import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';

/// Shared card geometry for the bordered "glass" cards on the pre-arrival
/// Home sections (Figma `2237:4237`). Both sit on the ghostWhite scaffold as
/// near-transparent glass — the white hairline and the soft shadow are what
/// actually draw the edge.
const glassCardRadius = 14.0;
const glassCardBorder = BorderSide(color: AppColors.white, width: 1.2);
const glassCardShadow = BoxShadow(
  color: AppColors.slateShadow04,
  blurRadius: 12,
  offset: Offset(0, 2),
);
