import 'package:carlton/components/expandable_text_tile.dart';
import 'package:carlton/l10n/app_translations.dart';
import 'package:carlton/controllers/account/legal_controller.dart';
import 'package:carlton/customWidgets/custom_containers.dart';
import 'package:carlton/customWidgets/custom_empty_placeholder.dart';
import 'package:carlton/customWidgets/custom_indicators.dart';
import 'package:carlton/customWidgets/custom_scaffold.dart';
import 'package:carlton/models/info_page.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';

/// Legal: the hotel's terms, privacy policy and about page, each fetched from
/// `GET /public/pages/{slug}` and shown as an expanding card.
class LegalView extends GetView<LegalController> {
  const LegalView({super.key});

  @override
  Widget build(BuildContext context) {
    return CustomScaffold(
      appBar: AppBar(
        iconTheme: const IconThemeData(color: AppColors.inkBlack),
        title: Text(AppTranslations.legal),
      ),
      body: Obx(() {
        if (controller.loading.value) {
          return const Center(child: LogoLoadingIndicator(size: 50));
        }
        if (controller.error.value) {
          return CustomEmptyPlaceholder.loadFailed(
            title: AppTranslations.legalLoadFailed,
            subtitle: AppTranslations.checkConnectionShort,
            onRetry: controller.load,
          );
        }
        return ListView(
          padding: const EdgeInsets.all(20),
          children: [
            Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              spacing: 10,
              children: [
                for (final page in controller.pages) _PageCard(page: page),
              ],
            ),
          ],
        );
      }),
    );
  }
}

/// One document, expanding to its full text.
class _PageCard extends GetView<LegalController> {
  final InfoPage page;

  const _PageCard({required this.page});

  @override
  Widget build(BuildContext context) {
    return PillContainer(
      radius: 14,
      backgroundColor: AppColors.white,
      padding: const EdgeInsets.all(20),
      border: Border.all(color: AppColors.linenGrey),
      child: Obx(
        () => ExpandableTextTile(
          title: page.title.value,
          body: page.content.value,
          expanded: controller.isExpanded(page.slug),
          onToggle: () => controller.toggle(page.slug),
        ),
      ),
    );
  }
}
