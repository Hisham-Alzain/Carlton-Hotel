import 'package:carlton/customWidgets/custom_empty_placeholder.dart';
import 'package:carlton/customWidgets/custom_scaffold.dart';
import 'package:carlton/l10n/app_translations.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';

/// Security. Guests sign in with a one-time code, so there is no password or
/// device list to manage yet; the page says so instead of a "coming soon".
class SecurityView extends StatelessWidget {
  const SecurityView({super.key});

  @override
  Widget build(BuildContext context) {
    return CustomScaffold(
      appBar: AppBar(
        iconTheme: const IconThemeData(color: AppColors.inkBlack),
        title: Text(AppTranslations.security),
      ),
      body: CustomEmptyPlaceholder(
        iconWidget: const Icon(
          Icons.verified_user_outlined,
          size: 48,
          color: AppColors.mediumGrey,
        ),
        title: AppTranslations.securityEmptyTitle,
        subtitle: AppTranslations.securityEmptySubtitle,
      ),
    );
  }
}
