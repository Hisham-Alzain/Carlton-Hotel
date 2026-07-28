import 'package:carlton/customWidgets/custom_containers.dart';
import 'package:carlton/controllers/booking/booking_flow_controller.dart';
import 'package:carlton/customWidgets/custom_image_carousel.dart';
import 'package:carlton/components/custom_info_banner.dart';
import 'package:carlton/components/custom_price_summary.dart';
import 'package:carlton/customWidgets/custom_rating_component.dart';
import 'package:carlton/customWidgets/custom_texts.dart';
import 'package:carlton/models/booking_models.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:flutter_svg/flutter_svg.dart';
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

  /// Single fixed height for every Highlights/Amenities tile so the grid stays
  /// uniform. Tune this one value if a two-line label ever clips.
  static const double _tileHeight = 60;

  @override
  Widget build(BuildContext context) {
    final TextTheme textStyle = Get.textTheme;
    final controller = Get.find<BookingFlowController>();
    final stayTotal = room.pricePerNight * controller.nights;

    return SingleChildScrollView(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          GetBuilder<BookingFlowController>(
            builder: (carouselController) => CustomImageCarousel(
              images: room.images,
              index: carouselController.roomImageIndex,
              onIndexChanged: carouselController.setRoomImage,
              height: 220,
              // topRight: CustomCircleIconButton(
              //   iconPath: 'assets/icons/close.svg',
              //   size: 32,
              //   color: AppColors.pebbleGrey73,
              //   bordered: false,
              //   shadow: false,
              //   iconSize: 16,
              //   iconPadding: 8,
              //   onTap: () => Get.back(),
              // ),
            ),
          ),
          Padding(
            padding: const EdgeInsets.all(10),
            child: Column(
              spacing: 10,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  room.name,
                  style: textStyle.titleLarge?.copyWith(
                    fontWeight: FontWeight.w700,
                    color: AppColors.inkBlack,
                  ),
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
                CustomRatingComponent(
                  rating: room.rating,
                  reviewCount: room.reviewCount,
                ),
                Text(
                  room.description,
                  style: textStyle.labelMedium?.copyWith(
                    fontFamily: 'DM Sans',
                    color: AppColors.dimGrey,
                  ),
                ),
                Text('Highlights', style: _headingStyle(textStyle)),
                _pairedGrid(room.highlights, _highlightTile, runSpacing: 10),
                Text('All Amenities', style: _headingStyle(textStyle)),
                _pairedGrid(room.amenities, _amenityTile, runSpacing: 10),
                const CustomInfoBanner(
                  message: 'Free cancellation until 48 hours before check-in.',
                ),
                PillContainer(
                  // The row no longer pads itself, so absorb the 10 it used to
                  // add on top of the pill's own 10.
                  padding: const EdgeInsets.all(20),
                  backgroundColor: AppColors.cream,
                  child: CustomPriceSummaryRow(
                    title:
                        'Total for ${controller.nights} night${controller.nights == 1 ? '' : 's'}',
                    value: '\$$stayTotal',
                    titleStyle: textStyle.labelMedium?.copyWith(
                      fontFamily: 'DM Sans',
                      color: AppColors.inkBlack,
                    ),
                    valueStyle: textStyle.labelLarge?.copyWith(
                      fontWeight: FontWeight.w700,
                      color: AppColors.primary,
                    ),
                  ),
                ),
                actions,
              ],
            ),
          ),
        ],
      ),
    );
  }

  TextStyle? _headingStyle(TextTheme textStyle) => textStyle.labelLarge
      ?.copyWith(fontWeight: FontWeight.w600, color: AppColors.inkBlack);

  TextStyle? _tileTextStyle(TextTheme textStyle) => textStyle.labelMedium
      ?.copyWith(fontFamily: 'DM Sans', color: AppColors.inkBlack);

  Widget _pairedGrid<T>(
    List<T> items,
    Widget Function(T) tileBuilder, {
    required double runSpacing,
  }) => Column(
    spacing: 10,
    children: List.generate((items.length + 1) ~/ 2, (row) {
      final leftIndex = row * 2;
      return Padding(
        padding: EdgeInsets.only(bottom: runSpacing),
        child: Row(
          spacing: 10,
          children: [
            Expanded(child: tileBuilder(items[leftIndex])),
            Expanded(
              child: leftIndex + 1 < items.length
                  ? tileBuilder(items[leftIndex + 1])
                  : const SizedBox.shrink(),
            ),
          ],
        ),
      );
    }),
  );

  Widget _highlightTile(IconLabel highlight) {
    final TextTheme textStyle = Get.textTheme;
    return PillContainer(
      height: _tileHeight,
      padding: const EdgeInsets.all(10),
      radius: 10,
      backgroundColor: AppColors.pearlCream65,
      border: Border.all(color: Colors.white),
      child: RowTextComponent(
        leading: _iconBadge(highlight.iconPath),
        text: highlight.label,
        textStyle: _tileTextStyle(textStyle),
        spacing: 10,
        expandText: true,
      ),
    );
  }

  /// The icon wrapped in a light rounded-square badge, shared by both the
  /// Highlights and Amenities tiles. Reuses [PillContainer] for the box.
  Widget _iconBadge(String iconPath) => PillContainer(
    padding: const EdgeInsetsGeometry.all(5),
    radius: 8,
    backgroundColor: AppColors.cream,
    child: SvgPicture.asset(
      iconPath,
      height: 15,
      width: 15,
      colorFilter: const ColorFilter.mode(
        AppColors.antiqueGold,
        BlendMode.srcIn,
      ),
    ),
  );

  Widget _circleIconBadge(String iconPath) => Container(
    padding: const EdgeInsetsGeometry.all(5),
    decoration: BoxDecoration(shape: BoxShape.circle, color: AppColors.cream),
    child: SvgPicture.asset(
      iconPath,
      height: 15,
      width: 15,
      colorFilter: const ColorFilter.mode(
        AppColors.antiqueGold,
        BlendMode.srcIn,
      ),
    ),
  );

//check spacing between tiles
  Widget _amenityTile(IconLabel amenity) {
    final TextTheme textStyle = Get.textTheme;
    return SizedBox(
      height: _tileHeight,
      child: RowTextComponent(
        leading: _circleIconBadge(amenity.iconPath),
        text: amenity.label,
        textStyle: _tileTextStyle(textStyle),
        spacing: 10,
        expandText: true,
      ),
    );
  }

  Widget _meta(String iconPath, String text) {
    final TextTheme textStyle = Get.textTheme;
    return RowTextComponent(
      iconPath: iconPath,
      iconColor: AppColors.graphite,
      text: text,
      textStyle: textStyle.labelMedium?.copyWith(
        fontWeight: FontWeight.w300,
        color: AppColors.graphite,
      ),
      spacing: 10,
    );
  }
}
