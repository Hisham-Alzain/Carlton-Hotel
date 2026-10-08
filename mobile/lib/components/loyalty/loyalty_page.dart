import 'package:carlton/customWidgets/custom_empty_placeholder.dart';
import 'package:carlton/customWidgets/custom_indicators.dart';
import 'package:carlton/customWidgets/custom_scaffold.dart';
import 'package:carlton/l10n/app_translations.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';

/// The shell every loyalty screen shares: an ivoryCream scaffold and app bar.
///
/// ivoryCream, not the theme's ghostWhite scaffold: every surface on these
/// screens is warm (cream banner, gold hairlines, teal card), and a cool
/// near-white behind them reads as a different screen showing through. The
/// app bar has to be told the same thing — appBarTheme still carries
/// ghostWhite, and the seam between the two is visible.
class LoyaltyScaffold extends StatelessWidget {
  final String title;
  final Widget body;

  const LoyaltyScaffold({required this.title, required this.body, super.key});

  @override
  Widget build(BuildContext context) {
    return CustomScaffold(
      backgroundColor: AppColors.ivoryCream,
      appBar: AppBar(
        backgroundColor: AppColors.ivoryCream,
        surfaceTintColor: AppColors.ivoryCream,
        title: Text(
          title,
          style: Get.theme.appBarTheme.titleTextStyle?.copyWith(
            color: AppColors.primary,
          ),
        ),
        iconTheme: const IconThemeData(color: AppColors.primary),
      ),
      body: body,
    );
  }
}

/// A paged loyalty list (rewards, vouchers) with its three states: loading,
/// failed with Retry, and loaded-but-empty. One frame's worth of values — the
/// view owns the `Obx` and passes them in.
class LoyaltyPagedList extends StatelessWidget {
  final bool loading;
  final bool hasError;
  final bool loadingMore;
  final int itemCount;
  final ScrollController scrollController;
  final VoidCallback onRetry;
  final IconData emptyIcon;
  final String emptyTitle;
  final String emptyBody;
  final IndexedWidgetBuilder itemBuilder;

  const LoyaltyPagedList({
    required this.loading,
    required this.hasError,
    required this.loadingMore,
    required this.itemCount,
    required this.scrollController,
    required this.onRetry,
    required this.emptyIcon,
    required this.emptyTitle,
    required this.emptyBody,
    required this.itemBuilder,
    super.key,
  });

  @override
  Widget build(BuildContext context) {
    if (loading && itemCount == 0) {
      return const Center(child: LogoLoadingIndicator(size: 50));
    }
    if (hasError && itemCount == 0) {
      return CustomEmptyPlaceholder.loadFailed(
        title: AppTranslations.loyaltyLoadFailed,
        subtitle: AppTranslations.checkConnectionShort,
        onRetry: onRetry,
      );
    }
    if (itemCount == 0) {
      return CustomEmptyPlaceholder(
        iconWidget: Icon(emptyIcon, size: 30, color: AppColors.bronzeGold),
        iconContainerColor: AppColors.antiqueGold09,
        title: emptyTitle,
        subtitle: emptyBody,
        titleColor: AppColors.primary,
        subtitleColor: AppColors.taupeBrown,
      );
    }
    return ListView.separated(
      controller: scrollController,
      padding: const EdgeInsets.all(16),
      itemCount: itemCount + 1,
      separatorBuilder: (_, _) => const SizedBox(height: 14),
      itemBuilder: (context, index) {
        if (index < itemCount) return itemBuilder(context, index);
        return loadingMore
            ? const Padding(
                padding: EdgeInsets.all(8),
                child: Center(child: SpinningIconIndicator(size: 28)),
              )
            : const SizedBox.shrink();
      },
    );
  }
}
