import 'package:carlton/l10n/app_translations.dart';
import 'package:carlton/components/account/custom_settings_section.dart';
import 'package:carlton/controllers/account/support_controller.dart';
import 'package:carlton/customWidgets/custom_empty_placeholder.dart';
import 'package:carlton/customWidgets/custom_filled_button.dart';
import 'package:carlton/customWidgets/custom_indicators.dart';
import 'package:carlton/customWidgets/custom_scaffold.dart';
import 'package:carlton/models/faq.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';

/// Help & Support: the hotel's published FAQs, grouped by category, plus a way
/// into the staff chat for anything they do not answer.
class SupportView extends GetView<SupportController> {
  const SupportView({super.key});

  @override
  Widget build(BuildContext context) {
    return CustomScaffold(
      appBar: AppBar(
        iconTheme: const IconThemeData(color: AppColors.inkBlack),
        title: Text(AppTranslations.helpAndSupport),
      ),
      body: Obx(() {
        if (controller.loading.value) {
          return const Center(child: CustomProgressIndicator());
        }
        if (controller.hasError.value) {
          return CustomEmptyPlaceholder(
            iconWidget: const Icon(
              Icons.wifi_off_rounded,
              size: 48,
              color: AppColors.mediumGrey,
            ),
            title: AppTranslations.supportLoadFailed,
            subtitle: AppTranslations.checkConnectionShort,
            primaryLabel: AppTranslations.retry,
            onPrimary: controller.reload,
          );
        }
        // Reachable and empty: the hotel has published no FAQs. Chat still works,
        // so offer that rather than an error.
        if (controller.items.isEmpty) {
          return CustomEmptyPlaceholder(
            iconWidget: const Icon(
              Icons.help_outline_rounded,
              size: 48,
              color: AppColors.mediumGrey,
            ),
            title: AppTranslations.supportEmpty,
            subtitle: AppTranslations.supportEmptySubtitle,
            primaryLabel: AppTranslations.messageUs,
            onPrimary: controller.contactUs,
          );
        }
        return ListView(
          // The mixin loads the next page when this nears the bottom.
          controller: controller.scrollController,
          padding: const EdgeInsets.all(20),
          children: [
            // Grouped by the category the CMS assigns, in first-seen order —
            // the endpoint already sorts by `sort_order`, so that ordering is
            // the hotel's own.
            for (final group in _grouped(controller.items).entries)
              Padding(
                padding: const EdgeInsets.only(bottom: 20),
                child: CustomSettingsSection(
                  title: group.key,
                  children: [for (final faq in group.value) _FaqRow(faq: faq)],
                ),
              ),
            if (controller.loadingMore.value)
              const Padding(
                padding: EdgeInsets.only(bottom: 20),
                child: CustomProgressIndicator(),
              ),
            CustomFilledButton(
              width: double.infinity,
              height: 50,
              onPressed: controller.contactUs,
              child: Text(AppTranslations.stillNeedHelp),
            ),
          ],
        );
      }),
    );
  }

  /// Preserves the server's ordering: a plain map keyed by category, filled in
  /// list order, so categories appear as the CMS sorted them. FAQs with no
  /// category fall under one heading rather than vanishing.
  static Map<String, List<Faq>> _grouped(List<Faq> faqs) {
    final grouped = <String, List<Faq>>{};
    for (final faq in faqs) {
      final key = faq.category.isEmpty
          ? AppTranslations.supportGeneral
          : faq.category;
      grouped.putIfAbsent(key, () => <Faq>[]).add(faq);
    }
    return grouped;
  }
}

/// One question, expanding to its answer. Tapping the open row closes it.
class _FaqRow extends GetView<SupportController> {
  final Faq faq;

  const _FaqRow({required this.faq});

  @override
  Widget build(BuildContext context) {
    final TextTheme textStyle = Get.textTheme;

    return Obx(() {
      final expanded = controller.isExpanded(faq.uuid);
      return InkWell(
        onTap: () => controller.toggle(faq.uuid),
        child: Padding(
          padding: const EdgeInsets.symmetric(vertical: 10),
          child: Column(
            spacing: 10,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                spacing: 10,
                children: [
                  Expanded(
                    child: Text(
                      faq.question.value,
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
                  faq.answer.value,
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
