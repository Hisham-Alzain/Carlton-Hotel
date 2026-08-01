import 'package:carlton/controllers/booking/booking_flow_controller.dart';
import 'package:carlton/customWidgets/custom_containers.dart';
import 'package:carlton/customWidgets/custom_filled_button.dart';
import 'package:carlton/customWidgets/custom_image.dart';
import 'package:carlton/customWidgets/custom_scaffold.dart';
import 'package:carlton/customWidgets/custom_texts.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';

/// Final booking-flow screen (Figma "Booking / Step 17"): a room-photo hero
/// fading into the page, the success badge, the reservation code, and the
/// return-to-stays action.
class BookingConfirmedView extends StatelessWidget {
  const BookingConfirmedView({super.key});

  @override
  Widget build(BuildContext context) {
    final TextTheme textStyle = Get.textTheme;
    final controller = Get.find<BookingFlowController>();
    final code = controller.confirmationCode ?? '';
    final email = controller.emailCtrl.text.trim();
    final room = controller.selectedRoom;

    final bodyStyle = textStyle.labelMedium?.copyWith(
      fontFamily: 'DM Sans',
      color: AppColors.graphite,
    );

    return CustomScaffold(
      appBar: AppBar(
        title: Text('Booking Confirmed'),
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
      body: Column(
        children: [
          Expanded(
            child: Stack(
              children: [
                if (room != null && room.images.isNotEmpty)
                  Positioned(
                    top: 0,
                    left: 0,
                    right: 0,
                    height: 250,
                    child: Stack(
                      fit: StackFit.expand,
                      children: [
                        CustomImage(
                          source: room.images.first,
                          fit: BoxFit.cover,
                        ),
                        DecoratedBox(
                          decoration: BoxDecoration(
                            gradient: LinearGradient(
                              begin: Alignment.topCenter,
                              end: Alignment.bottomCenter,
                              colors: [
                                AppColors.primary.withValues(alpha: 0.8),
                                AppColors.white,
                              ],
                            ),
                          ),
                        ),
                      ],
                    ),
                  ),
                Align(
                  alignment: Alignment.bottomCenter,
                  child: Padding(
                    padding: const EdgeInsets.all(10),
                    child: Column(
                      mainAxisSize: MainAxisSize.min,
                      spacing: 20,
                      children: [
                        Container(
                          width: 100,
                          height: 100,
                          alignment: Alignment.center,
                          decoration: BoxDecoration(
                            color: AppColors.primary90,
                            shape: BoxShape.circle,
                            border: Border.all(
                              color: AppColors.mistTeal,
                              width: 4,
                            ),
                          ),
                          child: const Icon(
                            Icons.check,
                            color: AppColors.white,
                            size: 50,
                          ),
                        ),
                        Text(
                          'Booking Confirmed!',
                          style: textStyle.headlineSmall?.copyWith(
                            fontWeight: FontWeight.w600,
                            color: AppColors.primary,
                          ),
                        ),
                        Text(
                          room?.name ?? '',
                          style: textStyle.labelLarge?.copyWith(
                            fontFamily: 'DM Sans',
                            color: AppColors.slateGrey,
                          ),
                        ),
                        Text(
                          controller.dateRange,
                          style: textStyle.labelMedium?.copyWith(
                            fontFamily: 'DM Sans',
                            color: AppColors.slateGrey,
                          ),
                        ),
                        PillContainer(
                          radius: 12,
                          backgroundColor: AppColors.cream,
                          child: Column(
                            mainAxisSize: MainAxisSize.min,
                            spacing: 4,
                            children: [
                              Text(
                                'CONFIRMATION CODE',
                                style: textStyle.labelSmall?.copyWith(
                                  fontFamily: 'DM Sans',
                                  color: AppColors.walnutGold,
                                ),
                              ),
                              Text(
                                code,
                                style: textStyle.titleLarge?.copyWith(
                                  fontFamily: 'Plus Jakarta Sans',
                                  fontWeight: FontWeight.w700,
                                  color: AppColors.primary,
                                ),
                              ),
                            ],
                          ),
                        ),
                        PillContainer(
                          radius: 10,
                          backgroundColor: AppColors.pearlSilver,
                          child: IconButton(
                            onPressed: controller.copyConfirmationCode,
                            icon: RowTextComponent(
                              text: 'Copy',
                              icon: Icons.copy,
                            ),
                          ),
                        ),
                        Text.rich(
                          TextSpan(
                            style: bodyStyle,
                            children: [
                              const TextSpan(
                                text: 'A confirmation has been sent to ',
                              ),
                              TextSpan(
                                text: email,
                                style: const TextStyle(
                                  fontWeight: FontWeight.w700,
                                  color: AppColors.inkBlack,
                                ),
                              ),
                              const TextSpan(
                                text: '. We look forward to welcoming you.',
                              ),
                            ],
                          ),
                          textAlign: TextAlign.center,
                        ),
                        CustomFilledButton(
                          width: double.infinity,
                          height: 50,
                          backgroundColor: AppColors.lagoonTeal,
                          onPressed: controller.viewMyStays,
                          child: const Text('View My Stays'),
                        ),
                      ],
                    ),
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
