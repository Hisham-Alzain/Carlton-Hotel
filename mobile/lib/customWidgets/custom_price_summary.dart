import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';

/// A single label → amount row for receipts and booking summaries.
///
/// Two visual tiers, both taken from the Figma Receipt sheet:
/// - regular line item (DM Sans 13, `#232323` label / `#141414` amount) with an
///   optional hairline divider below;
/// - [emphasized] total (Plus Jakarta SemiBold 15 label / Bold 17 primary
///   amount) with the primary-colored top rule the design draws above "Total".
class CustomPriceSummaryRow extends StatelessWidget {
  final String label;
  final String value;
  final bool emphasized;
  final bool showBottomDivider;
  final TextStyle? labelStyle;
  final TextStyle? valueStyle;

  const CustomPriceSummaryRow({
    required this.label,
    required this.value,
    this.emphasized = false,
    this.showBottomDivider = false,
    this.labelStyle,
    this.valueStyle,
    super.key,
  });

  @override
  Widget build(BuildContext context) {
    final defaultLabel = emphasized
        ? const TextStyle(
            fontFamily: 'Plus Jakarta Sans',
            fontSize: 15,
            fontWeight: FontWeight.w600,
            color: AppColors.navLabel,
          )
        : const TextStyle(
            fontFamily: 'DM Sans',
            fontSize: 13,
            color: AppColors.textPrimary,
          );
    final defaultValue = emphasized
        ? const TextStyle(
            fontFamily: 'Plus Jakarta Sans',
            fontSize: 17,
            fontWeight: FontWeight.w700,
            color: AppColors.primary,
          )
        : const TextStyle(
            fontFamily: 'DM Sans',
            fontSize: 13,
            fontWeight: FontWeight.w500,
            color: AppColors.navLabel,
          );

    final row = Padding(
      padding: EdgeInsets.only(top: emphasized ? 13 : 10, bottom: 10),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Expanded(child: Text(label, style: labelStyle ?? defaultLabel)),
          Text(value, style: valueStyle ?? defaultValue),
        ],
      ),
    );

    if (emphasized) {
      return Container(
        decoration: const BoxDecoration(
          border: Border(
            top: BorderSide(color: AppColors.primary, width: 1.18),
          ),
        ),
        child: row,
      );
    }
    if (showBottomDivider) {
      return Container(
        decoration: const BoxDecoration(
          border: Border(
            bottom: BorderSide(color: AppColors.hairlineFaint, width: 1.18),
          ),
        ),
        child: row,
      );
    }
    return row;
  }
}

/// Stacks a set of [CustomPriceSummaryRow]s with dividers between line items
/// and an emphasized total at the bottom — the receipt/breakdown block.
class CustomPriceSummary extends StatelessWidget {
  /// Ordered `(label, value)` line items, drawn with dividers between them.
  final List<(String, String)> items;
  final String? totalLabel;
  final String? totalValue;

  const CustomPriceSummary({
    required this.items,
    this.totalLabel,
    this.totalValue,
    super.key,
  });

  @override
  Widget build(BuildContext context) {
    return Column(
      mainAxisSize: MainAxisSize.min,
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        for (var i = 0; i < items.length; i++)
          CustomPriceSummaryRow(
            label: items[i].$1,
            value: items[i].$2,
            showBottomDivider: i != items.length - 1,
          ),
        if (totalLabel != null && totalValue != null)
          CustomPriceSummaryRow(
            label: totalLabel!,
            value: totalValue!,
            emphasized: true,
          ),
      ],
    );
  }
}
