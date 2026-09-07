import 'package:carlton/components/room_details_content.dart';
import 'package:carlton/controllers/booking/booking_flow_controller.dart';
import 'package:carlton/customWidgets/custom_filled_button.dart';
import 'package:carlton/models/booking_models.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';

/// Room detail bottom sheet (Figma "One Room Page") shown from the Choose Room
/// step. Sheet chrome — background, top radius, height cap, bottom safe-area
/// inset — comes from `CustomBottomSheet`; see
/// `BookingFlowController.openRoomDetails`. "Select This Room" continues the
/// in-progress booking.
class RoomDetailsSheet extends StatelessWidget {
  final RoomOption room;

  const RoomDetailsSheet({required this.room, super.key});

  @override
  Widget build(BuildContext context) {
    // Obx: the carousel index lives on the controller, so paging a photo has
    // to rebuild this body.
    final controller = Get.find<BookingFlowController>();

    return Obx(
      () => RoomDetailsContent(
        room: room,
        nights: controller.nights,
        imageIndex: controller.roomImageIndex.value,
        onImageChanged: controller.setRoomImage,
        actions: Column(
          spacing: 10,
          children: [
            CustomFilledButton(
              width: double.infinity,
              backgroundColor: AppColors.lagoonTeal,
              onPressed: () {
                Get.back();
                controller.selectRoom(room);
              },
              child: const Text('Select This Room'),
            ),
            CustomFilledButton(
              width: double.infinity,
              backgroundColor: AppColors.whisperGrey,
              foregroundColor: AppColors.inkBlack,
              onPressed: () => Get.back(),
              child: const Text('Back to Rooms'),
            ),
          ],
        ),
      ),
    );
  }
}
