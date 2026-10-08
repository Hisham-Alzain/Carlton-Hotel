import 'package:carlton/customWidgets/custom_empty_placeholder.dart';
import 'package:carlton/customWidgets/custom_scaffold.dart';
import 'package:carlton/l10n/app_translations.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';

/// Notifications inbox. The guest API has no notification list yet, so the
/// page shows its empty state; pushes still arrive as system notifications.
class NotificationsView extends StatelessWidget {
  const NotificationsView({super.key});

  @override
  Widget build(BuildContext context) {
    return CustomScaffold(
      appBar: AppBar(
        iconTheme: const IconThemeData(color: AppColors.inkBlack),
        title: Text(AppTranslations.notifications),
      ),
      body: CustomEmptyPlaceholder(
        iconWidget: const Icon(
          Icons.notifications_none_outlined,
          size: 48,
          color: AppColors.mediumGrey,
        ),
        title: AppTranslations.notificationsEmptyTitle,
        subtitle: AppTranslations.notificationsEmptySubtitle,
      ),
    );
  }
}
