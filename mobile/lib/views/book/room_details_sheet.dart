import 'package:carlton/components/room_details_content.dart';
import 'package:carlton/controllers/booking/booking_flow_controller.dart';
import 'package:carlton/customWidgets/custom_elevated_button.dart';
import 'package:carlton/customWidgets/custom_filled_button.dart';
import 'package:carlton/models/booking_models.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';

/// Room detail bottom sheet (Figma "One Room Page") shown from the Choose Room
/// step. Wraps the shared [RoomDetailsContent] in sheet chrome; "Select This
/// Room" continues the in-progress booking.
class RoomDetailsSheet extends StatelessWidget {
  final RoomOption room;

  const RoomDetailsSheet({required this.room, super.key});

  @override
  Widget build(BuildContext context) {
    final controller = Get.find<BookingFlowController>();
    final maxHeight = MediaQuery.sizeOf(context).height * 0.92;

    return Container(
      constraints: BoxConstraints(maxHeight: maxHeight),
      decoration: const BoxDecoration(
        color: AppColors.surface,
        borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
      ),
      clipBehavior: Clip.antiAlias,
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
              onPressed: () {
                Get.back<void>();
                controller.selectRoom(room);
              },
              child: const Text('Select This Room'),
            ),
            CustomElevatedButton(
              width: double.infinity,
              height: 48,
              elevation: 0,
              backgroundColor: AppColors.neutralIconBg,
              foregroundColor: AppColors.navLabel,
              onPressed: () => Get.back<void>(),
              child: const Text('Back to Rooms'),
            ),
          ],
        ),
      ),
    );
  }
}
