import 'package:carlton/controllers/booking/booking_flow_controller.dart';
import 'package:carlton/customWidgets/custom_containers.dart';
import 'package:carlton/customWidgets/custom_image.dart';
import 'package:carlton/customWidgets/custom_texts.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';

/// Room hero + total-so-far card shared by the Payment and Review Booking
/// screens (Figma "Booking / Step 12", node 2146:16780).
class BookingSummaryHeader extends StatelessWidget {
  final BookingFlowController controller;

  const BookingSummaryHeader({required this.controller, super.key});

  @override
  Widget build(BuildContext context) {
    final TextTheme textStyle = Get.textTheme;
    final c = controller;
    final room = c.selectedRoom!;

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
        spacing: 10,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          SizedBox(
            height: 100,
            child: Stack(
              fit: StackFit.expand,
              children: [
                CustomImage(path: room.images.first, fit: BoxFit.cover),
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
                          room.name,
                          style: textStyle.titleMedium?.copyWith(
                            fontWeight: FontWeight.w600,
                            color: AppColors.white,
                          ),
                        ),
                        Text(
                          '${c.dateRange} · ${c.nights} nights',
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
          ),
          Padding(
            padding: const EdgeInsets.all(10),
            child: Row(
              spacing: 10,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Expanded(
                  child: Column(
                    spacing: 10,
                    crossAxisAlignment: CrossAxisAlignment.start,
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Text(
                        'Total',
                        style: textStyle.labelLarge?.copyWith(
                          fontFamily: 'Plus Jakarta Sans',
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
                  spacing: 10,
                  crossAxisAlignment: CrossAxisAlignment.end,
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Text(
                      '\$${c.grandTotal}',
                      style: textStyle.titleLarge?.copyWith(
                        fontFamily: 'Plus Jakarta Sans',
                        fontWeight: FontWeight.w700,
                        color: AppColors.primary,
                      ),
                    ),
                    PillContainer(
                      backgroundColor: AppColors.linenGrey,
                      radius: 4,
                      child: RowTextComponent(
                        text: 'View price details',
                        textStyle: textStyle.labelSmall?.copyWith(
                          fontFamily: 'DM Sans',
                          color: AppColors.primary,
                        ),
                        icon: Icons.arrow_drop_down,
                        iconColor: AppColors.primary,
                        spacing: 10,
                      ),
                    ),
                  ],
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
