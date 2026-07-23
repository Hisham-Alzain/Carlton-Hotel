import 'package:carlton/controllers/booking/booking_flow_controller.dart';
import 'package:carlton/customWidgets/custom_filled_button.dart';
import 'package:carlton/routes/routes.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:flutter_svg/flutter_svg.dart';
import 'package:get/get.dart';

/// Book tab — a launcher into the 5-step booking flow.
class BookView extends StatelessWidget {
  const BookView({super.key});

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.all(24),
      child: Center(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Container(
              width: 84,
              height: 84,
              alignment: Alignment.center,
              decoration: const BoxDecoration(
                color: AppColors.antiqueGold08,
                shape: BoxShape.circle,
              ),
              child: SvgPicture.asset(
                'assets/icons/calendar.svg',
                width: 34,
                height: 34,
                colorFilter: const ColorFilter.mode(
                  AppColors.primary,
                  BlendMode.srcIn,
                ),
              ),
            ),
            const SizedBox(height: 20),
            const Text(
              'Plan Your Stay',
              style: TextStyle(
                fontFamily: 'Plus Jakarta Sans',
                fontSize: 20,
                fontWeight: FontWeight.w600,
                color: AppColors.inkBlack,
              ),
            ),
            const SizedBox(height: 8),
            const Text(
              'Choose your dates, pick a room, and book your next stay at Carlton.',
              textAlign: TextAlign.center,
              style: TextStyle(
                fontFamily: 'DM Sans',
                fontSize: 13,
                height: 1.5,
                color: AppColors.taupeBrown,
              ),
            ),
            const SizedBox(height: 24),
            CustomFilledButton(
              width: double.infinity,
              backgroundColor: AppColors.lagoonTeal,
              onPressed: () {
                Get.find<BookingFlowController>().reset();
                Get.toNamed(Routes.planStay);
              },
              child: const Text('Start Booking'),
            ),
          ],
        ),
      ),
    );
  }
}
