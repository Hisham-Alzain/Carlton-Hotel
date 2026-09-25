import 'package:carlton/extensions/price_extension.dart';
import 'package:carlton/l10n/app_translations.dart';
import 'package:carlton/customWidgets/custom_containers.dart';
import 'package:carlton/customWidgets/custom_filled_button.dart';
import 'package:carlton/customWidgets/custom_image.dart';
import 'package:carlton/customWidgets/custom_texts.dart';
import 'package:carlton/models/booking_models.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:flutter_svg/flutter_svg.dart';
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

  const CustomRoomResultCard({
    required this.room,
    required this.nights,
    required this.onSelect,
    this.onTap,
    super.key,
  });

  @override
  Widget build(BuildContext context) {
    final TextTheme textStyle = Get.textTheme;

    final stayTotal = room.pricePerNight * (nights == 0 ? 1 : nights);
    return InkWell(
      onTap: onTap,
      child: Card(
        clipBehavior: Clip.antiAlias,
        color: AppColors.white,
        shape: ContinuousRectangleBorder(
          borderRadius: BorderRadiusGeometry.circular(14),
        ),
        margin: const EdgeInsets.all(10),
        elevation: 1,
        child: Column(
          spacing: 10,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Stack(
              children: [
                CustomImage(
                  source: room.images.first,
                  width: double.infinity,
                  height: 150,
                  fit: BoxFit.cover,
                ),
                Padding(
                  padding: const EdgeInsets.all(10),
                  child: Align(
                    alignment: AlignmentDirectional.topEnd,
                    child: PillContainer(
                      backgroundColor: AppColors.white88,
                      child: Text.rich(
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
                              text: '/night',
                              style: textStyle.labelSmall?.copyWith(
                                fontFamily: 'DM Sans',
                                color: AppColors.taupeBrown,
                              ),
                            ),
                          ],
                        ),
                      ),
                    ),
                  ),
                ),
              ],
            ),
            Padding(
              padding: const EdgeInsets.all(10),
              child: Column(
                spacing: 10,
                crossAxisAlignment: CrossAxisAlignment.start,
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

                      Row(
                        spacing: 10,
                        children: [
                          SvgPicture.asset(
                            'assets/icons/star.svg',
                            width: 20,
                            height: 20,
                            colorFilter: const ColorFilter.mode(
                              AppColors.antiqueGold,
                              BlendMode.srcIn,
                            ),
                          ),

                          Text(
                            room.rating.toStringAsFixed(1),
                            style: textStyle.labelMedium?.copyWith(
                              fontFamily: 'DM Sans',
                              fontWeight: FontWeight.w600,
                              color: AppColors.inkBlack,
                            ),
                          ),

                          Text(
                            '(${room.reviewCount})',
                            style: textStyle.labelSmall?.copyWith(
                              fontFamily: 'DM Sans',
                              color: AppColors.taupeBrown,
                            ),
                          ),
                        ],
                      ),
                    ],
                  ),

                  Wrap(
                    spacing: 10,
                    runSpacing: 10,
                    children: [
                      _meta('assets/icons/space.svg', room.area),
                      _meta('assets/icons/view.svg', room.view),
                      _meta('assets/icons/king_bed.svg', room.bed),
                    ],
                  ),

                  Wrap(
                    spacing: 10,
                    runSpacing: 10,
                    children: room.amenityChips.map(_chip).toList(),
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
                            'Total for $nights night${nights == 1 ? '' : 's'}',
                            style: textStyle.labelMedium?.copyWith(
                              fontFamily: 'DM Sans',
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
                        ],
                      ),
                      CustomFilledButton(
                        backgroundColor: AppColors.lagoonTeal,
                        onPressed: onSelect,
                        child: Text(AppTranslations.selectRoom),
                      ),
                    ],
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _meta(String iconPath, String text) {
    final TextTheme textStyle = Get.textTheme;
    return RowTextComponent(
      iconPath: iconPath,
      iconColor: AppColors.graphite,
      text: text,
      textStyle: textStyle.labelSmall?.copyWith(
        fontWeight: FontWeight.w300,
        color: AppColors.graphite,
      ),
      spacing: 10,
    );
  }

  Widget _chip(String label) {
    final TextTheme textStyle = Get.textTheme;
    return PillContainer(
      padding: const EdgeInsets.all(10),
      backgroundColor: AppColors.cream,
      radius: 4,
      child: Text(
        label,
        style: textStyle.labelSmall?.copyWith(
          fontFamily: 'DM Sans',
          color: AppColors.cocoaGold,
        ),
      ),
    );
  }
}
