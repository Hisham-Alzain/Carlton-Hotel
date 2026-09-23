import 'package:carlton/customWidgets/custom_containers.dart';
import 'package:carlton/components/cards/custom_add_on_summary_tile.dart';
import 'package:carlton/controllers/booking/booking_flow_controller.dart';
import 'package:carlton/customWidgets/custom_filled_button.dart';
import 'package:carlton/customWidgets/custom_scaffold.dart';
import 'package:carlton/components/custom_price_summary.dart';
import 'package:carlton/components/custom_selectable_card.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:smooth_page_indicator/smooth_page_indicator.dart';

/// Step 3 — optional extras (Figma "Booking / Step 3"). The CTA label is
/// dynamic: "Skip — No Extras" ↔ "Continue with N extras".
class AddOnsView extends StatelessWidget {
  const AddOnsView({super.key});

  @override
  Widget build(BuildContext context) {
    final controller = Get.find<BookingFlowController>();

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
      body: Obx(
        () {
          final TextTheme textStyle = Get.textTheme;
          final room = controller.selectedRoom.value;
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
                                  subtitle: controller.roomDetailSummary,
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
                          itemCount: controller.addOns.length,
                          itemBuilder: (_, index) {
                            final addOn = controller.addOns[index];
                            return Padding(
                              padding: const EdgeInsets.all(10),
                              child: CustomSelectableCard(
                                title: addOn.title,
                                subtitle: addOn.subtitle,
                                trailingText: '+\$${addOn.price}',
                                selected: controller.selectedAddOnIds.contains(
                                  addOn.id,
                                ),
                                onTap: () => controller.toggleAddOn(addOn.id),
                                iconPath: addOn.iconPath,
                              ),
                            );
                          },
                        ),
                      ),
                    ],
                  ),
                ),
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
                    onPressed: controller.continueFromAddOns,
                    child: Text(controller.addOnsCtaLabel),
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
