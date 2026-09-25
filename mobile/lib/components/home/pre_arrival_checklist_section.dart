import 'package:carlton/components/check_in/arrival_time_sheet.dart';
import 'package:carlton/components/home/glass_card_style.dart';
import 'package:carlton/controllers/home/home_controller.dart';
import 'package:carlton/customWidgets/custom_containers.dart';
import 'package:carlton/l10n/app_translations.dart';
import 'package:carlton/models/check_in/pre_arrival_step.dart';
import 'package:carlton/routes/routes.dart';
import 'package:carlton/services/check_in_service.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:flutter_svg/flutter_svg.dart';
import 'package:get/get.dart';

/// Four-row checklist (Figma `2237:4237`), ticking live from CheckInService.
/// One Obx per row, so completing one step rebuilds one row rather than the
/// card.
class PreArrivalChecklistSection extends StatelessWidget {
  const PreArrivalChecklistSection({super.key});

  @override
  Widget build(BuildContext context) {
    final CheckInService service = CheckInService.find;
    return Container(
      margin: const EdgeInsets.only(bottom: 20),
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
      decoration: BoxDecoration(
        color: AppColors.white.withValues(alpha: 0.14),
        borderRadius: BorderRadius.circular(glassCardRadius),
        border: Border.fromBorderSide(glassCardBorder),
        boxShadow: const [glassCardShadow],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            AppTranslations.preArrivalChecklist,
            style: Get.textTheme.titleMedium?.copyWith(
              fontSize: 15,
              fontWeight: FontWeight.w600,
              color: AppColors.inkBlack,
            ),
          ),
          Obx(
            () => _ChecklistItemRow(
              asset: 'assets/icons/chk_contact_check.svg',
              title: AppTranslations.confirmContactDetails,
              subtitle: AppTranslations.completedLabel,
              isDone: service.isStepComplete(PreArrivalStep.contactDetails),
              onTap: null,
            ),
          ),
          Obx(
            () => _ChecklistItemRow(
              asset: 'assets/icons/chk_upload_doc.svg',
              title: AppTranslations.uploadIdOrPassport,
              subtitle: AppTranslations.requiredForCheckIn,
              isDone: service.isStepComplete(PreArrivalStep.identity),
              // Straight to the scanner: this row *is* the capture action, so
              // routing to the wizard shell would cost the guest a second tap.
              onTap: () => Get.toNamed(Routes.scanId),
            ),
          ),
          Obx(
            () => _ChecklistItemRow(
              asset: 'assets/icons/chk_clock.svg',
              title: AppTranslations.setArrivalTime,
              subtitle: AppTranslations.tapToAddEta,
              isDone: service.isStepComplete(PreArrivalStep.arrivalTime),
              onTap: showArrivalTimeSheet,
            ),
          ),
          Obx(
            () => _ChecklistItemRow(
              asset: 'assets/icons/chk_bell.svg',
              title: AppTranslations.preArrivalSpecialRequests,
              subtitle: AppTranslations.optionalPreferences,
              isDone: service.isStepComplete(PreArrivalStep.specialRequests),
              onTap: Get.find<HomeController>().startCheckIn,
              isLast: true,
            ),
          ),
        ],
      ),
    );
  }
}

class _ChecklistItemRow extends StatelessWidget {
  final String asset;
  final String title;
  final String subtitle;
  final bool isDone;
  final VoidCallback? onTap;

  /// Suppresses the hairline under the final row, which would otherwise draw a
  /// line against the card's own padding.
  final bool isLast;

  const _ChecklistItemRow({
    required this.asset,
    required this.title,
    required this.subtitle,
    required this.isDone,
    required this.onTap,
    this.isLast = false,
  });

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      child: Container(
        padding: const EdgeInsets.symmetric(vertical: 11),
        decoration: BoxDecoration(
          border: isLast
              ? null
              : const Border(bottom: BorderSide(color: AppColors.frostGrey)),
        ),
        child: Row(
          spacing: 12,
          children: [
            CustomIconChip(
              size: 34,
              // The tint tracks completion, so a finished row reads as done
              // from the badge alone.
              backgroundColor: isDone
                  ? AppColors.successGreen08
                  : AppColors.antiqueGold08,
              radius: 9,
              // Figma SVGs carry their own stroke colour per row.
              child: SvgPicture.asset(asset, width: 16),
            ),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    title,
                    style: Get.textTheme.titleSmall?.copyWith(
                      fontSize: 13,
                      color: isDone
                          ? AppColors.successGreen
                          : AppColors.inkBlack,
                    ),
                  ),
                  Text(
                    subtitle,
                    style: Get.textTheme.bodySmall?.copyWith(
                      fontSize: 11,
                      fontFamily: 'DM Sans',
                      color: AppColors.ashBrown,
                    ),
                  ),
                ],
              ),
            ),
            _ChecklistCheckbox(isDone: isDone),
          ],
        ),
      ),
    );
  }
}

/// The trailing state dot: a filled green tick once done, an empty ring until
/// then (both states inside Figma `2237:4237`).
class _ChecklistCheckbox extends StatelessWidget {
  final bool isDone;

  const _ChecklistCheckbox({required this.isDone});

  @override
  Widget build(BuildContext context) {
    return CustomIconChip.circle(
      size: 22,
      backgroundColor: isDone ? AppColors.successGreen : Colors.transparent,
      border: Border.all(
        color: isDone ? AppColors.successGreen : AppColors.black20,
      ),
      child: isDone
          ? SvgPicture.asset(
              'assets/icons/check.svg',
              width: 12,
              height: 12,
              colorFilter: const ColorFilter.mode(
                AppColors.white,
                BlendMode.srcIn,
              ),
            )
          : const SizedBox.shrink(),
    );
  }
}
