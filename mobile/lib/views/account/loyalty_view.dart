import 'package:carlton/components/loyalty/loyalty_activity_section.dart';
import 'package:carlton/components/loyalty/loyalty_earn_banner.dart';
import 'package:carlton/components/loyalty/loyalty_points_card.dart';
import 'package:carlton/components/loyalty/loyalty_stats_grid.dart';
import 'package:carlton/controllers/account/loyalty_controller.dart';
import 'package:carlton/customWidgets/custom_scaffold.dart';
import 'package:carlton/extensions/text_style_extension.dart';
import 'package:carlton/l10n/app_translations.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';

class LoyaltyView extends GetView<LoyaltyController> {
  const LoyaltyView({super.key});

  @override
  Widget build(BuildContext context) {
    return CustomScaffold(
      // ivoryCream, not the theme's ghostWhite scaffold: every surface on this
      // screen is warm (cream banner, gold hairlines, teal card), and a cool
      // near-white behind them reads as a different screen showing through.
      // The app bar has to be told the same thing — appBarTheme still carries
      // ghostWhite, and the seam between the two is visible.
      backgroundColor: AppColors.ivoryCream,
      appBar: AppBar(
        backgroundColor: AppColors.ivoryCream,
        surfaceTintColor: AppColors.ivoryCream,
        title: Text(
          AppTranslations.loyaltyTitle,
          style: Theme.of(
            context,
          ).appBarTheme.titleTextStyle?.copyWith(color: AppColors.primary),
        ),
        iconTheme: const IconThemeData(color: AppColors.primary),
      ),
      // SingleChildScrollView + Column rather than ListView: ListView has no
      // `spacing:`. `stretch` is load-bearing — a Column centres its children,
      // which would shrink the balance card, the stats grid and the CTA.
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(16),
        // Outer 28 sets the ledger and the CTA apart as their own blocks; the
        // inner 20 is the tighter rhythm between balance, promise and totals,
        // which read as one summary.
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          spacing: 28,
          children: [
            Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.stretch,
              spacing: 20,
              children: [
                LoyaltyPointsCard(account: controller.account),
                const LoyaltyEarnBanner(),
                LoyaltyStatsGrid(account: controller.account),
              ],
            ),

            LoyaltyActivitySection(transactions: controller.transactions),

            _RedeemButton(onPressed: controller.redeemPoints),
          ],
        ),
      ),
    );
  }
}

/// A foil-gradient CTA distinct from [CustomFilledButton]'s flat fill — the
/// one button in the app meant to look like a reward, not a form action.
/// Bespoke `Material` + `InkWell` rather than the shared button: that widget
/// only takes a flat [Color], and flattening this gradient into it would be
/// exactly the "worse copy of the framework's styling API" this codebase
/// avoids (see [CustomFilledButton]'s own call sites for the flat case).
class _RedeemButton extends StatelessWidget {
  final VoidCallback onPressed;

  const _RedeemButton({required this.onPressed});

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
                AppTranslations.loyaltyRedeem.toUpperCase(),
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
