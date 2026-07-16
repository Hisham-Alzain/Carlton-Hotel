import 'package:carlton/customWidgets/custom_empty_placeholder.dart';
import 'package:carlton/l10n/app_translations.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';

class AccountView extends StatelessWidget {
  const AccountView({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: CustomEmptyPlaceholder(
        iconWidget: const Icon(
          Icons.person_outline,
          size: 56,
          color: AppColors.primary,
        ),
        title: AppTranslations.accountComingSoonTitle,
        subtitle: AppTranslations.accountComingSoonSubtitle,
      ),
    );
  }
}
