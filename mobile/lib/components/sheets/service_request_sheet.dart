import 'package:carlton/controllers/home/services_controller.dart';
import 'package:carlton/customWidgets/custom_containers.dart';
import 'package:carlton/customWidgets/custom_filled_button.dart';
import 'package:carlton/customWidgets/custom_text_field.dart';
import 'package:carlton/l10n/app_translations.dart';
import 'package:carlton/models/service_catalog_item.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';

/// Confirm-a-request bottom sheet body for one [ServiceCatalogOption], shown
/// via `ServicesController.openServiceRequest` -> `CustomBottomSheet.show`.
///
/// The option's title and description are rendered by the sheet shell's
/// header, so they are deliberately absent here.
class ServiceRequestSheet extends StatefulWidget {
  final ServiceCatalogOption option;

  const ServiceRequestSheet({required this.option, super.key});

  @override
  State<ServiceRequestSheet> createState() => _ServiceRequestSheetState();
}

class _ServiceRequestSheetState extends State<ServiceRequestSheet> {
  // Held in state, not build(): opening the keyboard rebuilds the sheet, and a
  // controller created in build() would drop whatever was typed.
  final _notesController = TextEditingController();

  @override
  void dispose() {
    _notesController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final TextTheme textStyle = Get.textTheme;
    final mins = widget.option.expectedMinutes;
    // Active-stay label from the live Services controller (was demo Room 812).
    final services = Get.find<ServicesController>();
    final stayLabel = [
      services.room,
      services.stayRoomName,
    ].where((s) => s.isNotEmpty).join(' · ');

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      spacing: 10,
      children: [
        PillContainer(
          padding: const EdgeInsets.all(12),
          backgroundColor: AppColors.cream,
          child: Row(
            spacing: 10,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Icon(Icons.info_outline),
              Expanded(
                child: Text.rich(
                  // Built from ONE localized sentence, then split around the
                  // target so it can stay bold. Assembling this from three
                  // fragments hard-coded English word order — Arabic and
                  // Turkish both put the target elsewhere in the clause.
                  _confirmationSpan(
                    target: stayLabel.isEmpty
                        ? AppTranslations.requestTargetRoom
                        : stayLabel,
                    minutes: mins,
                  ),
                  style: textStyle.labelMedium?.copyWith(
                    color: AppColors.inkBlack,
                  ),
                ),
              ),
            ],
          ),
        ),
        Text(
          'Special Instructions (Optional)'.toUpperCase(),
          style: textStyle.labelSmall?.copyWith(
            fontWeight: FontWeight.w700,
            color: AppColors.dimGrey,
          ),
        ),
        CustomTextField(
          controller: _notesController,
          textInputType: TextInputType.multiline,
          maxLines: 3,
          hintText: AppTranslations.requestNotesHint,
          fillColor: AppColors.white,
          borderColor: AppColors.linenGrey,
        ),
        CustomFilledButton(
          width: double.infinity,
          backgroundColor: AppColors.lagoonTeal,
          onPressed: () {
            Get.find<ServicesController>().submitServiceRequest(
              serviceItemUuid: widget.option.uuid,
              notes: _notesController.text,
            );
            Get.back();
          },
          child: Text(AppTranslations.sendRequest),
        ),
      ],
    );
  }
}

/// One localized sentence, split around [target] so that word alone renders
/// bold. Falls back to a single unstyled span when the placeholder is absent
/// from a translation — better a plain sentence than a dropped one.
TextSpan _confirmationSpan({required String target, int? minutes}) {
  final sentence = minutes != null
      ? AppTranslations.requestConfirmationEta(
          target,
          AppTranslations.etaMinutes(minutes),
        )
      : AppTranslations.requestConfirmation(target);
  final at = sentence.indexOf(target);
  if (at < 0) return TextSpan(text: sentence);
  return TextSpan(
    children: [
      TextSpan(text: sentence.substring(0, at)),
      TextSpan(
        text: target,
        style: const TextStyle(fontWeight: FontWeight.w700),
      ),
      TextSpan(text: sentence.substring(at + target.length)),
    ],
  );
}
