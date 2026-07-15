import 'package:carlton/components/custom_app_bar.dart';
import 'package:carlton/customWidgets/custom_empty_placeholder.dart';
import 'package:carlton/customWidgets/custom_scaffold.dart';
import 'package:carlton/l10n/app_translations.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';

/// Stays tab — placeholder until the stays list is built.
class StaysView extends StatelessWidget {
  const StaysView({super.key});

  @override
  Widget build(BuildContext context) {
    return CustomScaffold(
      appBar: CustomAppBar(title: AppTranslations.staysTitle),
      body: CustomEmptyPlaceholder(
        iconWidget: const Icon(
          Icons.bed_outlined,
          size: 56,
          color: AppColors.primary,
        ),
        title: AppTranslations.staysComingSoonTitle,
        subtitle: AppTranslations.staysComingSoonSubtitle,
      ),
    );
  }
}
