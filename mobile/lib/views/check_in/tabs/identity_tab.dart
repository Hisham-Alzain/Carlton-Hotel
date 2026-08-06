import 'dart:io';

import 'package:carlton/components/check_in/check_in_booking_panel.dart';
import 'package:carlton/controllers/check_in/check_in_controller.dart';
import 'package:carlton/customWidgets/custom_containers.dart';
import 'package:carlton/customWidgets/custom_filled_button.dart';
import 'package:carlton/l10n/app_translations.dart';
import 'package:carlton/models/check_in/check_in_enums.dart';
import 'package:carlton/routes/routes.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:flutter_svg/flutter_svg.dart';
import 'package:get/get.dart';

/// Identity tab. Figma 75:463 (notStarted) and 75:565 (verified) are ONE view
/// in two states — everything above the state block is identical in both.
class IdentityTab extends GetView<CheckInController> {
  const IdentityTab({super.key});

  @override
  Widget build(BuildContext context) {
    // Scrollable, not a ListView: a fixed short column, so lazy building
    // would buy nothing.
    return SingleChildScrollView(
      padding: const EdgeInsets.all(20),
      child: Obx(() {
        final verified =
            controller.service.identity.value == IdentityStatus.verified;
        return Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          spacing: 20,
          children: [
            if (!verified) const _IdentityHeader(),
            CheckInBookingPanel(
              reservation: controller.service.reservation.value,
            ),
            if (verified) const _VerifiedCard() else const _ScanPrompt(),
            if (!verified) const _SecurityNote(),
            AbsorbPointer(
              key: const Key('identity-continue-gate'),
              absorbing: !verified,
              child: Opacity(
                opacity: verified ? 1 : 0.4,
                child: CustomFilledButton(
                  height: 50,
                  onPressed: () => controller.advanceTo(1),
                  backgroundColor: AppColors.lagoonTeal,
                  child: Text(AppTranslations.continueToPreferences),
                ),
              ),
            ),
          ],
        );
      }),
    );
  }
}

class _IdentityHeader extends StatelessWidget {
  const _IdentityHeader();

  @override
  Widget build(BuildContext context) {
    return Column(
      spacing: 10,
      children: [
        CustomIconChip.circle(
          size: 50,
          backgroundColor: AppColors.primary08,
          child: SvgPicture.asset(
            'assets/icons/chk_identity_chip.svg',
            width: 24,
          ),
        ),
        Text(
          AppTranslations.identityTitle,
          textAlign: TextAlign.center,
          style: Get.textTheme.headlineSmall?.copyWith(
            color: AppColors.primary,
          ),
        ),
        Text(
          AppTranslations.identitySubtitle,
          textAlign: TextAlign.center,
          style: Get.textTheme.bodyMedium?.copyWith(
            color: AppColors.taupeBrown,
          ),
        ),
      ],
    );
  }
}

class _ScanPrompt extends StatelessWidget {
  const _ScanPrompt();

  @override
  Widget build(BuildContext context) {
    return Card(
      color: Colors.white,
      elevation: 0,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(10),
        side: const BorderSide(color: AppColors.linenTaupe30),
      ),
      child: Padding(
        padding: const EdgeInsets.all(20),
        child: Column(
          spacing: 15,
          children: [
            // The white-stroked chk_id_card is for the teal Scan button; on
            // this light card the dark-stroked chip variant is the readable one.
            SvgPicture.asset('assets/icons/chk_identity_chip.svg', width: 30),
            Text(
              AppTranslations.tapToScanId,
              style: Get.textTheme.titleMedium?.copyWith(
                color: AppColors.primary,
              ),
            ),
            Text(
              AppTranslations.passportOrNationalId,
              style: Get.textTheme.bodySmall?.copyWith(
                color: AppColors.taupeBrown,
              ),
            ),
            CustomFilledButton(
              height: 50,
              width: double.infinity,
              onPressed: () => Get.toNamed(Routes.scanId),
              backgroundColor: AppColors.nearBlack,
              child: Text(AppTranslations.scanId),
            ),
          ],
        ),
      ),
    );
  }
}

class _VerifiedCard extends GetView<CheckInController> {
  const _VerifiedCard();

  @override
  Widget build(BuildContext context) {
    return Card(
      color: AppColors.successGreen08,
      elevation: 0,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(10),
        side: const BorderSide(color: AppColors.successGreen),
      ),
      child: Padding(
        padding: const EdgeInsets.all(15),
        child: Column(
          spacing: 10,
          children: [
            Row(
              spacing: 10,
              children: [
                const Icon(
                  Icons.check_circle,
                  color: AppColors.successGreen,
                  size: 30,
                ),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    spacing: 5,
                    children: [
                      Text(
                        AppTranslations.identityVerified,
                        style: Get.textTheme.titleSmall?.copyWith(
                          color: AppColors.primary,
                        ),
                      ),
                      Text(
                        'Passport #${controller.service.documentNumber.value} · '
                        '${controller.service.reservation.value.guestName}',
                        style: Get.textTheme.bodySmall?.copyWith(
                          color: AppColors.taupeBrown,
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ),
            // Only the scanner produces a local file; the API upload route
            // verifies identity without one, so this stays conditional.
            const _CapturedDocumentThumbnail(),
          ],
        ),
      ),
    );
  }
}

/// The photo the guest took in the scanner, if they used the camera rather
/// than the upload route. Reads its own Obx so a late capture repaints just
/// the thumbnail.
class _CapturedDocumentThumbnail extends GetView<CheckInController> {
  const _CapturedDocumentThumbnail();

  @override
  Widget build(BuildContext context) {
    return Obx(() {
      final String path = controller.service.documentImagePath.value;
      if (path.isEmpty) return const SizedBox.shrink();

      final File file = File(path);
      // The file lives in app documents, but a reinstall or a manual clear can
      // still remove it out from under us.
      if (!file.existsSync()) return const SizedBox.shrink();

      return ClipRRect(
        borderRadius: BorderRadius.circular(8),
        child: AspectRatio(
          aspectRatio: 85.6 / 54,
          child: Image.file(file, fit: BoxFit.cover),
        ),
      );
    });
  }
}

class _SecurityNote extends StatelessWidget {
  const _SecurityNote();

  @override
  Widget build(BuildContext context) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      spacing: 10,
      children: [
        const Icon(Icons.info_outline, size: 16, color: AppColors.taupeBrown),
        Expanded(
          child: Text(
            AppTranslations.identitySecurityNote,
            style: Get.textTheme.bodySmall?.copyWith(
              color: AppColors.taupeBrown,
            ),
          ),
        ),
      ],
    );
  }
}
