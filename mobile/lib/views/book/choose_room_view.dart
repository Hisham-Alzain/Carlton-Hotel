import 'package:carlton/theme/theme.dart';
import 'package:carlton/components/booking_step_header.dart';
import 'package:carlton/l10n/app_translations.dart';
import 'package:carlton/customWidgets/custom_containers.dart';
import 'package:carlton/components/cards/custom_room_result_card.dart';
import 'package:carlton/controllers/booking/booking_flow_controller.dart';
import 'package:carlton/customWidgets/custom_scaffold.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:carlton/customWidgets/custom_indicators.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';

/// Step 2 — browse and pick a room (Figma "Booking / Step 2").
class ChooseRoomView extends StatelessWidget {
  const ChooseRoomView({super.key});

  @override
  Widget build(BuildContext context) {
    final controller = Get.find<BookingFlowController>();

    return CustomScaffold(
      appBar: BookingStepAppBar(
        title: AppTranslations.chooseYourRoom,
        onClose: Get.back,
      ),
      body: Obx(() {
        final TextTheme textStyle = Get.textTheme;
        return CustomScrollView(
          // The pagination mixin's controller: nearing the bottom loads the
          // next page of room types.
          controller: controller.scrollController,
          slivers: [
            SliverPadding(
              padding: const EdgeInsetsGeometry.all(10),
              sliver: SliverToBoxAdapter(
                child: Column(
                  spacing: 10,
                  children: [
                    BookingStepIndicator(step: 1),
                    PillContainer(
                      padding: const EdgeInsets.all(10),
                      backgroundColor: AppColors.pearlCream,
                      child: Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          Flexible(
                            child: Text(
                              controller.dateSummary,
                              style: textStyle.dmLabelMedium?.copyWith(
                                color: AppColors.inkBlack,
                              ),
                            ),
                          ),
                          Text(
                            controller.guestSummary,
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
            if (controller.roomsLoading.value)
              const SliverToBoxAdapter(
                child: Padding(
                  padding: EdgeInsets.only(top: 60),
                  child: Center(child: LogoLoadingIndicator(size: 50)),
                ),
              )
            else
              SliverPadding(
                padding: const EdgeInsets.all(10),
                sliver: SliverList.builder(
                  itemCount: controller.rooms.length,
                  itemBuilder: (_, index) {
                    final room = controller.rooms[index];
                    return CustomRoomResultCard(
                      room: room,
                      nights: controller.nights,
                      roomsAvailable: controller.availableFor(room),
                      onSelect: () => controller.selectRoom(room),
                      onTap: () => controller.openRoomDetails(room),
                    );
                  },
                ),
              ),
            if (controller.loadingMore.value)
              const SliverToBoxAdapter(
                child: Padding(
                  padding: EdgeInsets.all(20),
                  child: Center(child: SpinningIconIndicator(size: 28)),
                ),
              ),
          ],
        );
      }),
    );
  }
}
