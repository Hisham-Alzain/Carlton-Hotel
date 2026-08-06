import 'package:carlton/components/check_in/arrival_time_sheet.dart';
import 'package:carlton/controllers/home/home_controller.dart';
import 'package:carlton/customWidgets/custom_containers.dart';
import 'package:carlton/customWidgets/custom_filled_button.dart';
import 'package:carlton/customWidgets/custom_image.dart';
import 'package:carlton/l10n/app_translations.dart';
import 'package:carlton/models/check_in/pre_arrival_step.dart';
import 'package:carlton/routes/routes.dart';
import 'package:carlton/services/check_in_service.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:flutter_svg/flutter_svg.dart';
import 'package:get/get.dart';

/// The three Home sections that only exist while a guest is booked but has not
/// checked in (Figma `2209:2415`, "Home Pre-checkin"). Kept out of
/// `home_view.dart` so that file stays a section list rather than a 700-line
/// screen.
///
/// Each section owns an Obx scoped to just the state it renders, matching the
/// convention the rest of Home follows.

/// Shared card geometry for the two bordered cards (Figma `2209:2500` and
/// `2209:2555`). Both sit on the ghostWhite scaffold as near-transparent glass
/// — the white hairline and the soft shadow are what actually draw the edge.
const _glassCardRadius = 14.0;
const _glassCardBorder = BorderSide(color: AppColors.white, width: 1.2);
const _glassCardShadow = BoxShadow(
  color: AppColors.slateShadow04,
  blurRadius: 12,
  offset: Offset(0, 2),
);

/// Dark teal hero (Figma `2209:2439`). Owns the pre-arrival progress bar, which
/// reads from CheckInService rather than a hardcoded fraction — completing a
/// wizard step moves it.
class PreArrivalStaySection extends StatelessWidget {
  const PreArrivalStaySection({super.key});

  @override
  Widget build(BuildContext context) {
    final CheckInService service = CheckInService.find;
    return Obx(() {
      final reservation = service.reservation.value;
      return Container(
        margin: const EdgeInsets.only(bottom: 20),
        clipBehavior: Clip.antiAlias,
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(16),
          // Teal almost all the way down, easing into the page colour over the
          // last few percent so the card melts into the scaffold.
          gradient: const LinearGradient(
            begin: Alignment.topCenter,
            end: Alignment.bottomCenter,
            colors: [AppColors.primary, AppColors.ghostWhite],
            stops: [0.954, 1],
          ),
        ),
        child: Stack(
          children: [
            const Positioned.fill(child: _StayHeroBackdrop()),
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 18),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    spacing: 6,
                    children: [
                      _StatusChip(
                        label: AppTranslations.roomChip(reservation.roomNumber),
                        background: AppColors.antiqueGold,
                      ),
                      _StatusChip(
                        label: AppTranslations.preCheckInAvailable,
                        background: AppColors.harborTeal,
                        showLiveDot: true,
                      ),
                    ],
                  ),
                  const SizedBox(height: 8),
                  Text(
                    reservation.suiteName,
                    style: Get.textTheme.titleMedium?.copyWith(
                      fontSize: 18,
                      fontWeight: FontWeight.w600,
                      color: AppColors.white,
                    ),
                  ),
                  Text(
                    reservation.stayRangeLabel,
                    style: Get.textTheme.bodySmall?.copyWith(
                      fontFamily: 'DM Sans',
                      color: AppColors.white.withValues(alpha: 0.6),
                    ),
                  ),
                  const SizedBox(height: 16),
                  Row(
                    spacing: 8,
                    children: [
                      Expanded(
                        child: _ReservationDetailTile(
                          label: AppTranslations.checkInLabel,
                          value: reservation.checkInDate,
                          hint: reservation.checkInTime,
                        ),
                      ),
                      Expanded(
                        child: _ReservationDetailTile(
                          label: AppTranslations.checkOutLabel,
                          value: reservation.checkOutDate,
                          hint: reservation.checkOutTime,
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 8),
                  Row(
                    spacing: 8,
                    children: [
                      Expanded(
                        child: _ReservationDetailTile(
                          label: AppTranslations.roomLabel,
                          value: 'Suite ${reservation.roomNumber}',
                          hint: reservation.suiteName,
                        ),
                      ),
                      Expanded(
                        child: _ReservationDetailTile(
                          label: AppTranslations.bookingRefLabel,
                          value: reservation.bookingRef,
                          hint: AppTranslations.confirmedStatus,
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 20),
                  Row(
                    children: [
                      Text(
                        AppTranslations.preArrivalProgress,
                        style: Get.textTheme.bodySmall?.copyWith(
                          fontSize: 11,
                          fontFamily: 'DM Sans',
                          color: AppColors.white,
                        ),
                      ),
                      const Spacer(),
                      Text(
                        AppTranslations.completeFraction(
                          service.completedCount,
                          service.totalSteps,
                        ),
                        style: Get.textTheme.labelSmall?.copyWith(
                          fontWeight: FontWeight.w600,
                          color: AppColors.sandGold,
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 5),
                  ClipRRect(
                    borderRadius: BorderRadius.circular(2),
                    child: LinearProgressIndicator(
                      value: service.progress,
                      minHeight: 3,
                      color: AppColors.antiqueGold,
                      backgroundColor: AppColors.white.withValues(alpha: 0.15),
                    ),
                  ),
                  const SizedBox(height: 14),
                  CustomFilledButton(
                    height: 48,
                    width: double.infinity,
                    onPressed: () => Get.toNamed(Routes.checkIn),
                    backgroundColor: AppColors.white,
                    foregroundColor: AppColors.primary,
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(10),
                    ),
                    textStyle: Get.textTheme.labelLarge?.copyWith(
                      fontFamily: 'DM Sans',
                      fontWeight: FontWeight.w600,
                    ),
                    child: Text(AppTranslations.checkInNow),
                  ),
                ],
              ),
            ),
          ],
        ),
      );
    });
  }
}

/// The barely-there courtyard photo behind the hero. At 10% it reads as
/// texture, not imagery — the tinted wash on top is what keeps the white type
/// legible over whatever the photo happens to contain.
class _StayHeroBackdrop extends StatelessWidget {
  const _StayHeroBackdrop();

  @override
  Widget build(BuildContext context) {
    return IgnorePointer(
      child: Opacity(
        opacity: 0.1,
        child: Stack(
          fit: StackFit.expand,
          children: [
            const CustomImage(
              source: 'assets/images/room_classic_courtyard.jpg',
              fit: BoxFit.cover,
            ),
            DecoratedBox(
              decoration: BoxDecoration(
                gradient: LinearGradient(
                  begin: Alignment.topLeft,
                  end: Alignment.bottomRight,
                  colors: [
                    AppColors.iceBlue.withValues(alpha: 0.5),
                    AppColors.mistTeal.withValues(alpha: 0.5),
                  ],
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

/// A small caps label on a solid fill — the room number and the pre-check-in
/// banner. [showLiveDot] adds the pulseless status dot the teal chip carries.
class _StatusChip extends StatelessWidget {
  final String label;
  final Color background;
  final bool showLiveDot;

  const _StatusChip({
    required this.label,
    required this.background,
    this.showLiveDot = false,
  });

  @override
  Widget build(BuildContext context) {
    return PillContainer(
      backgroundColor: background,
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
      radius: 6,
      child: Row(
        mainAxisSize: MainAxisSize.min,
        spacing: 6,
        children: [
          if (showLiveDot)
            const DecoratedBox(
              decoration: BoxDecoration(
                color: AppColors.white,
                shape: BoxShape.circle,
              ),
              child: SizedBox.square(dimension: 6),
            ),
          Text(
            label,
            style: Get.textTheme.labelSmall?.copyWith(
              fontSize: 10,
              fontWeight: FontWeight.w600,
              letterSpacing: 0.6,
              color: AppColors.white,
            ),
          ),
        ],
      ),
    );
  }
}

/// One cell of the hero's 2×2 reservation grid.
class _ReservationDetailTile extends StatelessWidget {
  final String label;
  final String value;
  final String hint;

  const _ReservationDetailTile({
    required this.label,
    required this.value,
    required this.hint,
  });

  @override
  Widget build(BuildContext context) {
    return PillContainer(
      backgroundColor: AppColors.deepTeal57,
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
      radius: 10,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            label.toUpperCase(),
            style: Get.textTheme.labelSmall?.copyWith(
              fontSize: 9,
              letterSpacing: 0.5,
              color: AppColors.white.withValues(alpha: 0.97),
            ),
          ),
          const SizedBox(height: 2),
          Text(
            value,
            style: Get.textTheme.titleSmall?.copyWith(
              fontSize: 13,
              fontWeight: FontWeight.w600,
              color: AppColors.white,
            ),
          ),
          if (hint.isNotEmpty)
            Text(
              hint,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: Get.textTheme.bodySmall?.copyWith(
                fontSize: 9,
                fontFamily: 'DM Sans',
                color: AppColors.white.withValues(alpha: 0.94),
              ),
            ),
        ],
      ),
    );
  }
}

/// Four-row checklist (Figma `2209:2500`), ticking live from CheckInService.
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
        borderRadius: BorderRadius.circular(_glassCardRadius),
        border: Border.fromBorderSide(_glassCardBorder),
        boxShadow: const [_glassCardShadow],
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
              onTap: () => Get.toNamed(Routes.checkIn),
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
/// then (Figma `2209:2515` / `2209:2529`).
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
          ? const Icon(Icons.check, size: 12, color: AppColors.white)
          : const SizedBox.shrink(),
    );
  }
}

/// Airport transfer promo (Figma `2209:2555`). Routes into the existing
/// Services tab rather than inventing a second request path.
class AirportTransferSection extends GetView<HomeController> {
  const AirportTransferSection({super.key});

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.only(bottom: 20),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(_glassCardRadius),
        border: Border.fromBorderSide(_glassCardBorder),
        boxShadow: const [_glassCardShadow],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        spacing: 14,
        children: [
          Row(
            spacing: 12,
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  spacing: 8,
                  children: [
                    Text(
                      AppTranslations.airportTransfer,
                      style: Get.textTheme.titleSmall?.copyWith(
                        fontSize: 15,
                        color: AppColors.nearBlack,
                      ),
                    ),
                    Text(
                      AppTranslations.airportTransferBody,
                      style: Get.textTheme.bodySmall?.copyWith(
                        fontSize: 11,
                        color: AppColors.steelGrey,
                      ),
                    ),
                  ],
                ),
              ),
              const CustomImage(
                source: 'assets/images/airport_transfer.png',
                width: 90,
                height: 75,
                fit: BoxFit.contain,
              ),
            ],
          ),
          CustomFilledButton(
            height: 44,
            width: double.infinity,
            onPressed: controller.goToServices,
            backgroundColor: AppColors.lagoonTeal,
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(6),
            ),
            textStyle: Get.textTheme.labelLarge?.copyWith(
              fontSize: 13,
              fontFamily: 'DM Sans',
            ),
            child: Text(AppTranslations.requestAirportTransfer),
          ),
        ],
      ),
    );
  }
}
