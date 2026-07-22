import 'package:carlton/components/cards/custom_add_on_summary_tile.dart';
import 'package:carlton/components/custom_booking_app_bar.dart';
import 'package:carlton/controllers/booking/booking_flow_controller.dart';
import 'package:carlton/customWidgets/custom_filled_button.dart';
import 'package:carlton/customWidgets/custom_scaffold.dart';
import 'package:carlton/customWidgets/custom_price_summary.dart';
import 'package:carlton/customWidgets/custom_selectable_card.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:flutter_svg/flutter_svg.dart';
import 'package:get/get.dart';

/// Step 3 — optional extras (Figma "Booking / Step 3"). The CTA label is
/// dynamic: "Skip — No Extras" ↔ "Continue with N extras".
class AddOnsView extends StatelessWidget {
  const AddOnsView({super.key});

  @override
  Widget build(BuildContext context) {
    return CustomScaffold(
      appBar: const CustomBookingAppBar(title: 'Add-Ons', currentStep: 3),
      body: GetBuilder<BookingFlowController>(
        builder: (c) {
          final room = c.selectedRoom;
          return Column(
            children: [
              Expanded(
                child: ListView(
                  padding: const EdgeInsets.all(20),
                  children: [
                    if (room != null)
                      CustomAddOnSummaryTile(
                        imagePath: room.images.first,
                        roomName: room.name,
                        detail: c.roomDetailSummary,
                      ),
                    const SizedBox(height: 16),
                    const Text(
                      'Enhance Your Stay',
                      style: TextStyle(
                        fontFamily: 'Plus Jakarta Sans',
                        fontSize: 14,
                        fontWeight: FontWeight.w600,
                        color: AppColors.navLabel,
                      ),
                    ),
                    const SizedBox(height: 12),
                    for (final a in c.addOns) ...[
                      CustomSelectableCard(
                        title: a.title,
                        subtitle: a.subtitle,
                        trailingText: '+\$${a.price}',
                        selected: c.selectedAddOnIds.contains(a.id),
                        onTap: () => c.toggleAddOn(a.id),
                        leading: _leadingIcon(a.iconPath),
                      ),
                      const SizedBox(height: 12),
                    ],
                  ],
                ),
              ),
              _Footer(controller: c),
            ],
          );
        },
      ),
    );
  }

  Widget _leadingIcon(String iconPath) => Container(
    width: 36,
    height: 36,
    alignment: Alignment.center,
    decoration: BoxDecoration(
      color: AppColors.softButtonBg,
      borderRadius: BorderRadius.circular(10),
    ),
    child: SvgPicture.asset(
      iconPath,
      width: 16,
      height: 16,
      colorFilter: const ColorFilter.mode(AppColors.primary, BlendMode.srcIn),
    ),
  );
}

class _Footer extends StatelessWidget {
  final BookingFlowController controller;
  const _Footer({required this.controller});

  @override
  Widget build(BuildContext context) {
    final hasExtras = controller.selectedAddOnIds.isNotEmpty;
    return SafeArea(
      top: false,
      child: Padding(
        padding: const EdgeInsets.fromLTRB(20, 8, 20, 12),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            if (hasExtras) ...[
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 14),
                decoration: BoxDecoration(
                  color: AppColors.cream,
                  borderRadius: BorderRadius.circular(10),
                ),
                child: CustomPriceSummaryRow(
                  label: 'Extras total',
                  value: '+\$${controller.extrasTotal}',
                  labelStyle: const TextStyle(
                    fontFamily: 'DM Sans',
                    fontSize: 13,
                    color: AppColors.navLabel,
                  ),
                  valueStyle: const TextStyle(
                    fontFamily: 'Plus Jakarta Sans',
                    fontSize: 13,
                    fontWeight: FontWeight.w600,
                    color: AppColors.creamTextDeep,
                  ),
                ),
              ),
              const SizedBox(height: 12),
            ],
            CustomFilledButton(
              width: double.infinity,
              backgroundColor: AppColors.teal,
              onPressed: controller.continueFromAddOns,
              child: Text(controller.addOnsCtaLabel),
            ),
          ],
        ),
      ),
    );
  }
}
