import 'package:carlton/components/custom_circle_icon_button.dart';
import 'package:carlton/controllers/booking/booking_flow_controller.dart';
import 'package:carlton/customWidgets/custom_image_carousel.dart';
import 'package:carlton/customWidgets/custom_info_banner.dart';
import 'package:carlton/customWidgets/custom_price_summary.dart';
import 'package:carlton/customWidgets/custom_rating_stars.dart';
import 'package:carlton/customWidgets/custom_texts.dart';
import 'package:carlton/models/booking_models.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';

/// Shared scrollable body for the room details UI (Figma "One Room Page"):
/// photo carousel, highlights grid, full amenity list, free-cancellation note
/// and price summary. Rendered by both the booking [RoomDetailsSheet] and the
/// standalone RoomDetailsView so the two stay pixel-identical; only the outer
/// chrome and the [actions] block below the price differ.
class RoomDetailsContent extends StatelessWidget {
  final RoomOption room;
  final Widget actions;

  const RoomDetailsContent({
    required this.room,
    required this.actions,
    super.key,
  });

  @override
  Widget build(BuildContext context) {
    final controller = Get.find<BookingFlowController>();
    final total = room.pricePerNight * controller.nights;

    return SingleChildScrollView(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          GetBuilder<BookingFlowController>(
            builder: (c) => CustomImageCarousel(
              images: room.images,
              index: c.roomImageIndex,
              onIndexChanged: c.setRoomImage,
              height: 220,
              topRight: CustomCircleIconButton(
                iconPath: 'assets/icons/close.svg',
                size: 32,
                color: const Color(0xBADDDDDD),
                bordered: false,
                shadow: false,
                iconSize: 16,
                iconPadding: 8,
                onTap: () => Get.back<void>(),
              ),
            ),
          ),
          Padding(
            padding: const EdgeInsets.fromLTRB(20, 16, 20, 0),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(room.name, style: _titleStyle),
                const SizedBox(height: 7),
                Wrap(
                  spacing: 12,
                  runSpacing: 6,
                  children: [
                    _meta('assets/icons/space.svg', room.area),
                    _meta('assets/icons/view.svg', room.view),
                    _meta('assets/icons/king_bed.svg', room.bed),
                  ],
                ),
                const SizedBox(height: 10),
                CustomRatingStars(
                  rating: room.rating,
                  reviewCount: room.reviewCount,
                ),
                const SizedBox(height: 14),
                Text(room.description, style: _descriptionStyle),
                const SizedBox(height: 16),
                const Text('Highlights', style: _headingStyle),
                const SizedBox(height: 12),
                _pairedGrid(room.highlights, _highlightTile, runSpacing: 10),
                const SizedBox(height: 16),
                const Text('All Amenities', style: _headingStyle),
                const SizedBox(height: 12),
                _pairedGrid(room.amenities, _amenityTile, runSpacing: 12),
                const SizedBox(height: 16),
                const CustomInfoBanner(
                  message: 'Free cancellation until 48 hours before check-in.',
                ),
                const SizedBox(height: 16),
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 14),
                  decoration: BoxDecoration(
                    color: AppColors.cream,
                    borderRadius: BorderRadius.circular(10),
                  ),
                  child: CustomPriceSummaryRow(
                    label:
                        'Total for ${controller.nights} night${controller.nights == 1 ? '' : 's'}',
                    value: '\$$total',
                    labelStyle: _totalLabelStyle,
                    valueStyle: _totalValueStyle,
                  ),
                ),
                const SizedBox(height: 20),
                actions,
                SizedBox(height: 20 + MediaQuery.paddingOf(context).bottom),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _pairedGrid<T>(
    List<T> items,
    Widget Function(T) tileBuilder, {
    required double runSpacing,
  }) => Column(
    children: [
      for (var i = 0; i < items.length; i += 2)
        Padding(
          padding: EdgeInsets.only(bottom: runSpacing),
          child: Row(
            spacing: 10,
            children: [
              Expanded(child: tileBuilder(items[i])),
              Expanded(
                child: i + 1 < items.length
                    ? tileBuilder(items[i + 1])
                    : const SizedBox.shrink(),
              ),
            ],
          ),
        ),
    ],
  );

  Widget _highlightTile(IconLabel item) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 15, vertical: 13),
    decoration: BoxDecoration(
      color: AppColors.highlightTileBg,
      borderRadius: BorderRadius.circular(10),
      border: Border.all(color: Colors.white),
    ),
    child: RowTextComponent(
      path: item.iconPath,
      iconSize: 15,
      iconColor: AppColors.gold,
      text: item.label,
      textStyle: _tileTextStyle,
      expandText: true,
    ),
  );

  Widget _amenityTile(IconLabel item) => RowTextComponent(
    path: item.iconPath,
    iconSize: 13,
    iconColor: AppColors.gold,
    text: item.label,
    textStyle: _tileTextStyle,
    spacing: 8,
    expandText: true,
  );

  Widget _meta(String iconPath, String text) => RowTextComponent(
    path: iconPath,
    iconSize: 12,
    iconColor: AppColors.metaText,
    text: text,
    textStyle: const TextStyle(
      fontFamily: 'Plus Jakarta Sans',
      fontSize: 12,
      fontWeight: FontWeight.w300,
      color: AppColors.metaText,
    ),
    spacing: 4,
  );

  static const _titleStyle = TextStyle(
    fontFamily: 'Plus Jakarta Sans',
    fontSize: 19,
    fontWeight: FontWeight.w700,
    color: AppColors.navLabel,
  );

  static const _descriptionStyle = TextStyle(
    fontFamily: 'DM Sans',
    fontSize: 13,
    height: 1.65,
    color: Color(0xFF555555),
  );

  static const _headingStyle = TextStyle(
    fontFamily: 'Plus Jakarta Sans',
    fontSize: 14,
    fontWeight: FontWeight.w600,
    color: AppColors.navLabel,
  );

  static const _tileTextStyle = TextStyle(
    fontFamily: 'DM Sans',
    fontSize: 12,
    color: AppColors.navLabel,
  );

  static const _totalLabelStyle = TextStyle(
    fontFamily: 'DM Sans',
    fontSize: 13,
    color: AppColors.navLabel,
  );

  static const _totalValueStyle = TextStyle(
    fontFamily: 'Plus Jakarta Sans',
    fontSize: 15,
    fontWeight: FontWeight.w700,
    color: AppColors.primary,
  );
}
