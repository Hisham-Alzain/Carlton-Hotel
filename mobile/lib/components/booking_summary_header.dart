import 'package:carlton/controllers/booking/booking_flow_controller.dart';
import 'package:carlton/customWidgets/custom_image.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:flutter_svg/flutter_svg.dart';

/// Room hero + total-so-far card shared by the Payment and Review Booking
/// screens (Figma "Booking / Step 12", node 2146:16780).
class BookingSummaryHeader extends StatelessWidget {
  final BookingFlowController controller;

  const BookingSummaryHeader({required this.controller, super.key});

  @override
  Widget build(BuildContext context) {
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
                    color: AppColors.slateTeal.withValues(alpha: 0.79),
                  ),
                ),
                Positioned(
                  left: 14,
                  bottom: 12,
                  right: 14,
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Text(
                        room.name,
                        style: const TextStyle(
                          fontFamily: 'Plus Jakarta Sans',
                          fontSize: 16,
                          fontWeight: FontWeight.w600,
                          color: AppColors.white,
                        ),
                      ),
                      Text(
                        '${c.dateRange} · ${c.nights} nights',
                        style: const TextStyle(
                          fontFamily: 'DM Sans',
                          fontSize: 12,
                          color: AppColors.white73,
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),
          Container(
            padding: const EdgeInsets.all(14),
            decoration: const BoxDecoration(
              border: Border(
                top: BorderSide(color: AppColors.primary, width: 1.18),
              ),
            ),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Text(
                        'Total',
                        style: TextStyle(
                          fontFamily: 'Plus Jakarta Sans',
                          fontSize: 14,
                          fontWeight: FontWeight.w700,
                          color: AppColors.inkBlack,
                        ),
                      ),
                      Text(
                        'Includes taxes and service fees',
                        style: TextStyle(
                          fontFamily: 'DM Sans',
                          fontSize: 9,
                          color: AppColors.primary,
                        ),
                      ),
                    ],
                  ),
                ),
                Column(
                  crossAxisAlignment: CrossAxisAlignment.end,
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Text(
                      '\$${c.grandTotal}',
                      style: const TextStyle(
                        fontFamily: 'Plus Jakarta Sans',
                        fontSize: 20,
                        fontWeight: FontWeight.w700,
                        color: AppColors.primary,
                      ),
                    ),
                    Container(
                      padding: const EdgeInsets.symmetric(
                        horizontal: 8,
                        vertical: 4,
                      ),
                      decoration: BoxDecoration(
                        color: AppColors.linenGrey,
                        borderRadius: BorderRadius.circular(4),
                      ),
                      child: Row(
                        mainAxisSize: MainAxisSize.min,
                        spacing: 4,
                        children: [
                          const Text(
                            'View price details',
                            style: TextStyle(
                              fontFamily: 'DM Sans',
                              fontSize: 9,
                              color: AppColors.primary,
                            ),
                          ),
                          SvgPicture.asset(
                            'assets/icons/chevron_down.svg',
                            width: 13,
                            height: 13,
                            colorFilter: const ColorFilter.mode(
                              AppColors.primary,
                              BlendMode.srcIn,
                            ),
                          ),
                        ],
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
