import 'package:carlton/enums/enums.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:flutter_svg/flutter_svg.dart';
import 'package:get/get.dart';

// ─── Type config ───────────────────────────────────────────────────────────────

class _SnackbarConfig {
  const _SnackbarConfig({
    required this.bgColor,
    required this.accentColor,
    required this.icon,
  });

  final Color bgColor;
  final Color accentColor;
  final IconData icon;
}

final _kConfig = {
  SnackbarType.success: _SnackbarConfig(
    bgColor: Colors.green.shade50,
    accentColor: Colors.green.shade900, // Colors.green.shade800 equivalent
    icon: Icons.check_circle_outline,
  ),
  SnackbarType.error: _SnackbarConfig(
    bgColor: Colors.red.shade50,
    accentColor: Colors.red.shade900, // Colors.red.shade800 equivalent
    icon: Icons.error_outline,
  ),
  SnackbarType.warning: _SnackbarConfig(
    bgColor: Colors.yellow.shade50,
    accentColor: Colors.yellow.shade900, // Colors.orange.shade900 equivalent
    icon: Icons.warning_amber_rounded,
  ),
  SnackbarType.info: _SnackbarConfig(
    bgColor: Colors.white,
    accentColor: AppColors.primary,
    icon: Icons.info_outline,
  ),
};

// ─── CustomSnackbars ───────────────────────────────────────────────────────────

class CustomSnackbars {
  CustomSnackbars._();

  static void _show({
    required SnackbarType type,
    required String message,
    dynamic icon,
    String? actionText,
    VoidCallback? onAction,
    Duration duration = const Duration(seconds: 1),
  }) {
    final config = _kConfig[type]!;

    Get.rawSnackbar(
      messageText: Text(
        message,
        style: Get.textTheme.labelLarge?.copyWith(color: config.accentColor),
      ),
      icon: Padding(
        padding: const EdgeInsets.all(10),
        child: _buildIcon(icon, config),
      ),
      mainButton: actionText != null
          ? TextButton(
              onPressed: () {
                if (Get.isSnackbarOpen) Get.back();
                onAction?.call();
              },
              child: Text(
                actionText.toUpperCase(),
                style: Get.textTheme.labelLarge?.copyWith(
                  color: config.accentColor,
                  fontWeight: FontWeight.w700,
                ),
              ),
            )
          : null,
      backgroundColor: config.bgColor,
      borderRadius: 0,
      margin: const EdgeInsets.all(10),
      padding: const EdgeInsets.all(10),
      snackPosition: SnackPosition.TOP,
      duration: duration,
      boxShadows: [
        BoxShadow(
          color: config.accentColor,
          blurRadius: 0, // hard edge, no softness
          offset: const Offset(4, 4), // same as button's left: 4, top: 4
        ),
      ],
    );
  }

  static Widget _buildIcon(dynamic icon, _SnackbarConfig config) {
    final color = config.accentColor;
    if (icon is IconData) return Icon(icon, color: color);
    if (icon is String) {
      return SvgPicture.asset(
        icon,
        colorFilter: ColorFilter.mode(color, BlendMode.srcIn),
      );
    }
    return Icon(config.icon, color: color);
  }

  // ── Public API ───────────────────────────────────────────────────────────────

  static void showSuccess({
    required String message,
    String? actionText,
    VoidCallback? onAction,
    dynamic icon,
  }) {
    _show(
      type: SnackbarType.success,
      message: message,
      icon: icon,
      actionText: actionText,
      onAction: onAction,
    );
  }

  static void showError({
    required String message,
    String? actionText,
    VoidCallback? onAction,
    dynamic icon,
  }) {
    _show(
      type: SnackbarType.error,
      message: message,
      icon: icon,
      actionText: actionText,
      onAction: onAction,
      duration: const Duration(seconds: 2),
    );
  }

  static void showWarning({
    required String message,
    String? actionText,
    VoidCallback? onAction,
    dynamic icon,
  }) {
    _show(
      type: SnackbarType.warning,
      message: message,
      icon: icon,
      actionText: actionText,
      onAction: onAction,
    );
  }

  static void showInfo({
    required String message,
    String? actionText,
    VoidCallback? onAction,
    dynamic icon,
  }) {
    _show(
      type: SnackbarType.info,
      message: message,
      icon: icon,
      actionText: actionText,
      onAction: onAction,
    );
  }
}
