import 'package:carlton/theme/theme.dart';
import 'package:carlton/components/check_in/arrival_time_sheet.dart';
import 'package:carlton/customWidgets/custom_containers.dart';
import 'package:carlton/customWidgets/custom_filled_button.dart';
import 'package:carlton/l10n/app_translations.dart';
import 'package:carlton/models/check_in/pre_arrival_step.dart';
import 'package:carlton/routes/routes.dart';
import 'package:carlton/services/check_in_service.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:flutter_svg/flutter_svg.dart';
import 'package:get/get.dart';

part 'airport_transfer_section.dart';

part 'pre_arrival_checklist.dart';

part 'pre_arrival_stay_widgets.dart';

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
                spacing: 16,
                children: [
                  Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    spacing: 8,
                    children: [
                      Row(
                        spacing: 6,
                        children: [
                          _StatusChip(
                            label: AppTranslations.roomChip(
                              reservation.roomNumber,
                            ),
                            background: AppColors.antiqueGold,
                          ),
                          _StatusChip(
                            label: AppTranslations.preCheckInAvailable,
                            background: AppColors.harborTeal,
                            showLiveDot: true,
                          ),
                        ],
                      ),
                      Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
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
                            style: Get.textTheme.dmBodySmall?.copyWith(
                              color: AppColors.white.withValues(alpha: 0.6),
                            ),
                          ),
                        ],
                      ),
                    ],
                  ),
                  Column(
                    spacing: 8,
                    children: [
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
                      Row(
                        spacing: 8,
                        children: [
                          Expanded(
                            child: _ReservationDetailTile(
                              label: AppTranslations.roomLabel,
                              value: AppTranslations.suiteNumber(
                                reservation.roomNumber,
                              ),
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
                    ],
                  ),
                  Column(
                    spacing: 5,
                    children: [
                      Row(
                        children: [
                          Text(
                            AppTranslations.preArrivalProgress,
                            style: Get.textTheme.dmBodySmall?.copyWith(
                              fontSize: 11,
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
                      ClipRRect(
                        borderRadius: BorderRadius.circular(2),
                        child: LinearProgressIndicator(
                          value: service.progress,
                          minHeight: 3,
                          color: AppColors.antiqueGold,
                          backgroundColor: AppColors.white.withValues(
                            alpha: 0.15,
                          ),
                        ),
                      ),
                    ],
                  ),
                  CustomFilledButton(
                    height: 48,
                    width: double.infinity,
                    onPressed: () => Get.toNamed(Routes.checkIn),
                    backgroundColor: AppColors.white,
                    foregroundColor: AppColors.primary,
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(10),
                    ),
                    textStyle: Get.textTheme.dmLabelLarge?.copyWith(
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
