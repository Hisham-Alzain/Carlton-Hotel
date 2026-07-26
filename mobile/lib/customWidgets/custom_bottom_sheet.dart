import 'package:carlton/customWidgets/custom_containers.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';

/// The shared shell for every modal sheet in the app: header (title, optional
/// subtitle, optional close button), a scrolling [child], and optional
/// [actions] pinned to the bottom. Call sites supply only the content.
///
/// Background, top radius and drag handle all come from
/// `ThemeData.bottomSheetTheme` — deliberately *not* re-declared here, so the
/// theme stays the single source of truth for how a sheet looks.
class CustomBottomSheet extends StatelessWidget {
  final String? title;
  final String? subtitle;
  final bool showClose;
  final Widget child;

  /// Pinned footer. Stays put while [child] scrolls, and sits above the
  /// system navigation bar.
  final Widget? actions;

  final double heightFactor;

  /// Wrap [child] in a scroll view. Set false when the content already scrolls
  /// itself (e.g. [RoomDetailsContent]) — nesting two scrollables breaks the
  /// drag-to-dismiss gesture.
  final bool scrollable;

  const CustomBottomSheet({
    required this.child,
    this.title,
    this.subtitle,
    this.showClose = true,
    this.actions,
    this.heightFactor = 0.9,
    this.scrollable = true,
    super.key,
  });

  /// Present [child] as a modal sheet. Returns whatever `Get.back(result: …)`
  /// passes back, or null if the sheet was dismissed.
  ///
  /// [context] defaults to `Get.context!` so controllers can open sheets
  /// without holding one.
  static Future<T?> show<T>({
    required Widget child,
    String? title,
    String? subtitle,
    bool showClose = true,
    Widget? actions,
    bool isDismissible = true,
    bool enableDrag = true,
    double heightFactor = 0.9,
    bool scrollable = true,
    BuildContext? context,
  }) {
    return showModalBottomSheet<T>(
      context: context ?? Get.context!,
      // Required both to grow past the default ~50% cap and for the keyboard
      // push to work at all.
      isScrollControlled: true,
      isDismissible: isDismissible,
      enableDrag: enableDrag,
      // Guards the top/left/right only — the bottom is handled in build().
      useSafeArea: true,
      builder: (_) => CustomBottomSheet(
        title: title,
        subtitle: subtitle,
        showClose: showClose,
        actions: actions,
        heightFactor: heightFactor,
        scrollable: scrollable,
        child: child,
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final media = MediaQuery.of(context);
    final textStyle = Get.textTheme;
    final hasHeader = title != null || subtitle != null || showClose;

    return ConstrainedBox(
      constraints: BoxConstraints(maxHeight: media.size.height * heightFactor),
      child: Padding(
        // `viewInsets.bottom` is the keyboard, `viewPadding.bottom` is the
        // Android nav/gesture bar. It has to be viewPadding rather than
        // padding: the latter collapses to 0 the moment the keyboard opens.
        padding: EdgeInsets.only(
          bottom: media.viewInsets.bottom + media.viewPadding.bottom,
        ),
        child: Column(
          spacing: 10,
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            if (hasHeader)
              Padding(
                padding: const EdgeInsets.all(10),
                child: Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Expanded(
                      child: Column(
                        mainAxisSize: MainAxisSize.min,
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          if (title != null)
                            Text(
                              title!,
                              style: textStyle.titleMedium?.copyWith(
                                fontFamily: 'Plus Jakarta Sans',
                                fontWeight: FontWeight.w600,
                                color: AppColors.inkBlack,
                              ),
                            ),
                          if (subtitle != null) ...[
                            Text(
                              subtitle!,
                              style: textStyle.labelMedium?.copyWith(
                                fontFamily: 'DM Sans',
                                color: AppColors.taupeBrown,
                              ),
                            ),
                          ],
                        ],
                      ),
                    ),
                    if (showClose)
                      PillContainer(
                        height: 30,
                        width: 30,
                        padding: EdgeInsets.zero,
                        backgroundColor: AppColors.whisperGrey,
                        child: IconButton(
                          onPressed: () => Get.back(),
                          padding: EdgeInsets.zero,
                          iconSize: 16,
                          visualDensity: VisualDensity.compact,
                          icon: const Icon(
                            Icons.close,
                            color: AppColors.taupeBrown,
                          ),
                        ),
                      ),
                  ],
                ),
              ),
            Flexible(
              child: scrollable
                  ? SingleChildScrollView(
                      padding: const EdgeInsets.all(10),
                      child: child,
                    )
                  : child,
            ),
            if (actions != null)
              Padding(padding: const EdgeInsets.all(10), child: actions),
          ],
        ),
      ),
    );
  }
}
