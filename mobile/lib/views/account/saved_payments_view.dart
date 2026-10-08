import 'package:carlton/customWidgets/custom_empty_placeholder.dart';
import 'package:carlton/customWidgets/custom_scaffold.dart';
import 'package:carlton/l10n/app_translations.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';

/// Saved payment methods. Card payments have no gateway yet, so nothing can
/// be saved and the page shows its empty state.
class SavedPaymentsView extends StatelessWidget {
  const SavedPaymentsView({super.key});

  @override
  Widget build(BuildContext context) {
    return CustomScaffold(
      appBar: AppBar(
        iconTheme: const IconThemeData(color: AppColors.inkBlack),
        title: Text(AppTranslations.savedPayments),
      ),
      body: CustomEmptyPlaceholder(
        iconWidget: const Icon(
          Icons.credit_card_outlined,
          size: 48,
          color: AppColors.mediumGrey,
        ),
        title: AppTranslations.savedPaymentsEmptyTitle,
        subtitle: AppTranslations.savedPaymentsEmptySubtitle,
      ),
    );
  }
}
