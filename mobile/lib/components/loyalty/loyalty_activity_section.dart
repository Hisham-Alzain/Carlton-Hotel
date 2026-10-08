import 'package:carlton/theme/theme.dart';
import 'package:carlton/components/loyalty/loyalty_card_texture.dart';
import 'package:carlton/customWidgets/custom_containers.dart';
import 'package:carlton/customWidgets/custom_empty_placeholder.dart';
import 'package:carlton/customWidgets/custom_indicators.dart';
import 'package:carlton/extensions/date_extension.dart';
import 'package:carlton/extensions/points_extension.dart';
import 'package:carlton/extensions/text_style_extension.dart';
import 'package:carlton/l10n/app_translations.dart';
import 'package:carlton/models/loyalty.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';

/// The points ledger card: a header, then one row per movement, or the empty
/// placeholder when the guest has none yet.
///
/// Each row names the stay or booking that moved the balance when there is
/// one, so a movement can be tied back to what caused it without a detail
/// screen behind it.
class LoyaltyActivitySection extends StatelessWidget {
  final List<LoyaltyLedgerEntry> entries;

  /// True while the next page is loading, shown as a spinner under the last row.
  final bool loadingMore;

  const LoyaltyActivitySection({
    required this.entries,
    this.loadingMore = false,
    super.key,
  });

  @override
  Widget build(BuildContext context) {
    final TextTheme textStyle = Get.textTheme;

    return Container(
      clipBehavior: Clip.antiAlias,
      decoration: loyaltyPanelDecoration,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(15, 15, 15, 14),
            child: Text(
              AppTranslations.loyaltyActivity,
              style: textStyle.titleMedium?.copyWith(
                fontWeight: FontWeight.w700,
                color: AppColors.primary,
              ),
            ),
          ),
          const Divider(
            height: 1,
            thickness: 1,
            color: AppColors.antiqueGold20,
          ),

          if (entries.isEmpty)
            const SizedBox(height: 220, child: _EmptyActivity())
          else
            // The index only exists to suppress the rule above the first row,
            // so it stays inside the row rather than being interleaved here.
            ...entries.indexed.map(
              (entry) =>
                  _ActivityRow(entry: entry.$2, showDivider: entry.$1 > 0),
            ),
          if (loadingMore)
            const Padding(
              padding: EdgeInsets.all(16),
              child: Center(child: SpinningIconIndicator(size: 28)),
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
  final LoyaltyLedgerEntry entry;

  /// Whether a rule is drawn above this row. False for the first row, where
  /// the card header already supplies one.
  final bool showDivider;

  const _ActivityRow({required this.entry, required this.showDivider});

  @override
  Widget build(BuildContext context) {
    final TextTheme textStyle = Get.textTheme;
    // The sign decides credit or debit: an `adjust` row can go either way.
    final bool isCredit = entry.isCredit;
    final date = entry.occurredAt?.formatDatePicker();
    final subtitle = [
      entry.bookingCode ?? entry.sourceLabel,
      date,
    ].whereType<String>().where((part) => part.isNotEmpty).join(' · ');

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
                      entry.label,
                      style: textStyle.labelLarge?.copyWith(
                        fontWeight: FontWeight.w600,
                        color: AppColors.primary,
                      ),
                    ),
                    if (subtitle.isNotEmpty)
                      Text(
                        subtitle,
                        style: textStyle.dmLabelSmall
                            ?.copyWith(color: AppColors.taupeBrown)
                            .tracked(context, 0.8),
                      ),
                    // A cancelled stay whose points were already spent: the
                    // clawback took what was left, and this says why it is
                    // less than the original earn.
                    if (entry.shortfallPoints > 0)
                      Text(
                        AppTranslations.loyaltyShortfall(
                          entry.shortfallPoints.formatPoints(),
                        ),
                        style: textStyle.dmLabelSmall?.copyWith(
                          color: AppColors.brickRed,
                        ),
                      ),
                  ],
                ),
              ),
              Text(
                entry.points.formatSignedPoints(),
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

  /// What the row shows: the kind of movement first (a lapse, a redemption, a
  /// refund each have one glyph that says it), and for an earn, what the
  /// points were for.
  IconData get _glyph => switch (entry.type) {
    LoyaltyEntryType.expire => Icons.hourglass_empty_rounded,
    LoyaltyEntryType.redeem => Icons.card_giftcard_outlined,
    LoyaltyEntryType.refund => Icons.replay_rounded,
    LoyaltyEntryType.clawback => Icons.remove_circle_outline,
    LoyaltyEntryType.adjust => Icons.tune_rounded,
    _ => switch (entry.source) {
      LoyaltyEntrySource.stay => Icons.king_bed_outlined,
      LoyaltyEntrySource.service => Icons.room_service_outlined,
      _ => Icons.auto_awesome_outlined,
    },
  };
}
