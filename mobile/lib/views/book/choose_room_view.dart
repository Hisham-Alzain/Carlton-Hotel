import 'package:carlton/customWidgets/custom_containers.dart';
import 'package:carlton/components/cards/custom_room_result_card.dart';
import 'package:carlton/components/custom_booking_app_bar.dart';
import 'package:carlton/controllers/booking/booking_flow_controller.dart';
import 'package:carlton/customWidgets/custom_scaffold.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';

/// Step 2 — browse and pick a room (Figma "Booking / Step 2").
class ChooseRoomView extends StatelessWidget {
  const ChooseRoomView({super.key});

  @override
  Widget build(BuildContext context) {
    return CustomScaffold(
      appBar: const CustomBookingAppBar(
        title: 'Choose Your Room',
        currentStep: 2,
      ),
      body: GetBuilder<BookingFlowController>(
        builder: (c) => ListView(
          padding: const EdgeInsets.all(20),
          children: [
            PillContainer(
              padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
              backgroundColor: AppColors.pearlCream,
              child: Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Flexible(
                    child: Text(
                      c.dateSummary,
                      style: const TextStyle(
                        fontFamily: 'DM Sans',
                        fontSize: 12,
                        color: AppColors.inkBlack,
                      ),
                    ),
                  ),
                  Text(
                    c.guestSummary,
                    style: const TextStyle(
                      fontFamily: 'Plus Jakarta Sans',
                      fontSize: 12,
                      color: AppColors.walnutGold,
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 16),
            for (final room in c.rooms) ...[
              CustomRoomResultCard(
                room: room,
                nights: c.nights,
                onSelect: () => c.selectRoom(room),
                onTap: () => c.openRoomDetails(room),
              ),
              const SizedBox(height: 16),
            ],
          ],
        ),
      ),
    );
  }
}
