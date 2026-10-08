import 'package:carlton/customWidgets/custom_containers.dart';
import 'package:carlton/customWidgets/custom_filled_button.dart';
import 'package:carlton/customWidgets/custom_text_field.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';

/// The body shared by the "send a note" bottom sheets (a service request, a
/// folio dispute): an info box, a caps label over a multi-line field, and the
/// send button. The sheet's title and subtitle live in the sheet shell. The
/// caller owns [controller] so typed text survives the keyboard's rebuild,
/// and closes the sheet.
class NoteSheetBody extends StatelessWidget {
  final String info;
  final String label;
  final TextEditingController controller;
  final String hintText;
  final int maxLines;
  final int? maxLength;
  final String buttonLabel;
  final Color buttonColor;
  final bool submitting;
  final VoidCallback onSubmit;

  const NoteSheetBody({
    required this.info,
    required this.label,
    required this.controller,
    required this.hintText,
    required this.buttonLabel,
    required this.onSubmit,
    this.maxLines = 3,
    this.maxLength,
    this.buttonColor = AppColors.lagoonTeal,
    this.submitting = false,
    super.key,
  });

  @override
  Widget build(BuildContext context) {
    final TextTheme textStyle = Get.textTheme;

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
                child: Text(
                  info,
                  style: textStyle.labelMedium?.copyWith(
                    color: AppColors.inkBlack,
                  ),
                ),
              ),
            ],
          ),
        ),
        Text(
          label.toUpperCase(),
          style: textStyle.labelSmall?.copyWith(
            fontWeight: FontWeight.w700,
            color: AppColors.dimGrey,
          ),
        ),
        CustomTextField(
          controller: controller,
          textInputType: TextInputType.multiline,
          maxLines: maxLines,
          maxLength: maxLength,
          hintText: hintText,
          fillColor: AppColors.white,
          borderColor: AppColors.linenGrey,
        ),
        CustomFilledButton(
          width: double.infinity,
          backgroundColor: buttonColor,
          isLoading: submitting,
          onPressed: onSubmit,
          child: Text(buttonLabel),
        ),
      ],
    );
  }
}
