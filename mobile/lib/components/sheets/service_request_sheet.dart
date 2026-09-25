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

  /// "812 · Deluxe City View" — the active stay this request is for. Empty
  /// falls back to "your room".
  final String stayLabel;

  /// Called with the sheet's notes; the caller closes the sheet.
  final ValueChanged<String> onSubmit;

  const ServiceRequestSheet({
    required this.option,
    required this.stayLabel,
    required this.onSubmit,
    super.key,
  });

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
    final stayLabel = widget.stayLabel;

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
                  TextSpan(
                    children: [
                      const TextSpan(text: 'This request is for '),
                      TextSpan(
                        text: stayLabel.isEmpty ? 'your room' : stayLabel,
                        style: const TextStyle(fontWeight: FontWeight.w700),
                      ),
                      TextSpan(
                        text: mins != null
                            ? '. Our team will be with you within '
                                  '${AppTranslations.etaMinutes(mins)}.'
                            : '. Our team will be with you shortly.',
                      ),
                    ],
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
          hintText: 'Any specific requests or notes...',
          fillColor: AppColors.white,
          borderColor: AppColors.linenGrey,
        ),
        CustomFilledButton(
          width: double.infinity,
          backgroundColor: AppColors.lagoonTeal,
          onPressed: () => widget.onSubmit(_notesController.text),
          child: const Text('Send Request'),
        ),
      ],
    );
  }
}
