import 'package:carlton/l10n/app_translations.dart';
import 'package:carlton/customWidgets/custom_containers.dart';
import 'package:carlton/customWidgets/custom_image.dart';
import 'package:carlton/models/booking_models.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:flutter_svg/flutter_svg.dart';
import 'package:get/get.dart';

/// The active-stay hero on the reservation-state Home (Figma 2237:3914): a
/// solid teal card (with a faint room photo behind it) carrying a gold room
/// badge, the room name + dates + nights-left, a translucent Do-Not-Disturb
/// row, and four quick actions.
///
/// The decorated boxes here are [PillContainer] where they size to their child,
/// and [CustomIconChip] for the fixed-size centred icon badges — those need
/// centring and, in one case, a circle, neither of which a radius-based
/// [PillContainer] can express.
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

  /// Date range + nights, skipping any part the stay does not carry.
  ///
  /// Filters on emptiness, not just null: `HomeController._activeToStay` sets
  /// the check-in/out labels to `''` rather than null when the dates are
  /// missing, so a null-only filter left a bare " – " where the range belongs.
  static String _subtitleFor(Stay stay, bool interactive) {
    final range =
        stay.dateRangeLabel ??
        [
          stay.checkInLabel,
          stay.checkOutLabel,
        ].whereType<String>().where((s) => s.isNotEmpty).join(' – ');
    final nights = stay.nightsRemaining;
    final nightsLabel = nights == null
        ? null
        : interactive
        ? AppTranslations.nightsRemainingCount(nights)
        : AppTranslations.nightsCount(nights);
    return [
      range,
      nightsLabel,
    ].where((p) => p != null && p.isNotEmpty).join(' · ');
  }

  @override
  Widget build(BuildContext context) {
    final TextTheme textStyle = Get.textTheme;

    return Card(
      // Matches the other reservation-state sections: the parent SliverPadding
      // owns the inset, so the card carries no margin of its own.
      margin: const EdgeInsets.all(10),
      // cardTheme defaults to featherGrey and sets no surfaceTintColor, so both
      // must be explicit or M3 tints the teal.
      color: AppColors.primary,
      surfaceTintColor: Colors.transparent,
      elevation: 1,
      // Does the job the old ClipRRect did — without it the 10%-opacity
      // background image spills past the rounded corners.
      clipBehavior: Clip.antiAlias,
      // No black06 side, unlike the white siblings: that hairline defines a
      // white card against a near-white page, and on teal it just dirties the
      // edge.
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
      child: Stack(
        children: [
          if (stay.imagePath != null)
            Positioned.fill(
              child: Opacity(
                opacity: 0.10,
                child: CustomImage(source: stay.imagePath!, fit: BoxFit.cover),
              ),
            ),
          Padding(
            padding: const EdgeInsets.all(10),
            child: Column(
              spacing: 10,
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    if (stay.subtitle != null)
                      // radius 6 and EdgeInsets.all(10) are already the
                      // PillContainer defaults.
                      PillContainer(
                        backgroundColor: AppColors.antiqueGold,
                        child: Text(
                          stay.subtitle!.toUpperCase(),
                          style: textStyle.labelSmall?.copyWith(
                            fontWeight: FontWeight.w600,
                            color: AppColors.white,
                            letterSpacing: 0.5,
                          ),
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

                Text(
                  stay.roomName,
                  style: textStyle.titleMedium?.copyWith(
                    fontWeight: FontWeight.w600,
                    color: AppColors.white,
                  ),
                ),
                Text(
                  _subtitleFor(stay, interactive),
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: textStyle.labelSmall?.copyWith(
                    fontFamily: 'DM Sans',
                    color: Colors.white.withValues(alpha: 0.6),
                  ),
                ),
                // Pre-arrival (interactive == false): same layout, but the
                // in-stay controls are greyed and non-tappable — DND, Request,
                // Concierge, My Bill and Checkout only apply once checked in.
                Opacity(
                  opacity: interactive ? 1 : 0.45,
                  child: IgnorePointer(
                    ignoring: !interactive,
                    child: Column(
                      spacing: 10,
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        _DndRow(
                          doNotDisturb: doNotDisturb,
                          onChanged: onDndChanged,
                        ),
                        Row(
                          spacing: 5,
                          children: [
                            _QuickAction(
                              iconAsset: 'assets/icons/act_request.svg',
                              label: AppTranslations.request,
                              onTap: onRequest,
                            ),
                            _QuickAction(
                              iconAsset: 'assets/icons/act_concierge.svg',
                              label: AppTranslations.tileConcierge,
                              onTap: onConcierge,
                            ),
                            _QuickAction(
                              iconAsset: 'assets/icons/act_bill.svg',
                              label: AppTranslations.myBill,
                              onTap: onBill,
                            ),
                            _QuickAction(
                              iconAsset: 'assets/icons/act_checkout.svg',
                              label: AppTranslations.checkout,
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

    return PillContainer(
      backgroundColor: Colors.white.withValues(alpha: 0.08),
      radius: 10,
      child: Row(
        spacing: 10,
        children: [
          CustomIconChip(
            size: 30,
            radius: 8,
            backgroundColor: Colors.white.withValues(alpha: 0.10),
            child: const Icon(
              Icons.notifications_outlined,
              color: Colors.white,
              size: 15,
            ),
          ),
          Expanded(
            child: Column(
              spacing: 10,
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(
                  AppTranslations.dndTitle,
                  style: textStyle.labelMedium?.copyWith(
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
                    color: Colors.white.withValues(alpha: 0.5),
                  ),
                ),
              ],
            ),
          ),
          Switch(value: doNotDisturb, onChanged: onChanged),
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
      child: InkWell(
        onTap: onTap,
        child: PillContainer(
          backgroundColor: Colors.white.withValues(alpha: 0.10),
          radius: 10,
          border: Border.all(color: Colors.white.withValues(alpha: 0.15)),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            spacing: 10,
            children: [
              CustomIconChip.circle(
                size: 30,
                backgroundColor: Colors.white.withValues(alpha: 0.15),
                child: SvgPicture.asset(
                  iconAsset,
                  width: 16,
                  height: 16,
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
                  color: Colors.white.withValues(alpha: 0.75),
                  fontSize: 8,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
