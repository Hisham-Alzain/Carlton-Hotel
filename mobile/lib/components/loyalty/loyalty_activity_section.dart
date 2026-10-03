import 'package:carlton/customWidgets/custom_containers.dart';
import 'package:carlton/customWidgets/custom_empty_placeholder.dart';
import 'package:carlton/extensions/date_extension.dart';
import 'package:carlton/extensions/points_extension.dart';
import 'package:carlton/extensions/text_style_extension.dart';
import 'package:carlton/l10n/app_translations.dart';
import 'package:carlton/models/loyalty.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';

/// The points ledger card: a header carrying the entry count, then one row per
/// movement, or the empty placeholder when the guest has not earned yet.
///
/// Each row names the reservation that moved the balance — the ledger's whole
/// job is making the link between a stay and its points visible, so the
/// booking reference is part of the row, not a detail screen behind it.
class LoyaltyActivitySection extends StatelessWidget {
  final List<LoyaltyTransaction> transactions;

  const LoyaltyActivitySection({required this.transactions, super.key});

  @override
  Widget build(BuildContext context) {
    final TextTheme textStyle = Get.textTheme;

    return Container(
      clipBehavior: Clip.antiAlias,
      decoration: BoxDecoration(
        color: AppColors.white,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: AppColors.antiqueGold20),
        boxShadow: const [
          BoxShadow(
            color: AppColors.slateShadow04,
            blurRadius: 14,
            offset: Offset(0, 6),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(15, 15, 15, 14),
            child: Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Text(
                  AppTranslations.loyaltyActivity,
                  style: textStyle.titleMedium?.copyWith(
                    fontWeight: FontWeight.w700,
                    color: AppColors.primary,
                  ),
                ),
                PillContainer(
                  backgroundColor: AppColors.antiqueGold09,
                  radius: 20,
                  padding: const EdgeInsets.symmetric(
                    horizontal: 10,
                    vertical: 4,
                  ),
                  child: Text(
                    AppTranslations.loyaltyEntryCount(transactions.length),
                    style: textStyle.labelSmall?.copyWith(
                      fontFamily: 'DM Sans',
                      fontWeight: FontWeight.w600,
                      color: AppColors.bronzeGold,
                    ),
                  ),
                ),
              ],
            ),
          ),
          const Divider(
            height: 1,
            thickness: 1,
            color: AppColors.antiqueGold20,
          ),

          if (transactions.isEmpty)
            const SizedBox(height: 180, child: _EmptyActivity())
          else
            // The index only exists to suppress the rule above the first row,
            // so it stays inside the row rather than being interleaved here.
            ...transactions.indexed.map(
              (entry) => _ActivityRow(
                transaction: entry.$2,
                showDivider: entry.$1 > 0,
              ),
            ),
        ],
      ),
    );
  }
}

class _EmptyActivity extends StatelessWidget {
  const _EmptyActivity();

  @override
  Widget build(BuildContext context) {
    return CustomEmptyPlaceholder(
      iconWidget: const Icon(
        // A statement glyph, not sparkles — the empty state is an empty
        // ledger, and the icon should name the thing that is missing.
        Icons.receipt_long_outlined,
        size: 30,
        color: AppColors.bronzeGold,
      ),
      iconContainerColor: AppColors.antiqueGold09,
      title: AppTranslations.loyaltyNoActivity,
      subtitle: AppTranslations.loyaltyNoActivityBody,
      titleColor: AppColors.primary,
      subtitleColor: AppColors.taupeBrown,
    );
  }
}

class _ActivityRow extends StatelessWidget {
  final LoyaltyTransaction transaction;

  /// Whether a rule is drawn above this row. False for the first row, where
  /// the card header already supplies one.
  final bool showDivider;

  const _ActivityRow({required this.transaction, required this.showDivider});

  @override
  Widget build(BuildContext context) {
    final TextTheme textStyle = Get.textTheme;
    final bool isCredit = transaction.kind == LoyaltyEntryKind.earned;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        if (showDivider)
          const Divider(
            height: 1,
            thickness: 1,
            color: AppColors.antiqueGold09,
          ),
        Padding(
          padding: const EdgeInsets.all(15),
          child: Row(
            spacing: 12,
            children: [
              CustomIconChip(
                size: 40,
                radius: 12,
                backgroundColor: isCredit
                    ? AppColors.mistGreen
                    : AppColors.antiqueGold09,
                border: Border.all(
                  color: isCredit
                      ? AppColors.sageGreen
                      : AppColors.antiqueGold20,
                ),
                child: Icon(
                  _glyph,
                  size: 18,
                  color: isCredit
                      ? AppColors.forestGreen
                      : AppColors.bronzeGold,
                ),
              ),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  spacing: 4,
                  children: [
                    Text(
                      transaction.title,
                      style: textStyle.labelLarge?.copyWith(
                        fontWeight: FontWeight.w600,
                        color: AppColors.primary,
                      ),
                    ),
                    Text(
                      '${transaction.bookingRef} · '
                      '${transaction.date.formatDatePicker()}',
                      style: textStyle.labelSmall
                          ?.copyWith(
                            fontFamily: 'DM Sans',
                            color: AppColors.taupeBrown,
                          )
                          .tracked(context, 0.8),
                    ),
                  ],
                ),
              ),
              Text(
                (isCredit ? transaction.points : -transaction.points)
                    .formatSignedPoints(),
                style: textStyle.titleSmall?.copyWith(
                  fontWeight: FontWeight.w700,
                  color: isCredit ? AppColors.forestGreen : AppColors.brickRed,
                ),
              ),
            ],
          ),
        ),
      ],
    );
  }

  /// Expiry overrides the source glyph — that a batch lapsed is the salient
  /// fact about the row, and the hourglass is the only glyph here that says
  /// it. Everything else shows what the points were for.
  IconData get _glyph => switch (transaction) {
    LoyaltyTransaction(kind: LoyaltyEntryKind.expired) =>
      Icons.hourglass_empty_rounded,
    LoyaltyTransaction(source: LoyaltyEntrySource.stay) =>
      Icons.king_bed_outlined,
    LoyaltyTransaction(source: LoyaltyEntrySource.dining) =>
      Icons.restaurant_outlined,
    LoyaltyTransaction(source: LoyaltyEntrySource.spa) => Icons.spa_outlined,
    _ => Icons.auto_awesome_outlined,
  };
}
