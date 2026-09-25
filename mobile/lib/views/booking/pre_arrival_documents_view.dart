import 'package:carlton/components/custom_info_banner.dart';
import 'package:carlton/controllers/booking/pre_arrival_documents_controller.dart';
import 'package:carlton/customWidgets/custom_containers.dart';
import 'package:carlton/customWidgets/custom_filled_button.dart';
import 'package:carlton/customWidgets/custom_scaffold.dart';
import 'package:carlton/l10n/app_translations.dart';
import 'package:carlton/models/pre_arrival_document.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:flutter_svg/flutter_svg.dart';
import 'package:get/get.dart';

/// E-check-in document upload (`POST /pre-arrival/documents`). No Figma frame —
/// a clean functional screen built from the existing Custom* widgets: an empty
/// state explaining the passport/ID upload, a row per picked file (filename +
/// type + remove), an "Add Document" button, and a full-width submit CTA.
class PreArrivalDocumentsView extends GetView<PreArrivalDocumentsController> {
  const PreArrivalDocumentsView({super.key});

  @override
  Widget build(BuildContext context) {
    return CustomScaffold(
      appBar: AppBar(
        iconTheme: const IconThemeData(color: AppColors.inkBlack),
        title: Text(
          AppTranslations.preArrivalTitle,
          style: Get.textTheme.titleMedium?.copyWith(
            fontWeight: FontWeight.w700,
            color: AppColors.inkBlack,
          ),
        ),
      ),
      body: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Expanded(
            // SingleChildScrollView + Column rather than ListView: ListView
            // has no `spacing:`. `stretch` replaces the full-width sizing
            // ListView gave its children for free.
            child: SingleChildScrollView(
              padding: const EdgeInsets.all(16),
              // Was 16 after the banner but 20 before the button when rows
              // were present (each row carried a trailing `bottom: 12` on
              // top of an 8 spacer) and 8 when the list was empty. The gap
              // is now a consistent 16 in both states, and the per-row 12
              // sits between rows only — `spacing:` cannot leave a trailing
              // gap after the last one.
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                spacing: 16,
                children: [
                  CustomInfoBanner(
                    message: AppTranslations.preArrivalIntro,
                    tone: InfoBannerTone.info,
                  ),
                  Obx(
                    () => controller.docs.isEmpty
                        ? _EmptyState()
                        : Column(
                            mainAxisSize: MainAxisSize.min,
                            crossAxisAlignment: CrossAxisAlignment.stretch,
                            spacing: 12,
                            children: [
                              for (var i = 0; i < controller.docs.length; i++)
                                _DocumentRow(
                                  document: controller.docs[i],
                                  onTypeChanged: (type) =>
                                      controller.setType(i, type),
                                  onRemove: () => controller.removeDoc(i),
                                ),
                            ],
                          ),
                  ),
                  OutlinedButton.icon(
                    onPressed: controller.pickDocuments,
                    icon: const Icon(Icons.add, color: AppColors.primary),
                    label: Text(
                      AppTranslations.addDocument,
                      style: Get.textTheme.labelLarge?.copyWith(
                        color: AppColors.primary,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                    style: OutlinedButton.styleFrom(
                      side: const BorderSide(color: AppColors.black10),
                      padding: const EdgeInsets.symmetric(vertical: 14),
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(10),
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ),
          Padding(
            padding: const EdgeInsets.all(16),
            child: Obx(
              () => CustomFilledButton(
                width: double.infinity,
                height: 52,
                isLoading: controller.submitting.value,
                onPressed: controller.docs.isEmpty ? null : controller.submit,
                child: Text(AppTranslations.submitDocuments),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _EmptyState extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    final textStyle = Get.textTheme;
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 40),
      // Nested rather than one flat spacing: the title and subtitle are a pair
      // (8 apart) held further from the icon (16).
      child: Column(
        spacing: 16,
        children: [
          const Icon(
            Icons.badge_outlined,
            size: 56,
            color: AppColors.antiqueGold,
          ),
          Column(
            mainAxisSize: MainAxisSize.min,
            spacing: 8,
            children: [
              Text(
                AppTranslations.preArrivalEmptyTitle,
                textAlign: TextAlign.center,
                style: textStyle.titleSmall?.copyWith(
                  fontWeight: FontWeight.w700,
                  color: AppColors.inkBlack,
                ),
              ),
              Text(
                AppTranslations.preArrivalEmptySubtitle,
                textAlign: TextAlign.center,
                style: textStyle.labelMedium?.copyWith(
                  color: AppColors.dimGrey,
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _DocumentRow extends StatelessWidget {
  final PreArrivalDocumentUpload document;
  final ValueChanged<String> onTypeChanged;
  final VoidCallback onRemove;

  const _DocumentRow({
    required this.document,
    required this.onTypeChanged,
    required this.onRemove,
  });

  @override
  Widget build(BuildContext context) {
    final textStyle = Get.textTheme;
    return PillContainer(
      radius: 12,
      padding: const EdgeInsets.all(12),
      backgroundColor: AppColors.pearlCream,
      child: Row(
        spacing: 12,
        children: [
          Icon(
            document.mime == 'application/pdf'
                ? Icons.picture_as_pdf_outlined
                : Icons.image_outlined,
            color: AppColors.antiqueGold,
          ),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              spacing: 6,
              children: [
                Text(
                  document.fileName,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: textStyle.labelMedium?.copyWith(
                    fontWeight: FontWeight.w600,
                    color: AppColors.inkBlack,
                  ),
                ),
                _TypeDropdown(value: document.type, onChanged: onTypeChanged),
              ],
            ),
          ),
          IconButton(
            onPressed: onRemove,
            icon: SvgPicture.asset(
              'assets/icons/close.svg',
              width: 18,
              height: 18,
              colorFilter: const ColorFilter.mode(
                AppColors.dimGrey,
                BlendMode.srcIn,
              ),
            ),
            visualDensity: VisualDensity.compact,
          ),
        ],
      ),
    );
  }
}

class _TypeDropdown extends StatelessWidget {
  final String value;
  final ValueChanged<String> onChanged;

  const _TypeDropdown({required this.value, required this.onChanged});

  @override
  Widget build(BuildContext context) {
    final types = PreArrivalDocumentsController.documentTypes;
    // A picked type could be free-form; make sure the current value is present.
    final items = {value, ...types}.toList();
    return DropdownButtonHideUnderline(
      child: DropdownButton<String>(
        value: value,
        isDense: true,
        icon: const Icon(Icons.arrow_drop_down, color: AppColors.dimGrey),
        style: Get.textTheme.labelMedium?.copyWith(color: AppColors.dimGrey),
        items: items
            .map(
              (type) => DropdownMenuItem<String>(
                value: type,
                child: Text(AppTranslations.documentType(type)),
              ),
            )
            .toList(),
        onChanged: (next) {
          if (next != null) onChanged(next);
        },
      ),
    );
  }
}
