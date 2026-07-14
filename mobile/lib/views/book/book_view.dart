import 'package:carlton/customWidgets/custom_app_bar.dart';
import 'package:carlton/customWidgets/custom_empty_placeholder.dart';
import 'package:carlton/customWidgets/custom_scaffold.dart';
import 'package:carlton/l10n/app_translations.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';

/// Book tab — placeholder until the booking flow is built.
class BookView extends StatelessWidget {
  const BookView({super.key});

  @override
  Widget build(BuildContext context) {
    return CustomScaffold(
      appBar: CustomAppBar(title: AppTranslations.bookTitle),
      body: CustomEmptyPlaceholder(
        iconWidget: const Icon(
          Icons.calendar_month_outlined,
          size: 56,
          color: AppColors.primary,
        ),
        title: AppTranslations.bookingComingSoon,
        subtitle: AppTranslations.bookComingSoonSubtitle,
      ),
    );
  }
}
