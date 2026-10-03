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
          return const Center(child: CustomProgressIndicator());
        }
        if (controller.error.value) {
          return CustomEmptyPlaceholder(
            iconWidget: const Icon(
              Icons.wifi_off_rounded,
              size: 48,
              color: AppColors.mediumGrey,
            ),
            title: AppTranslations.legalLoadFailed,
            subtitle: AppTranslations.checkConnectionShort,
            primaryLabel: AppTranslations.retry,
            onPrimary: controller.load,
          );
        }
        return ListView(
          padding: const EdgeInsets.all(20),
          children: [
            for (final page in controller.pages)
              Padding(
                padding: const EdgeInsets.only(bottom: 10),
                child: _PageCard(page: page),
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
    final TextTheme textStyle = Get.textTheme;

    return Obx(() {
      final expanded = controller.isExpanded(page.slug);
      return PillContainer(
        radius: 14,
        backgroundColor: AppColors.white,
        padding: const EdgeInsets.all(20),
        border: Border.all(color: AppColors.linenGrey),
        child: InkWell(
          onTap: () => controller.toggle(page.slug),
          child: Column(
            spacing: 10,
            crossAxisAlignment: CrossAxisAlignment.start,
            mainAxisSize: MainAxisSize.min,
            children: [
              Row(
                spacing: 10,
                children: [
                  Expanded(
                    child: Text(
                      page.title.value,
                      style: textStyle.titleSmall?.copyWith(
                        color: AppColors.inkBlack,
                      ),
                    ),
                  ),
                  Icon(
                    expanded
                        ? Icons.keyboard_arrow_up_rounded
                        : Icons.keyboard_arrow_down_rounded,
                    color: AppColors.mediumGrey,
                  ),
                ],
              ),
              if (expanded)
                Text(
                  page.content.value,
                  style: textStyle.bodySmall?.copyWith(
                    fontFamily: 'DM Sans',
                    color: AppColors.taupeBrown,
                  ),
                ),
            ],
          ),
        ),
      );
    });
  }
}
