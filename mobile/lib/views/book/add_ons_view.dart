import 'package:carlton/customWidgets/custom_containers.dart';
import 'package:carlton/components/cards/custom_add_on_summary_tile.dart';
import 'package:carlton/controllers/booking/booking_flow_controller.dart';
import 'package:carlton/customWidgets/custom_filled_button.dart';
import 'package:carlton/customWidgets/custom_scaffold.dart';
import 'package:carlton/components/custom_price_summary.dart';
import 'package:carlton/customWidgets/custom_selectable_card.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:flutter_svg/flutter_svg.dart';
import 'package:get/get.dart';
import 'package:smooth_page_indicator/smooth_page_indicator.dart';

/// Step 3 — optional extras (Figma "Booking / Step 3"). The CTA label is
/// dynamic: "Skip — No Extras" ↔ "Continue with N extras".
class AddOnsView extends StatelessWidget {
  const AddOnsView({super.key});

  @override
  Widget build(BuildContext context) {
    return CustomScaffold(
      appBar: AppBar(
        title: Text('Add-Ons'),
        iconTheme: IconThemeData(color: Colors.black),
        actions: [
          Container(
            decoration: BoxDecoration(
              shape: BoxShape.circle,
              color: AppColors.whisperGrey,
            ),
            child: IconButton(
              onPressed: () {},
              icon: const Icon(Icons.close, color: AppColors.inkBlack),
            ),
          ),
        ],
      ),
      body: GetBuilder<BookingFlowController>(
        builder: (c) {
          final TextTheme textStyle = Get.textTheme;
          final room = c.selectedRoom;
          return Padding(
            padding: const EdgeInsets.all(10),
            child: Column(
              spacing: 10,
              children: [
                AnimatedSmoothIndicator(
                  activeIndex: 2,
                  count: 6,
                  effect: SlideEffect(
                    dotHeight: 5,
                    dotWidth: 50,
                    spacing: 20,
                    activeDotColor: AppColors.primary,
                    dotColor: AppColors.iceBlue,
                  ),
                ),
                // CustomScrollView needs bounded height inside the Column, so
                // it stays wrapped in Expanded.
                Expanded(
                  child: CustomScrollView(
                    slivers: [
                      SliverPadding(
                        padding: const EdgeInsets.all(10),
                        sliver: SliverToBoxAdapter(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            spacing: 10,
                            children: [
                              if (room != null)
                                CustomAddOnSummaryTile(
                                  imagePath: room.images.first,
                                  roomName: room.name,
                                  detail: c.roomDetailSummary,
                                ),
                              Text(
                                'Enhance Your Stay',
                                style: textStyle.labelLarge?.copyWith(
                                  fontWeight: FontWeight.w600,
                                  color: AppColors.inkBlack,
                                ),
                              ),
                            ],
                          ),
                        ),
                      ),
                      SliverPadding(
                        padding: const EdgeInsets.all(10),
                        sliver: SliverList.builder(
                          itemCount: c.addOns.length,
                          itemBuilder: (_, i) {
                            final a = c.addOns[i];
                            return Padding(
                              padding: const EdgeInsets.all(10),
                              child: CustomSelectableCard(
                                title: a.title,
                                subtitle: a.subtitle,
                                trailingText: '+\$${a.price}',
                                selected: c.selectedAddOnIds.contains(a.id),
                                onTap: () => c.toggleAddOn(a.id),
                                //TODO: move to the custom widget
                                leading: Container(
                                  width: 40,
                                  height: 40,
                                  alignment: Alignment.center,
                                  decoration: BoxDecoration(
                                    color: AppColors.pearlCream,
                                    borderRadius: BorderRadius.circular(10),
                                  ),
                                  child: SvgPicture.asset(
                                    a.iconPath,
                                    width: 16,
                                    height: 16,
                                    colorFilter: const ColorFilter.mode(
                                      AppColors.primary,
                                      BlendMode.srcIn,
                                    ),
                                  ),
                                ),
                              ),
                            );
                          },
                        ),
                      ),
                    ],
                  ),
                ),
                //TODO:
                if (true) ...[
                  PillContainer(
                    // The row no longer pads itself — fold what it used to add
                    // into the pill.
                    padding: const EdgeInsets.symmetric(
                      horizontal: 24,
                      vertical: 10,
                    ),
                    backgroundColor: AppColors.cream,
                    child: CustomPriceSummaryRow(
                      title: 'Extras total',
                      value: '+\$${30}',
                      titleStyle: textStyle.labelMedium?.copyWith(
                        fontFamily: 'DM Sans',
                        color: AppColors.inkBlack,
                      ),
                      valueStyle: textStyle.labelMedium?.copyWith(
                        fontFamily: 'Plus Jakarta Sans',
                        fontWeight: FontWeight.w600,
                        color: AppColors.walnutGold,
                      ),
                    ),
                  ),
                  CustomFilledButton(
                    width: double.infinity,
                    backgroundColor: AppColors.lagoonTeal,
                    onPressed: c.continueFromAddOns,
                    child: Text(c.addOnsCtaLabel),
                  ),
                ],
              ],
            ),
          );
        },
      ),
    );
  }
}
