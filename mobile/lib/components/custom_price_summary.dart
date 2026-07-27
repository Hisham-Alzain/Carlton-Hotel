import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';

/// A single title → value row for receipts and booking summaries.
///
/// Two visual tiers, both taken from the Figma Receipt sheet:
/// - regular line item (DM Sans 13, `#232323` title / `#141414` value);
/// - [isTotal] total (Plus Jakarta SemiBold 15 title / Bold 17 primary value).
///
/// [titleColor] / [valueColor] recolour a tier; [titleStyle] / [valueStyle]
/// replace it outright, for the one-off rows in Room Details and Add-Ons.
class CustomPriceSummaryRow extends StatelessWidget {
  final String title;
  final String value;
  final Color? titleColor;
  final Color? valueColor;
  final bool isTotal;
  final TextStyle? titleStyle;
  final TextStyle? valueStyle;

  const CustomPriceSummaryRow({
    required this.title,
    required this.value,
    this.titleColor,
    this.valueColor,
    this.isTotal = false,
    this.titleStyle,
    this.valueStyle,
    super.key,
  });

  @override
  Widget build(BuildContext context) {
    final TextTheme textStyle = Get.textTheme;

    final defaultTitle = isTotal
        ? textStyle.labelLarge?.copyWith(
            fontWeight: FontWeight.w600,
            color: titleColor ?? AppColors.inkBlack,
          )
        : textStyle.labelMedium?.copyWith(
            fontFamily: 'DM Sans',
            color: titleColor ?? AppColors.slateGrey,
          );

    final defaultValue = isTotal
        ? textStyle.titleMedium?.copyWith(
            fontWeight: FontWeight.w700,
            color: valueColor ?? AppColors.primary,
          )
        : textStyle.labelLarge?.copyWith(
            fontFamily: 'DM Sans',
            fontWeight: FontWeight.w500,
            color: valueColor ?? AppColors.inkBlack,
          );

    return Row(
      mainAxisAlignment: MainAxisAlignment.spaceBetween,
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Expanded(child: Text(title, style: titleStyle ?? defaultTitle)),
        Text(value, style: valueStyle ?? defaultValue),
      ],
    );
  }
}

/// Stacks a set of [CustomPriceSummaryRow]s under an optional heading, with the
/// emphasized total ruled off at the bottom — the receipt/breakdown block.
class CustomPriceSummary extends StatelessWidget {
  /// Optional section heading above the line items.
  final String? title;

  /// Ordered `(title, value)` line items.
  final List<(String, String)> lineItems;
  final String? totalLabel;
  final String? totalValue;

  const CustomPriceSummary({
    required this.lineItems,
    this.title,
    this.totalLabel,
    this.totalValue,
    super.key,
  });

  @override
  Widget build(BuildContext context) {
    final TextTheme textStyle = Get.textTheme;
    final hasTotal = totalLabel != null && totalValue != null;

    return Padding(
      padding: const EdgeInsets.all(10),
      child: Column(
        spacing: 10,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          if (title != null)
            Text(
              title!,
              style: textStyle.labelLarge?.copyWith(
                fontWeight: FontWeight.w700,
              ),
            ),

          ...lineItems.map(
            (lineItem) =>
                CustomPriceSummaryRow(title: lineItem.$1, value: lineItem.$2),
          ),

          if (hasTotal) ...[
            const Divider(),
            CustomPriceSummaryRow(
              title: totalLabel!,
              value: totalValue!,
              isTotal: true,
            ),
          ],
        ],
      ),
    );
  }
}
