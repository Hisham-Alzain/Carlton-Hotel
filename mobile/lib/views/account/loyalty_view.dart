import 'package:carlton/components/custom_info_banner.dart';
import 'package:carlton/components/loyalty/loyalty_activity_section.dart';
import 'package:carlton/components/loyalty/loyalty_earn_banner.dart';
import 'package:carlton/components/loyalty/loyalty_page.dart';
import 'package:carlton/components/loyalty/loyalty_points_card.dart';
import 'package:carlton/components/loyalty/loyalty_stats_grid.dart';
import 'package:carlton/controllers/account/loyalty_controller.dart';
import 'package:carlton/customWidgets/custom_empty_placeholder.dart';
import 'package:carlton/customWidgets/custom_indicators.dart';
import 'package:carlton/extensions/date_extension.dart';
import 'package:carlton/extensions/points_extension.dart';
import 'package:carlton/extensions/text_style_extension.dart';
import 'package:carlton/l10n/app_translations.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';

class LoyaltyView extends GetView<LoyaltyController> {
  const LoyaltyView({super.key});

  @override
  Widget build(BuildContext context) {
    return LoyaltyScaffold(
      title: AppTranslations.loyaltyTitle,
      body: Obx(() {
        if (controller.accountLoading.value &&
            controller.account.value == null) {
          return const Center(child: LogoLoadingIndicator(size: 50));
        }
        final account = controller.account.value;
        if (account == null) {
          return CustomEmptyPlaceholder.loadFailed(
            title: AppTranslations.loyaltyLoadFailed,
            subtitle: AppTranslations.checkConnectionShort,
            onRetry: controller.reloadAll,
          );
        }
        // Pagination loads the next ledger page as this scroll nears its end;
        // the ledger is inside one scroll view with the cards above it, so it
        // listens through the mixin rather than owning a ScrollController.
        return NotificationListener<ScrollNotification>(
          onNotification: controller.onScrollNotification,
          child: RefreshIndicator(
            onRefresh: controller.reloadAll,
            color: AppColors.primary,
            child: SingleChildScrollView(
              physics: const AlwaysScrollableScrollPhysics(),
              padding: const EdgeInsets.all(16),
              // Outer 28 sets the ledger and the actions apart as their own
              // blocks; the inner 20 is the tighter rhythm between balance,
              // promise and totals, which read as one summary. `stretch` is
              // load-bearing — a Column centres its children, which would
              // shrink the balance card, the stats grid and the actions.
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                spacing: 28,
                children: [
                  Column(
                    mainAxisSize: MainAxisSize.min,
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    spacing: 20,
                    children: [
                      LoyaltyPointsCard(account: account),
                      if (account.hasExpiringPoints)
                        CustomInfoBanner(
                          tone: InfoBannerTone.warning,
                          message: AppTranslations.loyaltyExpiring(
                            points: account.expiringSoonPoints.formatPoints(),
                            date: account.nextExpiryAt!.formatDatePicker(),
                          ),
                        ),
                      if (account.program.earning) const LoyaltyEarnBanner(),
                      LoyaltyStatsGrid(account: account),
                    ],
                  ),
                  _Ledger(controller: controller),
                  Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    spacing: 12,
                    children: [
                      _FoilButton(
                        label: AppTranslations.loyaltyRedeem,
                        onPressed: controller.openRewards,
                      ),
                      _OutlineButton(
                        label: AppTranslations.loyaltyMyVouchers,
                        onPressed: controller.openVouchers,
                      ),
                    ],
                  ),
                ],
              ),
            ),
          ),
        );
      }),
    );
  }
}

/// The ledger with its own loading and failed states: a ledger that failed
/// must not blank the balance above it.
class _Ledger extends StatelessWidget {
  final LoyaltyController controller;

  const _Ledger({required this.controller});

  @override
  Widget build(BuildContext context) {
    return Obx(() {
      if (controller.loading.value && controller.items.isEmpty) {
        return const Padding(
          padding: EdgeInsets.symmetric(vertical: 32),
          child: Center(child: LogoLoadingIndicator(size: 40)),
        );
      }
      if (controller.hasError.value && controller.items.isEmpty) {
        return CustomEmptyPlaceholder.loadFailed(
          title: AppTranslations.loyaltyLoadFailed,
          subtitle: AppTranslations.checkConnectionShort,
          onRetry: controller.reloadAll,
        );
      }
      return LoyaltyActivitySection(
        entries: controller.items,
        loadingMore: controller.loadingMore.value,
      );
    });
  }
}

/// A foil-gradient CTA distinct from [CustomFilledButton]'s flat fill — the
/// one button in the app meant to look like a reward, not a form action.
/// Bespoke `Material` + `InkWell` rather than the shared button: that widget
/// only takes a flat [Color], and flattening this gradient into it would be
/// exactly the "worse copy of the framework's styling API" this codebase
/// avoids (see [CustomFilledButton]'s own call sites for the flat case).
class _FoilButton extends StatelessWidget {
  final String label;
  final VoidCallback onPressed;

  const _FoilButton({required this.label, required this.onPressed});

  @override
  Widget build(BuildContext context) {
    final TextTheme textStyle = Get.textTheme;

    return Container(
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(14),
        boxShadow: const [
          BoxShadow(
            color: AppColors.antiqueGold56,
            blurRadius: 16,
            offset: Offset(0, 6),
          ),
        ],
      ),
      child: Material(
        color: Colors.transparent,
        borderRadius: BorderRadius.circular(14),
        child: Ink(
          height: 52,
          decoration: const BoxDecoration(
            gradient: LinearGradient(
              colors: [
                AppColors.antiqueGold,
                AppColors.sandGold,
                AppColors.antiqueGold,
              ],
              stops: [0, 0.5, 1],
            ),
            borderRadius: BorderRadius.all(Radius.circular(14)),
          ),
          child: InkWell(
            onTap: onPressed,
            borderRadius: BorderRadius.circular(14),
            // No leading icon: the gift glyph already belongs to the redeemed
            // ledger rows, and a foil-stamped wordmark on its own reads as the
            // more considered button than wordmark-plus-icon.
            child: Center(
              child: Text(
                label.toUpperCase(),
                style: textStyle.labelLarge
                    ?.copyWith(
                      fontWeight: FontWeight.w700,
                      color: AppColors.espressoBrown,
                    )
                    .tracked(context, 1.2),
              ),
            ),
          ),
        ),
      ),
    );
  }
}

/// The quieter second action under the foil one.
class _OutlineButton extends StatelessWidget {
  final String label;
  final VoidCallback onPressed;

  const _OutlineButton({required this.label, required this.onPressed});

  @override
  Widget build(BuildContext context) {
    final TextTheme textStyle = Get.textTheme;

    return Material(
      color: Colors.transparent,
      borderRadius: BorderRadius.circular(14),
      child: InkWell(
        onTap: onPressed,
        borderRadius: BorderRadius.circular(14),
        child: Container(
          height: 48,
          alignment: Alignment.center,
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(14),
            border: Border.all(color: AppColors.antiqueGold56),
          ),
          child: Text(
            label,
            style: textStyle.labelLarge?.copyWith(
              fontWeight: FontWeight.w600,
              color: AppColors.primary,
            ),
          ),
        ),
      ),
    );
  }
}
