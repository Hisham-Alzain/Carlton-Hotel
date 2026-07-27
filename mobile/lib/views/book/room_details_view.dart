import 'package:carlton/components/room_details_content.dart';
import 'package:carlton/customWidgets/custom_filled_button.dart';
import 'package:carlton/customWidgets/custom_scaffold.dart';
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
    final roomArgument = Get.arguments;
    if (roomArgument is! RoomOption) {
      WidgetsBinding.instance.addPostFrameCallback((_) => Get.back());
      return const Scaffold(backgroundColor: AppColors.white);
    }
    final room = roomArgument;
    // final controller = Get.find<BookingFlowController>();

    return CustomScaffold(
      //TODO: check what to add in title
      appBar: AppBar(iconTheme: IconThemeData(color: AppColors.inkBlack)),
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

                backgroundColor: AppColors.lagoonTeal,
                onPressed: () {},
                child: const Text('Select This Room'),
              ),
              CustomFilledButton(
                width: double.infinity,
                backgroundColor: AppColors.whisperGrey,
                foregroundColor: AppColors.inkBlack,
                onPressed: () => Get.back(),
                child: const Text('Back'),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
