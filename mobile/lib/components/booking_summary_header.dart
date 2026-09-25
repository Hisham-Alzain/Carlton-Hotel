import 'package:carlton/customWidgets/custom_containers.dart';
import 'package:carlton/customWidgets/custom_image.dart';
import 'package:carlton/customWidgets/custom_texts.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';

/// Room hero + total card shared by the Payment and Review Booking screens
/// (Figma "Booking / Step 12"). On Payment a [priceBreakdown] is supplied: the
/// total row toggles in place to the full breakdown ("View price details"). On
/// Review it is null — the hero stands alone above a separate always-visible
/// breakdown, so the whole price section is omitted.
class BookingSummaryHeader extends StatefulWidget {
  final String roomName;

  /// Hero photo behind the tinted overlay — a `CustomImage` source, so an asset
  /// path today and a storage URL once rooms come from the API.
  final String roomImage;
  final String dateRange;
  final int nights;
  final String totalDisplay;

  /// Revealed in place of the total row by "View price details". Null hides the
  /// price section entirely.
  final Widget? priceBreakdown;

  const BookingSummaryHeader({
    required this.roomName,
    required this.roomImage,
    required this.dateRange,
    required this.nights,
    required this.totalDisplay,
    this.priceBreakdown,
    super.key,
  });

  @override
  State<BookingSummaryHeader> createState() => _BookingSummaryHeaderState();
}

class _BookingSummaryHeaderState extends State<BookingSummaryHeader> {
  bool _expanded = false;

  @override
  Widget build(BuildContext context) {
    final TextTheme textStyle = Get.textTheme;

    return Container(
      clipBehavior: Clip.antiAlias,
      decoration: BoxDecoration(
        color: AppColors.white,
        borderRadius: BorderRadius.circular(12),
        boxShadow: const [
          BoxShadow(
            color: AppColors.black06,
            blurRadius: 12,
            offset: Offset(0, 2),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          _hero(textStyle),
          if (widget.priceBreakdown != null) _priceSection(textStyle),
        ],
      ),
    );
  }

  Widget _hero(TextTheme textStyle) {
    return SizedBox(
      height: 100,
      child: Stack(
        fit: StackFit.expand,
        children: [
          CustomImage(source: widget.roomImage, fit: BoxFit.cover),
          DecoratedBox(
            decoration: BoxDecoration(
              color: AppColors.slateTeal.withValues(alpha: 0.8),
            ),
          ),
          Padding(
            padding: const EdgeInsets.all(10),
            child: Align(
              alignment: Alignment.bottomLeft,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                mainAxisSize: MainAxisSize.min,
                children: [
                  Text(
                    widget.roomName,
                    style: textStyle.titleMedium?.copyWith(
                      fontWeight: FontWeight.w600,
                      color: AppColors.white,
                    ),
                  ),
                  Text(
                    '${widget.dateRange} · ${widget.nights} nights',
                    style: textStyle.labelMedium?.copyWith(
                      fontFamily: 'DM Sans',
                      color: AppColors.white73,
                    ),
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _priceSection(TextTheme textStyle) {
    return AnimatedSize(
      duration: const Duration(milliseconds: 200),
      curve: Curves.easeOut,
      alignment: Alignment.topCenter,
      child: _expanded
          ? Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                widget.priceBreakdown!,
                Padding(
                  padding: const EdgeInsets.fromLTRB(10, 0, 10, 10),
                  child: Align(
                    alignment: Alignment.centerRight,
                    child: _toggle(textStyle),
                  ),
                ),
              ],
            )
          : Padding(
              padding: const EdgeInsets.all(10),
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Text(
                          'Total',
                          style: textStyle.labelLarge?.copyWith(
                            fontWeight: FontWeight.w700,
                            color: AppColors.inkBlack,
                          ),
                        ),
                        Text(
                          'Includes taxes and service fees',
                          style: textStyle.labelSmall?.copyWith(
                            fontFamily: 'DM Sans',
                            color: AppColors.primary,
                          ),
                        ),
                      ],
                    ),
                  ),
                  Column(
                    crossAxisAlignment: CrossAxisAlignment.end,
                    mainAxisSize: MainAxisSize.min,
                    spacing: 6,
                    children: [
                      Text(
                        widget.totalDisplay,
                        style: textStyle.titleLarge?.copyWith(
                          fontWeight: FontWeight.w700,
                          color: AppColors.primary,
                        ),
                      ),
                      _toggle(textStyle),
                    ],
                  ),
                ],
              ),
            ),
    );
  }

  Widget _toggle(TextTheme textStyle) {
    return GestureDetector(
      onTap: () => setState(() => _expanded = !_expanded),
      child: PillContainer(
        backgroundColor: AppColors.linenGrey,
        radius: 4,
        child: RowTextComponent(
          text: _expanded ? 'Hide price details' : 'View price details',
          textStyle: textStyle.labelSmall?.copyWith(
            fontFamily: 'DM Sans',
            color: AppColors.primary,
          ),
          icon: _expanded ? Icons.arrow_drop_up : Icons.arrow_drop_down,
          iconColor: AppColors.primary,
          spacing: 10,
        ),
      ),
    );
  }
}
