import 'package:carlton/customWidgets/custom_image.dart';
import 'package:carlton/customWidgets/custom_texts.dart';
import 'package:carlton/models/booking_models.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:flutter_svg/flutter_svg.dart';

/// Room result card for "Choose Your Room" (Step 2), matched to Figma: a
/// white 14px-radius card with a soft shadow, a 150px image with a "$/night"
/// badge, title + compact rating on one row, a meta row, cream amenity chips,
/// and a divider footer with the total + a Select Room button.
class CustomRoomResultCard extends StatelessWidget {
  final RoomOption room;
  final int nights;
  final VoidCallback onSelect;
  final VoidCallback? onTap;

  const CustomRoomResultCard({
    required this.room,
    required this.nights,
    required this.onSelect,
    this.onTap,
    super.key,
  });

  @override
  Widget build(BuildContext context) {
    final total = room.pricePerNight * (nights == 0 ? 1 : nights);
    return Material(
      color: AppColors.surface,
      borderRadius: BorderRadius.circular(14),
      clipBehavior: Clip.antiAlias,
      elevation: 0,
      child: DecoratedBox(
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(14),
          boxShadow: const [
            BoxShadow(
              color: Color(0x14000000),
              blurRadius: 12,
              offset: Offset(0, 2),
            ),
          ],
        ),
        child: InkWell(
          onTap: onTap,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Stack(
                children: [
                  CustomImage(
                    path: room.images.first,
                    width: double.infinity,
                    height: 150,
                    fit: BoxFit.cover,
                  ),
                  Positioned(right: 14, top: 14, child: _priceBadge()),
                ],
              ),
              Padding(
                padding: const EdgeInsets.all(14),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Expanded(
                          child: Text(
                            room.name,
                            style: const TextStyle(
                              fontFamily: 'Plus Jakarta Sans',
                              fontSize: 15,
                              fontWeight: FontWeight.w600,
                              color: AppColors.navLabel,
                            ),
                          ),
                        ),
                        const SizedBox(width: 8),
                        _rating(),
                      ],
                    ),
                    const SizedBox(height: 6),
                    Wrap(
                      spacing: 10.5,
                      runSpacing: 4,
                      children: [
                        _meta('assets/icons/space.svg', room.area),
                        _meta('assets/icons/view.svg', room.view),
                        _meta('assets/icons/king_bed.svg', room.bed),
                      ],
                    ),
                    const SizedBox(height: 8),
                    Wrap(
                      spacing: 6,
                      runSpacing: 6,
                      children: [for (final c in room.amenityChips) _chip(c)],
                    ),
                    const SizedBox(height: 12),
                    Container(
                      padding: const EdgeInsets.only(top: 11.18),
                      decoration: const BoxDecoration(
                        border: Border(
                          top: BorderSide(
                            color: AppColors.hairlineFaint,
                            width: 1.18,
                          ),
                        ),
                      ),
                      child: Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        crossAxisAlignment: CrossAxisAlignment.center,
                        children: [
                          Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              Text(
                                'Total for $nights night${nights == 1 ? '' : 's'}',
                                style: const TextStyle(
                                  fontFamily: 'DM Sans',
                                  fontSize: 12,
                                  color: AppColors.textMuted,
                                ),
                              ),
                              Text(
                                '\$$total',
                                style: const TextStyle(
                                  fontFamily: 'Plus Jakarta Sans',
                                  fontSize: 17,
                                  fontWeight: FontWeight.w700,
                                  color: AppColors.primary,
                                ),
                              ),
                            ],
                          ),
                          _selectButton(),
                        ],
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _priceBadge() => Container(
    padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 4),
    decoration: BoxDecoration(
      color: const Color(0xE0FFFFFF),
      borderRadius: BorderRadius.circular(6),
    ),
    child: Text.rich(
      TextSpan(
        children: [
          TextSpan(
            text: '\$${room.pricePerNight}',
            style: const TextStyle(
              fontFamily: 'Plus Jakarta Sans',
              fontSize: 13,
              fontWeight: FontWeight.w700,
              color: AppColors.primary,
            ),
          ),
          const TextSpan(
            text: '/night',
            style: TextStyle(
              fontFamily: 'DM Sans',
              fontSize: 11,
              color: AppColors.textMuted,
            ),
          ),
        ],
      ),
    ),
  );

  Widget _rating() => Row(
    mainAxisSize: MainAxisSize.min,
    children: [
      SvgPicture.asset('assets/icons/star.svg', width: 12, height: 12),
      const SizedBox(width: 3),
      Text(
        room.rating.toStringAsFixed(1),
        style: const TextStyle(
          fontFamily: 'DM Sans',
          fontSize: 12,
          fontWeight: FontWeight.w600,
          color: AppColors.navLabel,
        ),
      ),
      const SizedBox(width: 3),
      Text(
        '(${room.reviewCount})',
        style: const TextStyle(
          fontFamily: 'DM Sans',
          fontSize: 11,
          color: AppColors.textMuted,
        ),
      ),
    ],
  );

  Widget _meta(String iconPath, String text) => RowTextComponent(
    path: iconPath,
    iconSize: 10,
    iconColor: AppColors.metaText,
    text: text,
    textStyle: const TextStyle(
      fontFamily: 'Plus Jakarta Sans',
      fontSize: 11,
      fontWeight: FontWeight.w300,
      color: AppColors.metaText,
    ),
    spacing: 4,
  );

  Widget _chip(String label) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
    decoration: BoxDecoration(
      color: AppColors.cream,
      borderRadius: BorderRadius.circular(4),
    ),
    child: Text(
      label,
      style: const TextStyle(
        fontFamily: 'DM Sans',
        fontSize: 10,
        color: AppColors.chipTextGold,
      ),
    ),
  );

  Widget _selectButton() => Material(
    color: AppColors.teal,
    borderRadius: BorderRadius.circular(8),
    child: InkWell(
      onTap: onSelect,
      borderRadius: BorderRadius.circular(8),
      child: const Padding(
        padding: EdgeInsets.symmetric(horizontal: 20, vertical: 10),
        child: Text(
          'Select Room',
          style: TextStyle(
            fontFamily: 'DM Sans',
            fontSize: 13,
            fontWeight: FontWeight.w500,
            color: Colors.white,
          ),
        ),
      ),
    ),
  );
}
