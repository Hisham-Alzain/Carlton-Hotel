import 'package:carlton/customWidgets/custom_containers.dart';
import 'package:carlton/components/cards/custom_room_result_card.dart';
import 'package:carlton/controllers/booking/booking_flow_controller.dart';
import 'package:carlton/customWidgets/custom_scaffold.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:smooth_page_indicator/smooth_page_indicator.dart';

/// Step 2 — browse and pick a room (Figma "Booking / Step 2").
class ChooseRoomView extends StatelessWidget {
  const ChooseRoomView({super.key});

  @override
  Widget build(BuildContext context) {
    return CustomScaffold(
      appBar: AppBar(
        title: Text('Choose Your Room'),
        iconTheme: IconThemeData(color: Colors.black),
        actions: [
          Container(
            decoration: BoxDecoration(
              shape: BoxShape.circle,
              color: AppColors.whisperGrey,
            ),
            child: IconButton(
              onPressed: () {},
              icon: const Icon(Icons.close, color: AppColors.inkBlack),
            ),
          ),
        ],
      ),
      body: GetBuilder<BookingFlowController>(
        builder: (c) {
          final TextTheme textStyle = Get.textTheme;
          return CustomScrollView(
            slivers: [
              SliverPadding(
                padding: const EdgeInsetsGeometry.all(10),
                sliver: SliverToBoxAdapter(
                  child: Column(
                    spacing: 10,
                    children: [
                      AnimatedSmoothIndicator(
                        activeIndex: 1,
                        count: 6,
                        effect: SlideEffect(
                          dotHeight: 5,
                          dotWidth: 50,
                          spacing: 20,
                          activeDotColor: AppColors.primary,
                          dotColor: AppColors.iceBlue,
                        ),
                      ),
                      PillContainer(
                        padding: const EdgeInsets.all(10),
                        backgroundColor: AppColors.pearlCream,
                        child: Row(
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          children: [
                            Flexible(
                              child: Text(
                                c.dateSummary,
                                style: textStyle.labelMedium?.copyWith(
                                  fontFamily: 'DM Sans',
                                  color: AppColors.inkBlack,
                                ),
                              ),
                            ),
                            Text(
                              c.guestSummary,
                              style: textStyle.labelMedium?.copyWith(
                                fontFamily: 'Plus Jakarta Sans',
                                color: AppColors.walnutGold,
                              ),
                            ),
                          ],
                        ),
                      ),
                    ],
                  ),
                ),
              ),
              SliverPadding(
                padding: const EdgeInsets.all(10),
                sliver: SliverList.builder(
                  itemCount: c.rooms.length,
                  itemBuilder: (_, i) {
                    final room = c.rooms[i];
                    return CustomRoomResultCard(
                      room: room,
                      nights: c.nights,
                      onSelect: () => c.selectRoom(room),
                      onTap: () => c.openRoomDetails(room),
                    );
                  },
                ),
              ),
            ],
          );
        },
      ),
    );
  }
}
