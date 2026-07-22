import 'package:carlton/customWidgets/custom_bottom_sheet.dart';
import 'package:carlton/customWidgets/custom_filled_button.dart';
import 'package:carlton/customWidgets/custom_info_banner.dart';
import 'package:carlton/models/booking_models.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:flutter_svg/flutter_svg.dart';
import 'package:get/get.dart';

/// Cancel-reservation confirmation sheet (Figma "CancelModal"). [onConfirm]
/// runs the actual cancellation; "No, Keep" just dismisses.
class CancelSheet extends StatelessWidget {
  final Stay stay;
  final VoidCallback onConfirm;

  const CancelSheet({required this.stay, required this.onConfirm, super.key});

  @override
  Widget build(BuildContext context) {
    final dates = [
      stay.checkInLabel,
      stay.checkOutLabel,
    ].where((e) => e != null).join(' – ');
    return CustomBottomSheet(
      showClose: false,
      actions: [
        CustomFilledButton(
          onPressed: onConfirm,
          backgroundColor: AppColors.danger,
          child: const Text('Yes, Cancel Reservation'),
        ),
        CustomFilledButton(
          onPressed: () => Get.back<void>(),
          backgroundColor: AppColors.softButtonBg,
          foregroundColor: AppColors.navLabel,
          child: const Text('No, Keep My Reservation'),
        ),
      ],
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Center(
            child: Container(
              width: 56,
              height: 56,
              alignment: Alignment.center,
              decoration: const BoxDecoration(
                color: AppColors.dangerSoft,
                shape: BoxShape.circle,
              ),
              child: SvgPicture.asset(
                'assets/icons/warning.svg',
                width: 24,
                height: 24,
                colorFilter: const ColorFilter.mode(
                  AppColors.danger,
                  BlendMode.srcIn,
                ),
              ),
            ),
          ),
          const SizedBox(height: 20),
          const Text(
            'Cancel Reservation?',
            textAlign: TextAlign.center,
            style: TextStyle(
              fontFamily: 'Plus Jakarta Sans',
              fontSize: 18,
              fontWeight: FontWeight.w600,
              color: AppColors.navLabel,
            ),
          ),
          const SizedBox(height: 8),
          Text.rich(
            TextSpan(
              style: const TextStyle(
                fontFamily: 'DM Sans',
                fontSize: 13,
                height: 1.5,
                color: Color(0xFF5D5D5D),
              ),
              children: [
                TextSpan(text: 'Your reservation for the ${stay.roomName}'),
                if (dates.isNotEmpty)
                  TextSpan(
                    text: ' on $dates',
                    style: const TextStyle(fontWeight: FontWeight.w600),
                  ),
                const TextSpan(text: ' will be cancelled.'),
              ],
            ),
            textAlign: TextAlign.center,
          ),
          const SizedBox(height: 17),
          const CustomInfoBanner(
            tone: InfoBannerTone.warning,
            bordered: true,
            message:
                'Free cancellation is currently available for this booking, no charges will apply.',
          ),
        ],
      ),
    );
  }
}
