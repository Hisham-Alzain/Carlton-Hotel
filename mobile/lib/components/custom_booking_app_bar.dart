import 'package:carlton/customWidgets/custom_step_progress_bar.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:flutter_svg/flutter_svg.dart';
import 'package:get/get.dart';

/// Shared header for the 5 booking steps: back arrow, centered title, a circular
/// close button, and the 6-segment step indicator underneath. The existing
/// `CustomAppBar` is hardwired to the bottom-nav shell, so booking needs its own.
class CustomBookingAppBar extends StatelessWidget
    implements PreferredSizeWidget {
  final String title;
  final int currentStep;
  final int totalSteps;
  final VoidCallback? onBack;
  final VoidCallback? onClose;

  const CustomBookingAppBar({
    required this.title,
    required this.currentStep,
    this.totalSteps = 6,
    this.onBack,
    this.onClose,
    super.key,
  });

  @override
  Size get preferredSize => const Size.fromHeight(72);

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: const BoxDecoration(
        color: AppColors.surface,
        border: Border(bottom: BorderSide(color: AppColors.hairlineFaint)),
      ),
      child: SafeArea(
        bottom: false,
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Padding(
              padding: const EdgeInsets.fromLTRB(20, 14, 20, 12),
              child: Row(
                children: [
                  InkWell(
                    onTap: onBack ?? () => Get.back<void>(),
                    borderRadius: BorderRadius.circular(20),
                    child: SvgPicture.asset(
                      'assets/icons/arrow_left.svg',
                      width: 24,
                      height: 24,
                      colorFilter: const ColorFilter.mode(
                        AppColors.navLabel,
                        BlendMode.srcIn,
                      ),
                    ),
                  ),
                  Expanded(
                    child: Text(
                      title,
                      textAlign: TextAlign.center,
                      style: const TextStyle(
                        fontFamily: 'Plus Jakarta Sans',
                        fontSize: 16,
                        fontWeight: FontWeight.w600,
                        color: AppColors.navLabel,
                      ),
                    ),
                  ),
                  InkWell(
                    onTap: onClose ?? () => Get.until((r) => r.isFirst),
                    customBorder: const CircleBorder(),
                    child: Container(
                      width: 32,
                      height: 32,
                      alignment: Alignment.center,
                      decoration: const BoxDecoration(
                        color: Color(0xBADDDDDD),
                        shape: BoxShape.circle,
                      ),
                      child: SvgPicture.asset(
                        'assets/icons/close.svg',
                        width: 16,
                        height: 16,
                        colorFilter: const ColorFilter.mode(
                          AppColors.navLabel,
                          BlendMode.srcIn,
                        ),
                      ),
                    ),
                  ),
                ],
              ),
            ),
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 20),
              child: CustomStepProgressBar(
                totalSteps: totalSteps,
                currentStep: currentStep,
              ),
            ),
            const SizedBox(height: 8),
          ],
        ),
      ),
    );
  }
}
