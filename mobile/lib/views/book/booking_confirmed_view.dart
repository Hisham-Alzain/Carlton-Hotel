import 'package:carlton/l10n/app_translations.dart';
import 'package:carlton/controllers/booking/booking_flow_controller.dart';
import 'package:carlton/customWidgets/custom_containers.dart';
import 'package:carlton/customWidgets/custom_filled_button.dart';
import 'package:carlton/customWidgets/custom_image.dart';
import 'package:carlton/customWidgets/custom_scaffold.dart';
import 'package:carlton/customWidgets/custom_texts.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:flutter_svg/flutter_svg.dart';
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
    final code = controller.confirmationCode.value ?? '';
    final email = controller.emailCtrl.text.trim();
    final room = controller.selectedRoom.value;

    final bodyStyle = textStyle.labelMedium?.copyWith(
      fontFamily: 'DM Sans',
      color: AppColors.graphite,
    );

    return CustomScaffold(
      appBar: AppBar(
        title: Text(AppTranslations.bookingConfirmed),
        actions: [
          Container(
            decoration: BoxDecoration(
              shape: BoxShape.circle,
              color: AppColors.whisperGrey,
            ),
            child: IconButton(
              onPressed: () {},
              icon: SvgPicture.asset(
                'assets/icons/close.svg',
                width: 24,
                height: 24,
                colorFilter: const ColorFilter.mode(
                  AppColors.inkBlack,
                  BlendMode.srcIn,
                ),
              ),
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
                          child: SvgPicture.asset(
                            'assets/icons/check.svg',
                            width: 50,
                            height: 50,
                            colorFilter: const ColorFilter.mode(
                              AppColors.white,
                              BlendMode.srcIn,
                            ),
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
                                AppTranslations.confirmationCode,
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
                              text: AppTranslations.copy,
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
                          child: Text(AppTranslations.viewMyStays),
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
