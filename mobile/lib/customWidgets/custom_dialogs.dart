import 'package:carlton/customWidgets/custom_elevated_button.dart';
import 'package:carlton/customWidgets/custom_text_field.dart';
import 'package:carlton/enums/enums.dart';
import 'package:carlton/l10n/app_translations.dart';
import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_svg/flutter_svg.dart';
import 'package:get/get.dart';
import 'package:url_launcher/url_launcher.dart';

// ─── Type → Color/Icon lookup tables ──────────────────────────────────────────

final _kDefaultColors = {
  AppDialogType.success: Colors.green.shade900,
  AppDialogType.error: Colors.red.shade900,
  AppDialogType.warning: Colors.yellow.shade900,
  AppDialogType.info: Colors.blueGrey, // replace with your brand color
  AppDialogType.confirmation: Colors.red.shade900,
  AppDialogType.destructive: Colors.red.shade900,
};

const _kDefaultIcons = {
  AppDialogType.success: Icons.check_circle_outline,
  AppDialogType.error: Icons.error_outline,
  AppDialogType.warning: Icons.warning_amber_rounded,
  AppDialogType.info: Icons.info_outline,
  AppDialogType.confirmation: Icons.question_mark_outlined,
  AppDialogType.destructive: Icons.delete,
};

// ─── CustomDialogs ─────────────────────────────────────────────────────────────

class CustomDialogs {
  CustomDialogs._();

  static void _close() {
    if (Get.isDialogOpen ?? false) Get.back();
  }

  // ── Core builder ────────────────────────────────────────────────────────────

  static Future<T?> _showDialog<T>({
    required String title,
    required AppDialogType type,
    String? message,
    dynamic icon,
    Color? accentColor,
    Widget? body,
    String? confirmationText,
    String? cancellationText,
    VoidCallback? onConfirm,
    VoidCallback? onCancel,
    bool barrierDismissible = false,
    bool showActions = true,
    bool showIcon = true,
    bool preventBack = false, // ← new
  }) {
    final color = accentColor ?? _kDefaultColors[type]!;

    return Get.dialog<T>(
      PopScope(
        canPop: !preventBack,
        child: Center(
          child: Padding(
            padding: const EdgeInsets.all(10),
            child: TweenAnimationBuilder<double>(
              tween: Tween(begin: 0.95, end: 1.0),
              duration: const Duration(milliseconds: 200),
              curve: Curves.easeOutCubic,
              builder: (_, value, child) =>
                  Transform.scale(scale: value, child: child),
              child: Material(
                // color: AppColors.backgroundColor,
                borderRadius: BorderRadius.circular(0),
                child: ConstrainedBox(
                  constraints: const BoxConstraints(
                    minWidth: 300,
                    maxWidth: 320,
                  ),
                  child: Padding(
                    padding: const EdgeInsets.all(10),
                    child: Column(
                      spacing: 10,
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        if (showIcon) _buildIconBadge(icon, type, color),
                        Text(
                          title,
                          textAlign: TextAlign.center,
                          style: Get.textTheme.titleMedium?.copyWith(
                            fontSize: 18,
                            fontWeight: FontWeight.w700,
                            color: color,
                          ),
                        ),
                        if (message != null)
                          Text(
                            message,
                            textAlign: TextAlign.center,
                            style: Get.textTheme.labelLarge,
                          ),
                        ?body,
                        if (showActions)
                          _buildActions(
                            color: color,
                            confirmationText: confirmationText,
                            cancellationText: cancellationText,
                            onConfirm: onConfirm,
                            onCancel: onCancel,
                          ),
                      ],
                    ),
                  ),
                ),
              ),
            ),
          ),
        ),
      ),
      barrierDismissible: barrierDismissible,
    );
  }

  // ── Private helpers ─────────────────────────────────────────────────────────

  static Widget _buildIconBadge(dynamic icon, AppDialogType type, Color color) {
    return Container(
      width: 50,
      height: 50,
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.12),
        shape: BoxShape.circle,
      ),
      child: Center(child: _buildIcon(icon, type, color)),
    );
  }

  static Widget _buildIcon(dynamic icon, AppDialogType type, Color color) {
    if (icon is IconData) return Icon(icon, color: color, size: 30);
    if (icon is String) {
      return SvgPicture.asset(
        icon,
        colorFilter: ColorFilter.mode(color, BlendMode.srcIn),
        width: 30,
      );
    }
    return Icon(_kDefaultIcons[type]!, color: color, size: 30);
  }

  static Widget _buildActions({
    required Color color,
    String? confirmationText,
    String? cancellationText,
    VoidCallback? onConfirm,
    VoidCallback? onCancel,
  }) {
    return Row(
      mainAxisAlignment: MainAxisAlignment.center,
      spacing: 10,
      children: [
        Flexible(
          child: CustomElevatedButton(
            width: Get.width / 2,
            backgroundColor: color,
            foregroundColor: Colors.white,
            onPressed: () {
              _close();
              onCancel?.call();
            },
            child: Text(cancellationText ?? AppTranslations.cancel),
          ),
        ),
        if (onConfirm != null)
          Flexible(
            child: CustomElevatedButton(
              width: Get.width / 2,
              backgroundColor: Colors.green,
              foregroundColor: Colors.white,
              onPressed: () {
                _close();
                onConfirm.call();
              },
              child: Text(confirmationText ?? AppTranslations.confirm),
            ),
          ),
      ],
    );
  }

  // ── Public API ───────────────────────────────────────────────────────────────

  static void showLoadingDialog() {
    _showDialog(
      type: AppDialogType.info,
      title: AppTranslations.loading,
      body: const Padding(
        padding: EdgeInsets.all(10),
        child: CircularProgressIndicator(),
      ),
      showActions: false,
      showIcon: false,
    );
  }

  static void showProgressDialog({
    required String title,
    required RxDouble progress,
    IconData? icon,
    CancelToken? cancelToken,
  }) {
    _showDialog(
      type: AppDialogType.info,
      title: title,
      icon: icon,
      showActions: false,
      body: Obx(
        () => Column(
          spacing: 10,
          children: [
            LinearProgressIndicator(
              value: progress.value,
              minHeight: 10,
              borderRadius: BorderRadius.circular(10),
            ),
            Text('${(progress.value * 100).toStringAsFixed(0)}%'),
            if (cancelToken != null)
              TextButton(
                onPressed: () {
                  cancelToken.cancel();
                  _close();
                },
                child: Text(AppTranslations.cancel),
              ),
          ],
        ),
      ),
    );
  }

  static void showErrorDialog({
    String? errorTitle,
    String? message,
    dynamic icon,
    Color? color,
  }) {
    _showDialog(
      type: AppDialogType.error,
      title: errorTitle ?? AppTranslations.error,
      message: message,
      icon: icon,
      accentColor: color,
      confirmationText: AppTranslations.cancel,
      barrierDismissible: true,
    );
  }

  static Future<void> showSuccessDialog({
    String? text,
    Duration duration = const Duration(seconds: 2),
    dynamic icon,
    Color? color,
  }) async {
    _showDialog(
      type: AppDialogType.success,
      title: AppTranslations.success,
      message: text,
      icon: icon,
      accentColor: color,
      showActions: false,
    );
    await Future.delayed(duration);
    _close();
  }

  static void showSessionExpiredDialog({Color? color}) {
    _showDialog(
      type: AppDialogType.warning,
      title: AppTranslations.sessionExpired,
      message: AppTranslations.pleaseLoginAgain,
      accentColor: color,
      confirmationText: AppTranslations.submit,
      barrierDismissible: false,
    );
  }

  static void showConfirmationDialog({
    AppDialogType? type,
    required String title,
    required String text,
    VoidCallback? onPressed,
    dynamic icon,
    Color? color,
  }) {
    _showDialog(
      type: type ?? AppDialogType.confirmation,
      title: title,
      message: text,
      icon: icon,
      accentColor: color,
      onConfirm: onPressed,
      onCancel: () {},
      confirmationText: AppTranslations.yes,
      cancellationText: AppTranslations.no,
    );
  }

  static void showCancelReasonDialog({
    required String title,
    required String hintText,
    required void Function(String? reason) onConfirm,
    dynamic icon,
    Color? color,
  }) {
    final controller = TextEditingController();
    _showDialog(
      type: AppDialogType.destructive,
      title: title,
      icon: icon ?? Icons.delete,
      accentColor: color,
      onCancel: () {},
      onConfirm: () => onConfirm(
        controller.text.trim().isEmpty ? null : controller.text.trim(),
      ),

      body: Padding(
        padding: const EdgeInsets.all(10),
        child: CustomTextField(
          controller: controller,
          textInputType: TextInputType.multiline,
          obsecureText: false,
          prefixIcon: Icons.edit_note,
          hintText: hintText,
          maxLines: 3,
        ),
      ),
    );
  }

  static void showUpdateDialog({
    required String title,
    required String message,
    required String storeUrl,
    required bool forceUpdate,
  }) {
    _showDialog(
      type: AppDialogType.info,
      title: title.isEmpty ? AppTranslations.updateAvailable : title,
      message: message.isEmpty ? null : message,
      icon: Icons.system_update,
      confirmationText: AppTranslations.updateNow,
      cancellationText: AppTranslations.later,
      onConfirm: () => _launchStore(storeUrl),
      onCancel: forceUpdate ? null : () {},
      barrierDismissible: !forceUpdate,
      preventBack: forceUpdate,
    );
  }

  static Future<void> _launchStore(String url) async {
    if (url.isEmpty) return;
    final uri = Uri.tryParse(url);
    if (uri == null) return;
    await launchUrl(uri, mode: LaunchMode.externalApplication);
  }
}
