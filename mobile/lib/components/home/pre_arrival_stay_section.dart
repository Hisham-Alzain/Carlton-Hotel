import 'package:carlton/controllers/home/home_controller.dart';
import 'package:carlton/customWidgets/custom_containers.dart';
import 'package:carlton/customWidgets/custom_filled_button.dart';
import 'package:carlton/customWidgets/custom_image.dart';
import 'package:carlton/l10n/app_translations.dart';
import 'package:carlton/services/check_in_service.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';

/// Dark teal hero (Figma `2237:4237`). Owns the pre-arrival progress bar, which
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
                // Section rhythm. The inner groups keep their own tighter
                // gaps so a label stays visually bound to the value under it.
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
                            style: Get.textTheme.bodySmall?.copyWith(
                              fontFamily: 'DM Sans',
                              color: AppColors.white.withValues(alpha: 0.6),
                            ),
                          ),
                        ],
                      ),
                    ],
                  ),
                  Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
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
                    crossAxisAlignment: CrossAxisAlignment.start,
                    spacing: 5,
                    children: [
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
                  // Always live: check-in is open for the whole pre-arrival
                  // span, so this section only renders when it is actionable.
                  // The "Home Pre-checkin" frame (`2237:4237`) now exists, so
                  // this section is no longer the provisional composition it
                  // began as — hero, checklist and CTA are all in the design.
                  CustomFilledButton(
                    height: 48,
                    width: double.infinity,
                    onPressed: Get.find<HomeController>().startCheckIn,
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
                  begin: AlignmentDirectional.topStart,
                  end: AlignmentDirectional.bottomEnd,
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
      // The eyebrow sits 2 off the value; the value and its hint are flush, so
      // they nest as their own group rather than inheriting that 2.
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        spacing: 2,
        children: [
          Text(
            label.toUpperCase(),
            style: Get.textTheme.labelSmall?.copyWith(
              fontSize: 9,
              letterSpacing: 0.5,
              color: AppColors.white.withValues(alpha: 0.97),
            ),
          ),
          Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
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
        ],
      ),
    );
  }
}
