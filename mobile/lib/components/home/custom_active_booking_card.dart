import 'package:carlton/customWidgets/custom_image.dart';
import 'package:carlton/models/booking_models.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:flutter_svg/flutter_svg.dart';
import 'package:get/get.dart';

/// The active-stay hero on the reservation-state Home (Figma 2197:3202): a
/// solid teal card (with a faint room photo behind it) carrying a gold room
/// badge, the room name + dates + nights-left, a translucent Do-Not-Disturb
/// row, and four quick actions.
class CustomActiveBookingCard extends StatelessWidget {
  final Stay stay;
  final bool doNotDisturb;
  final ValueChanged<bool> onDndChanged;
  final VoidCallback onRequest;
  final VoidCallback onConcierge;
  final VoidCallback onBill;
  final VoidCallback onCheckout;

  /// When false (guest has booked but not checked in), the same card renders but
  /// the Do-Not-Disturb row and quick actions are greyed and non-interactive —
  /// none of them apply until the guest is actually in-house.
  final bool interactive;

  const CustomActiveBookingCard({
    required this.stay,
    required this.doNotDisturb,
    required this.onDndChanged,
    required this.onRequest,
    required this.onConcierge,
    required this.onBill,
    required this.onCheckout,
    this.interactive = true,
    super.key,
  });

  @override
  Widget build(BuildContext context) {
    final TextTheme textStyle = Get.textTheme;

    return ClipRRect(
      borderRadius: BorderRadius.circular(16),
      child: ColoredBox(
        color: AppColors.primary,
        child: Stack(
          children: [
            if (stay.imagePath != null)
              Positioned.fill(
                child: Opacity(
                  opacity: 0.10,
                  child: CustomImage(
                    source: stay.imagePath!,
                    fit: BoxFit.cover,
                  ),
                ),
              ),
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 18),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            if (stay.subtitle != null)
                              Container(
                                padding: const EdgeInsets.symmetric(
                                  horizontal: 10,
                                  vertical: 3,
                                ),
                                decoration: BoxDecoration(
                                  color: AppColors.antiqueGold,
                                  borderRadius: BorderRadius.circular(6),
                                ),
                                child: Text(
                                  stay.subtitle!.toUpperCase(),
                                  style: textStyle.labelSmall?.copyWith(
                                    fontWeight: FontWeight.w600,
                                    color: AppColors.white,
                                    letterSpacing: 0.5,
                                  ),
                                ),
                              ),
                            const SizedBox(height: 6),
                            Text(
                              stay.roomName,
                              style: textStyle.titleMedium?.copyWith(
                                fontWeight: FontWeight.w600,
                                color: AppColors.white,
                              ),
                            ),
                            Text(
                              '${stay.dateRangeLabel ?? '${stay.checkInLabel} – ${stay.checkOutLabel}'}'
                              ' · ${stay.nightsRemaining} ${interactive ? 'nights remaining' : 'nights'}',
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                              style: textStyle.labelMedium?.copyWith(
                                fontFamily: 'DM Sans',
                                fontSize: 12,
                                color: Colors.white.withValues(alpha: 0.6),
                              ),
                            ),
                          ],
                        ),
                      ),
                      Column(
                        crossAxisAlignment: CrossAxisAlignment.end,
                        children: [
                          Text(
                            '${stay.nightsRemaining ?? 0}',
                            style: textStyle.headlineSmall?.copyWith(
                              fontWeight: FontWeight.w700,
                              color: AppColors.white,
                            ),
                          ),
                          Text(
                            'nights left',
                            style: textStyle.labelSmall?.copyWith(
                              fontFamily: 'DM Sans',
                              color: Colors.white.withValues(alpha: 0.55),
                            ),
                          ),
                        ],
                      ),
                    ],
                  ),
                  const SizedBox(height: 12),
                  // Pre-arrival (interactive == false): same layout, but the
                  // in-stay controls are greyed and non-tappable — DND, Request,
                  // Concierge, My Bill and Checkout only apply once checked in.
                  Opacity(
                    opacity: interactive ? 1 : 0.45,
                    child: IgnorePointer(
                      ignoring: !interactive,
                      child: Column(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          _DndRow(
                            doNotDisturb: doNotDisturb,
                            onChanged: onDndChanged,
                          ),
                          const SizedBox(height: 14),
                          Row(
                            spacing: 8,
                            children: [
                              _QuickAction(
                                iconAsset: 'assets/icons/act_request.svg',
                                label: 'Request',
                                onTap: onRequest,
                              ),
                              _QuickAction(
                                iconAsset: 'assets/icons/act_concierge.svg',
                                label: 'Concierge',
                                onTap: onConcierge,
                              ),
                              _QuickAction(
                                iconAsset: 'assets/icons/act_bill.svg',
                                label: 'My Bill',
                                onTap: onBill,
                              ),
                              _QuickAction(
                                iconAsset: 'assets/icons/act_checkout.svg',
                                label: 'Checkout',
                                onTap: onCheckout,
                              ),
                            ],
                          ),
                        ],
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _DndRow extends StatelessWidget {
  final bool doNotDisturb;
  final ValueChanged<bool> onChanged;

  const _DndRow({required this.doNotDisturb, required this.onChanged});

  @override
  Widget build(BuildContext context) {
    final TextTheme textStyle = Get.textTheme;

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.08),
        borderRadius: BorderRadius.circular(10),
      ),
      child: Row(
        children: [
          Container(
            width: 28,
            height: 28,
            alignment: Alignment.center,
            decoration: BoxDecoration(
              color: Colors.white.withValues(alpha: 0.10),
              borderRadius: BorderRadius.circular(8),
            ),
            child: SvgPicture.asset(
              'assets/icons/dnd_bell.svg',
              width: 14,
              height: 14,
              colorFilter: const ColorFilter.mode(
                AppColors.white,
                BlendMode.srcIn,
              ),
            ),
          ),
          const SizedBox(width: 10),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(
                  'Do Not Disturb',
                  style: textStyle.labelLarge?.copyWith(
                    fontSize: 13,
                    fontWeight: FontWeight.w500,
                    color: AppColors.white,
                  ),
                ),
                Text(
                  doNotDisturb
                      ? 'On — staff will not disturb you'
                      : 'Off — staff may contact you',
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: textStyle.labelSmall?.copyWith(
                    fontFamily: 'DM Sans',
                    fontSize: 10,
                    color: Colors.white.withValues(alpha: 0.5),
                  ),
                ),
              ],
            ),
          ),
          Switch(
            value: doNotDisturb,
            onChanged: onChanged,
            materialTapTargetSize: MaterialTapTargetSize.shrinkWrap,
            activeThumbColor: AppColors.white,
            activeTrackColor: AppColors.antiqueGold,
            inactiveThumbColor: AppColors.white,
            inactiveTrackColor: Colors.white.withValues(alpha: 0.2),
            trackOutlineColor: const WidgetStatePropertyAll(Colors.transparent),
          ),
        ],
      ),
    );
  }
}

class _QuickAction extends StatelessWidget {
  final String iconAsset;
  final String label;
  final VoidCallback onTap;

  const _QuickAction({
    required this.iconAsset,
    required this.label,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return Expanded(
      child: GestureDetector(
        onTap: onTap,
        child: Container(
          padding: const EdgeInsets.only(top: 11, bottom: 9),
          decoration: BoxDecoration(
            color: Colors.white.withValues(alpha: 0.10),
            borderRadius: BorderRadius.circular(10),
            border: Border.all(color: Colors.white.withValues(alpha: 0.15)),
          ),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            spacing: 5,
            children: [
              Container(
                width: 32,
                height: 32,
                alignment: Alignment.center,
                decoration: BoxDecoration(
                  color: Colors.white.withValues(alpha: 0.15),
                  shape: BoxShape.circle,
                ),
                child: SvgPicture.asset(
                  iconAsset,
                  width: 17,
                  height: 17,
                  colorFilter: const ColorFilter.mode(
                    AppColors.white,
                    BlendMode.srcIn,
                  ),
                ),
              ),
              Text(
                label,
                style: Get.textTheme.labelSmall?.copyWith(
                  fontFamily: 'DM Sans',
                  fontWeight: FontWeight.w500,
                  fontSize: 9,
                  letterSpacing: 0.3,
                  color: Colors.white.withValues(alpha: 0.75),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
