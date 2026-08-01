import 'package:carlton/customWidgets/custom_elevated_button.dart';
import 'package:carlton/customWidgets/custom_indicators.dart';
import 'package:carlton/customWidgets/custom_text_field.dart';
import 'package:carlton/enums/enums.dart';
import 'package:carlton/l10n/app_translations.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_svg/flutter_svg.dart';
import 'package:get/get.dart';
import 'package:url_launcher/url_launcher.dart';

// ─── Type → Color/Icon lookup tables ──────────────────────────────────────────

final _kDefaultColors = {
  AppDialogType.success: AppColors.primary,
  AppDialogType.error: AppColors.brickRed,
  AppDialogType.warning: AppColors.bronzeGold,
  AppDialogType.info: AppColors.primary,
  AppDialogType.confirmation: AppColors.brickRed,
  AppDialogType.destructive: AppColors.brickRed,
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
    final resolvedAccentColor = accentColor ?? _kDefaultColors[type]!;

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
              builder: (_, scaleFactor, child) =>
                  Transform.scale(scale: scaleFactor, child: child),
              child: Material(
                // color: AppColors.backgroundColor,
                borderRadius: BorderRadius.circular(10),
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
                        if (showIcon)
                          _buildIconBadge(icon, type, resolvedAccentColor),
                        Text(
                          title,
                          textAlign: TextAlign.center,
                          style: Get.textTheme.titleMedium?.copyWith(
                            fontSize: 18,
                            fontWeight: FontWeight.w700,
                            color: resolvedAccentColor,
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
                            accentColor: resolvedAccentColor,
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

  static Widget _buildIconBadge(
    dynamic icon,
    AppDialogType type,
    Color accentColor,
  ) {
    return Container(
      width: 50,
      height: 50,
      decoration: BoxDecoration(
        color: accentColor.withValues(alpha: 0.12),
        shape: BoxShape.circle,
      ),
      child: Center(child: _buildIcon(icon, type, accentColor)),
    );
  }

  /// [icon] is either an [IconData] or an SVG asset path; falling back to the
  /// dialog type's default glyph when it is neither.
  static Widget _buildIcon(
    dynamic icon,
    AppDialogType type,
    Color accentColor,
  ) {
    if (icon is IconData) return Icon(icon, color: accentColor, size: 30);
    if (icon is String) {
      return SvgPicture.asset(
        icon,
        colorFilter: ColorFilter.mode(accentColor, BlendMode.srcIn),
        width: 30,
      );
    }
    return Icon(_kDefaultIcons[type]!, color: accentColor, size: 30);
  }

  static Widget _buildActions({
    required Color accentColor,
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
            backgroundColor: accentColor,
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
      // The dialog Material has no explicit colour (see _showDialog), so it
      // sits on the light theme surface — the indicator's default white logo
      // would be invisible. AppColors.primary matches what the bare
      // CircularProgressIndicator picked up from progressIndicatorTheme.
      body: const Padding(
        padding: EdgeInsets.all(10),
        child: SpinningIconIndicator(size: 50, color: AppColors.primary),
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
    Color? accentColor,
  }) {
    _showDialog(
      type: AppDialogType.error,
      title: errorTitle ?? AppTranslations.error,
      message: message,
      icon: icon,
      accentColor: accentColor,
      confirmationText: AppTranslations.cancel,
      barrierDismissible: true,
    );
  }

  static Future<void> showSuccessDialog({
    String? message,
    Duration duration = const Duration(seconds: 2),
    dynamic icon,
    Color? accentColor,
  }) async {
    _showDialog(
      type: AppDialogType.success,
      title: AppTranslations.success,
      message: message,
      icon: icon,
      accentColor: accentColor,
      showActions: false,
    );
    await Future.delayed(duration);
    _close();
  }

  static void showSessionExpiredDialog({Color? accentColor}) {
    _showDialog(
      type: AppDialogType.warning,
      title: AppTranslations.sessionExpired,
      message: AppTranslations.pleaseLoginAgain,
      accentColor: accentColor,
      confirmationText: AppTranslations.submit,
      barrierDismissible: false,
    );
  }

  static void showConfirmationDialog({
    AppDialogType? type,
    required String title,
    required String message,
    VoidCallback? onPressed,
    dynamic icon,
    Color? accentColor,
  }) {
    _showDialog(
      type: type ?? AppDialogType.confirmation,
      title: title,
      message: message,
      icon: icon,
      accentColor: accentColor,
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
    Color? accentColor,
  }) {
    final reasonController = TextEditingController();
    _showDialog(
      type: AppDialogType.destructive,
      title: title,
      icon: icon ?? Icons.delete,
      accentColor: accentColor,
      onCancel: () {},
      onConfirm: () => onConfirm(
        reasonController.text.trim().isEmpty
            ? null
            : reasonController.text.trim(),
      ),

      body: Padding(
        padding: const EdgeInsets.all(10),
        child: CustomTextField(
          controller: reasonController,
          textInputType: TextInputType.multiline,
          obscureText: false,
          prefixIcon: Icons.edit_note,
          hintText: hintText,
          maxLines: 3,
          fillColor: AppColors.whisperGrey,
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
