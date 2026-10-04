import 'package:carlton/theme/theme.dart';
import 'package:carlton/components/booking_step_header.dart';
import 'package:carlton/l10n/app_translations.dart';
import 'package:carlton/customWidgets/custom_containers.dart';
import 'package:carlton/components/cards/custom_add_on_summary_tile.dart';
import 'package:carlton/controllers/booking/booking_flow_controller.dart';
import 'package:carlton/customWidgets/custom_filled_button.dart';
import 'package:carlton/customWidgets/custom_indicators.dart';
import 'package:carlton/customWidgets/custom_scaffold.dart';
import 'package:carlton/extensions/price_extension.dart';
import 'package:carlton/components/custom_price_summary.dart';
import 'package:carlton/components/custom_selectable_card.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';

/// Step 3 — optional extras (Figma "Booking / Step 3"). The CTA label is
/// dynamic: "Skip — No Extras" ↔ "Continue with N extras".
class AddOnsView extends StatelessWidget {
  const AddOnsView({super.key});

  @override
  Widget build(BuildContext context) {
    final controller = Get.find<BookingFlowController>();
    // Deferred to after this frame: loadAddOns() writes Rx state, which would
    // otherwise mutate during build. It no-ops when the catalogue is already
    // loaded or in flight, so re-entering the step costs nothing.
    WidgetsBinding.instance.addPostFrameCallback(
      (_) => controller.loadAddOns(),
    );

    return CustomScaffold(
      appBar: BookingStepAppBar(
        title: AppTranslations.addOns,
        onClose: Get.back,
      ),
      body: Obx(() {
        final TextTheme textStyle = Get.textTheme;
        final room = controller.selectedRoom.value;
        return Padding(
          padding: const EdgeInsets.all(10),
          child: Column(
            spacing: 10,
            children: [
              BookingStepIndicator(step: 2),
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
                              AppTranslations.enhanceYourStay,
                              style: textStyle.labelLarge?.copyWith(
                                fontWeight: FontWeight.w600,
                                color: AppColors.inkBlack,
                              ),
                            ),
                          ],
                        ),
                      ),
                    ),
                    if (controller.addOnsLoading.value)
                      const SliverToBoxAdapter(
                        child: Padding(
                          padding: EdgeInsets.all(20),
                          child: Center(child: LogoLoadingIndicator(size: 50)),
                        ),
                      )
                    else if (controller.addOns.isEmpty)
                      // A reachable-but-empty catalogue is a real state: the
                      // hotel may simply have no extras published. The CTA
                      // below already reads "Skip", so say nothing more.
                      const SliverToBoxAdapter(child: SizedBox.shrink())
                    else
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
                                trailingText:
                                    '+${MoneyFormat.usdString(addOn.priceUsd)}',
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
              // The extras total only exists once something is selected —
              // an empty pill reading "+$0" is noise.
              if (controller.selectedAddOnIds.isNotEmpty)
                PillContainer(
                  // The row no longer pads itself — fold what it used to add
                  // into the pill.
                  padding: const EdgeInsets.symmetric(
                    horizontal: 24,
                    vertical: 10,
                  ),
                  backgroundColor: AppColors.cream,
                  child: CustomPriceSummaryRow(
                    title: AppTranslations.extrasTotal,
                    value:
                        '+${MoneyFormat.usd(controller.selectedAddOnsTotalUsd)}',
                    titleStyle: textStyle.dmLabelMedium?.copyWith(
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
          ),
        );
      }),
    );
  }
}
