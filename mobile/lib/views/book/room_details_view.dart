import 'package:carlton/components/room_details_content.dart';
import 'package:carlton/controllers/booking/booking_flow_controller.dart';
import 'package:carlton/customWidgets/custom_elevated_button.dart';
import 'package:carlton/customWidgets/custom_filled_button.dart';
import 'package:carlton/models/booking_models.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';

/// Full-screen room details (Figma "One Room Page") reached by tapping a room
/// on Home. Same layout as the booking [RoomDetailsSheet], rendered as a pushed
/// route instead of a sheet. The room arrives via `Get.arguments`.
class RoomDetailsView extends StatelessWidget {
  const RoomDetailsView({super.key});

  @override
  Widget build(BuildContext context) {
    final args = Get.arguments;
    if (args is! RoomOption) {
      WidgetsBinding.instance.addPostFrameCallback((_) => Get.back<void>());
      return const Scaffold(backgroundColor: AppColors.surface);
    }
    final room = args;
    final controller = Get.find<BookingFlowController>();

    return Scaffold(
      backgroundColor: AppColors.surface,
      body: SafeArea(
        bottom: false,
        child: RoomDetailsContent(
          room: room,
          actions: Column(
            mainAxisSize: MainAxisSize.min,
            spacing: 10,
            children: [
              CustomFilledButton(
                width: double.infinity,
                height: 52,
                backgroundColor: AppColors.teal,
                onPressed: () => controller.beginBooking(room),
                child: const Text('Select This Room'),
              ),
              CustomElevatedButton(
                width: double.infinity,
                height: 48,
                elevation: 0,
                backgroundColor: AppColors.neutralIconBg,
                foregroundColor: AppColors.navLabel,
                onPressed: () => Get.back<void>(),
                child: const Text('Back'),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
