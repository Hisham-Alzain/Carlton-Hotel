import 'package:carlton/constants/demo_data.dart';
import 'package:carlton/customWidgets/custom_filled_button.dart';
import 'package:carlton/customWidgets/custom_snackbar.dart';
import 'package:carlton/customWidgets/custom_text_field.dart';
import 'package:carlton/models/service_models.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:flutter_svg/flutter_svg.dart';
import 'package:get/get.dart';

/// Confirm-a-request bottom sheet body for one [ServiceOption], shown via
/// `ServicesController.openServiceRequest` -> `CustomBottomSheet.show`.
///
/// The option's title and description are rendered by the sheet shell's
/// header, so they are deliberately absent here.
class ServiceRequestSheet extends StatefulWidget {
  final ServiceOption option;

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
    final textStyle = Get.textTheme;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      spacing: 16,
      children: [
        Container(
          padding: const EdgeInsets.all(12),
          decoration: BoxDecoration(
            color: AppColors.cream,
            borderRadius: BorderRadius.circular(10),
          ),
          child: Row(
            spacing: 10,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              SvgPicture.asset(
                'assets/icons/info.svg',
                height: 14,
                width: 14,
                colorFilter: const ColorFilter.mode(
                  AppColors.walnutGold,
                  BlendMode.srcIn,
                ),
              ),
              Expanded(
                child: Text.rich(
                  TextSpan(
                    children: [
                      const TextSpan(text: 'This request is for '),
                      TextSpan(
                        text: '${DemoData.room} · ${DemoData.stayRoomName}',
                        style: const TextStyle(fontWeight: FontWeight.w700),
                      ),
                      TextSpan(
                        text:
                            '. Our team will be with you within '
                            '${widget.option.etaLabel}.',
                      ),
                    ],
                  ),
                  style: textStyle.labelMedium?.copyWith(
                    fontSize: 12,
                    height: 1.45,
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
            fontSize: 11,
            fontWeight: FontWeight.w700,
            letterSpacing: 0.9,
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
          height: 52,
          backgroundColor: AppColors.lagoonTeal,
          onPressed: () {
            Get.back();
            CustomSnackbars.showSuccess(
              message: "Request sent — we'll be in touch shortly",
            );
          },
          child: const Text('Send Request'),
        ),
      ],
    );
  }
}
