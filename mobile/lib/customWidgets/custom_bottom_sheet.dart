import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:flutter_svg/flutter_svg.dart';
import 'package:get/get.dart';

/// Reusable modal sheet for Receipt, Cancel Reservation, and Room Details.
///
/// Renders the drag handle + header (title, optional subtitle, optional close
/// button) the designs share, scrolls its [child], and pins optional [actions]
/// to the bottom. Use [CustomBottomSheet.show] to present it — it opens a
/// height-capped, scroll-controlled sheet with the design's 20px top radius.
class CustomBottomSheet extends StatelessWidget {
  final String? title;
  final String? subtitle;
  final bool showClose;
  final VoidCallback? onClose;
  final Widget child;
  final List<Widget>? actions;

  const CustomBottomSheet({
    required this.child,
    this.title,
    this.subtitle,
    this.showClose = true,
    this.onClose,
    this.actions,
    super.key,
  });

  /// Present [content] as a modal bottom sheet. Returns the value passed to
  /// `Get.back(result: ...)`, or null if dismissed.
  static Future<T?> show<T>({
    required Widget content,
    bool isDismissible = true,
    bool enableDrag = true,
  }) {
    return Get.bottomSheet<T>(
      content,
      isScrollControlled: true,
      isDismissible: isDismissible,
      enableDrag: enableDrag,
      backgroundColor: Colors.transparent,
    );
  }

  @override
  Widget build(BuildContext context) {
    final textTheme = Theme.of(context).textTheme;
    final maxHeight = MediaQuery.sizeOf(context).height * 0.9;

    return Container(
      constraints: BoxConstraints(maxHeight: maxHeight),
      decoration: const BoxDecoration(
        color: AppColors.surface,
        borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
      ),
      child: SafeArea(
        top: false,
        child: Padding(
          padding: const EdgeInsets.fromLTRB(24, 12, 24, 24),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Center(
                child: Container(
                  width: 36,
                  height: 4,
                  decoration: BoxDecoration(
                    color: AppColors.dragHandle,
                    borderRadius: BorderRadius.circular(2),
                  ),
                ),
              ),
              if (title != null) ...[
                const SizedBox(height: 20),
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Expanded(
                      child: Text(
                        title!,
                        style: textTheme.titleMedium?.copyWith(
                          fontSize: 17,
                          fontWeight: FontWeight.w600,
                          color: AppColors.navLabel,
                        ),
                      ),
                    ),
                    if (showClose)
                      InkWell(
                        onTap: onClose ?? () => Get.back<void>(),
                        borderRadius: BorderRadius.circular(8),
                        child: Container(
                          padding: const EdgeInsets.all(6),
                          decoration: BoxDecoration(
                            color: AppColors.neutralIconBg,
                            borderRadius: BorderRadius.circular(8),
                          ),
                          child: SvgPicture.asset(
                            'assets/icons/close.svg',
                            width: 16,
                            height: 16,
                            colorFilter: const ColorFilter.mode(
                              AppColors.navLabel,
                              BlendMode.srcIn,
                            ),
                          ),
                        ),
                      ),
                  ],
                ),
              ],
              if (subtitle != null) ...[
                const SizedBox(height: 4),
                Text(
                  subtitle!,
                  style: const TextStyle(
                    fontFamily: 'DM Sans',
                    fontSize: 13,
                    color: AppColors.textMuted,
                  ),
                ),
              ],
              const SizedBox(height: 16),
              Flexible(child: SingleChildScrollView(child: child)),
              if (actions != null && actions!.isNotEmpty) ...[
                const SizedBox(height: 16),
                for (var i = 0; i < actions!.length; i++) ...[
                  if (i > 0) const SizedBox(height: 12),
                  actions![i],
                ],
              ],
            ],
          ),
        ),
      ),
    );
  }
}
