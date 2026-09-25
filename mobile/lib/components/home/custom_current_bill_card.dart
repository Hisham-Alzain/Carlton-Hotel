import 'package:carlton/l10n/app_translations.dart';
import 'package:carlton/components/custom_price_summary.dart';
import 'package:carlton/customWidgets/custom_containers.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';

/// "Current Bill" block on the reservation-state Home (Figma 2237:3914): a
/// heading with a Full Statement link over the running charges + estimated
/// total. Reuses [CustomPriceSummary] for the line items.
class CustomCurrentBillCard extends StatelessWidget {
  final List<(String, String)> lines;
  final String total;
  final VoidCallback onFullStatement;

  const CustomCurrentBillCard({
    required this.lines,
    required this.total,
    required this.onFullStatement,
    super.key,
  });

  @override
  Widget build(BuildContext context) {
    return Card(
      margin: const EdgeInsets.all(10),
      color: AppColors.white,
      surfaceTintColor: Colors.transparent,
      elevation: 1,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(14),
        side: const BorderSide(color: AppColors.black06),
      ),
      child: Padding(
        padding: const EdgeInsets.all(10),
        child: SectionContainer(
          title: AppTranslations.currentBill,
          buttonText: AppTranslations.fullStatement,
          icon: Icons.chevron_right,
          iconColor: AppColors.primary,
          onPressed: onFullStatement,
          child: CustomPriceSummary(
            lineItems: lines,
            totalLabel: AppTranslations.estimatedTotal,
            totalValue: total,
          ),
        ),
      ),
    );
  }
}
