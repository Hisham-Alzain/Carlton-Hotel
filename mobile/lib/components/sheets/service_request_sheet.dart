import 'package:carlton/components/sheets/note_sheet_body.dart';
import 'package:carlton/l10n/app_translations.dart';
import 'package:carlton/models/service_catalog_item.dart';
import 'package:flutter/material.dart';

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
    final mins = widget.option.expectedMinutes;
    final stayLabel = widget.stayLabel;
    final target = AppTranslations.requestFor(
      stayLabel.isEmpty ? AppTranslations.requestTargetRoom : stayLabel,
    );
    final eta = mins != null
        ? AppTranslations.teamWithinMinutes(AppTranslations.etaMinutes(mins))
        : AppTranslations.teamShortly;

    return NoteSheetBody(
      info: '$target$eta',
      label: AppTranslations.transferInstructions,
      controller: _notesController,
      hintText: AppTranslations.requestNotesHint,
      buttonLabel: AppTranslations.sendRequest,
      onSubmit: () => widget.onSubmit(_notesController.text),
    );
  }
}
