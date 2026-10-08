import 'package:carlton/components/cards/custom_rating_label.dart';
import 'package:carlton/components/cards/image_header_card.dart';
import 'package:carlton/theme/theme.dart';
import 'package:carlton/extensions/price_extension.dart';
import 'package:carlton/l10n/app_translations.dart';
import 'package:carlton/customWidgets/custom_filled_button.dart';
import 'package:carlton/customWidgets/custom_texts.dart';
import 'package:carlton/models/booking_models.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';

/// Room result card for "Choose Your Room" (Step 2), matched to Figma: a
/// white 14px-radius card with a soft shadow, a 150px image with a "$/night"
/// badge, title + compact rating on one row, a meta row, cream amenity chips,
/// and a divider footer with the total + a Select Room button.
class CustomRoomResultCard extends StatelessWidget {
  final RoomOption room;
  final int nights;
  final VoidCallback onSelect;
  final VoidCallback? onTap;

  /// Rooms free over the guest's dates, from `GET /public/availability`, or null
  /// when the check has not answered. Null renders as bookable — the
  /// reservation endpoint re-checks, so an unanswered pre-check must not hide a
  /// room that is free.
  final int? roomsAvailable;

  /// False when the room takes fewer guests than the guest chose: the button
  /// greys out like a sold-out room, and [RoomOption.maxOccupancy] says why.
  final bool fitsParty;

  const CustomRoomResultCard({
    required this.room,
    required this.nights,
    required this.onSelect,
    this.onTap,
    this.roomsAvailable,
    this.fitsParty = true,
    super.key,
  });

  bool get _soldOut => roomsAvailable == 0;

  /// Only shown when the hotel is nearly out: "3 left" is useful, "9 left" is
  /// noise, and an unanswered check has nothing to say.
  String get _scarcityLabel {
    final count = roomsAvailable;
    if (count == null || count <= 0 || count > 3) return '';
    return count == 1
        ? AppTranslations.lastRoom
        : AppTranslations.roomsLeft('$count');
  }

  @override
  Widget build(BuildContext context) {
    final TextTheme textStyle = Get.textTheme;

    final stayTotal = room.pricePerNight * (nights == 0 ? 1 : nights);
    return ImageHeaderCard(
      image: room.images.first,
      onTap: onTap,
      corner: Text.rich(
        TextSpan(
          children: [
            TextSpan(
              text: room.pricePerNight.toDouble().formatPrice(),
              style: textStyle.labelMedium?.copyWith(
                fontWeight: FontWeight.w700,
                color: AppColors.primary,
              ),
            ),
            TextSpan(
              text: AppTranslations.perNightSuffix,
              style: textStyle.dmLabelSmall?.copyWith(
                color: AppColors.taupeBrown,
              ),
            ),
          ],
        ),
      ),
      children: [
        Row(
          spacing: 10,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Expanded(
              child: Text(
                room.name,
                style: textStyle.labelLarge?.copyWith(
                  fontWeight: FontWeight.w600,
                  color: AppColors.inkBlack,
                ),
              ),
            ),
            CustomRatingLabel(rating: room.rating, reviews: room.reviewCount),
          ],
        ),
        Wrap(
          spacing: 10,
          runSpacing: 10,
          children: [
            _meta('assets/icons/space.svg', room.area),
            _meta('assets/icons/view.svg', room.view),
            _meta('assets/icons/king_bed.svg', room.bed),
            if (room.maxOccupancy != null)
              _meta(
                null,
                AppTranslations.upToGuests(room.maxOccupancy!),
                icon: Icons.people_outline,
                color: fitsParty ? AppColors.graphite : AppColors.brickRed,
              ),
          ],
        ),
        Wrap(
          spacing: 10,
          runSpacing: 10,
          children: [
            for (final label in room.amenityChips) AmenityChip(label: label),
          ],
        ),
        const Divider(color: AppColors.cocoaGold),
        Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          crossAxisAlignment: CrossAxisAlignment.center,
          children: [
            Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(
                  AppTranslations.totalForNights(nights),
                  style: textStyle.dmLabelMedium?.copyWith(
                    color: AppColors.taupeBrown,
                  ),
                ),
                Text(
                  stayTotal.toDouble().formatPrice(),
                  style: textStyle.titleMedium?.copyWith(
                    fontWeight: FontWeight.w700,
                    color: AppColors.primary,
                  ),
                ),
                // Real scarcity off the availability check, not a marketing
                // nudge: absent unless the count is low.
                if (_scarcityLabel.isNotEmpty)
                  Text(
                    _scarcityLabel,
                    style: textStyle.dmLabelSmall?.copyWith(
                      color: AppColors.brickRed,
                    ),
                  ),
              ],
            ),
            CustomFilledButton(
              // Greyed rather than hidden: a sold-out room still tells the
              // guest the hotel has it, which is why the card stays.
              backgroundColor: _soldOut || !fitsParty
                  ? AppColors.mediumGrey
                  : AppColors.lagoonTeal,
              onPressed: onSelect,
              child: Text(
                _soldOut ? AppTranslations.soldOut : AppTranslations.selectRoom,
              ),
            ),
          ],
        ),
      ],
    );
  }

  Widget _meta(
    String? iconPath,
    String text, {
    IconData? icon,
    Color color = AppColors.graphite,
  }) {
    final TextTheme textStyle = Get.textTheme;
    return RowTextComponent(
      iconPath: iconPath,
      icon: icon,
      iconSize: icon == null ? null : 14,
      iconColor: color,
      text: text,
      textStyle: textStyle.labelSmall?.copyWith(
        fontWeight: FontWeight.w300,
        color: color,
      ),
      spacing: 10,
    );
  }
}
