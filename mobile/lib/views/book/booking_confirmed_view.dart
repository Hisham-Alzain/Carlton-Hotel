import 'package:carlton/controllers/booking/booking_flow_controller.dart';
import 'package:carlton/customWidgets/custom_filled_button.dart';
import 'package:carlton/customWidgets/custom_image.dart';
import 'package:carlton/customWidgets/custom_snackbar.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_svg/flutter_svg.dart';
import 'package:get/get.dart';

/// Final booking-flow screen (Figma "Booking / Step 17"): a room-photo hero
/// fading into the page, the success badge, the reservation code, and the
/// return-to-stays action.
class BookingConfirmedView extends StatelessWidget {
  const BookingConfirmedView({super.key});

  void _copyCode(String code) {
    Clipboard.setData(ClipboardData(text: code));
    CustomSnackbars.showSuccess(message: 'Code copied');
  }

  @override
  Widget build(BuildContext context) {
    final TextTheme textStyle = Get.textTheme;
    final c = Get.find<BookingFlowController>();
    final code = c.confirmationCode ?? '';
    final email = c.emailCtrl.text.trim();
    final room = c.selectedRoom;

    return Scaffold(
      backgroundColor: AppColors.white,
      body: SafeArea(
        child: Column(
          children: [
            _header(),
            Expanded(
              child: Stack(
                children: [
                  if (room != null && room.images.isNotEmpty)
                    Positioned(
                      top: 0,
                      left: 0,
                      right: 0,
                      height: 240,
                      child: Stack(
                        fit: StackFit.expand,
                        children: [
                          CustomImage(
                            path: room.images.first,
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
                  SingleChildScrollView(
                    padding: const EdgeInsets.fromLTRB(28, 150, 28, 24),
                    child: Column(
                      spacing: 28,
                      children: [
                        Column(
                          mainAxisSize: MainAxisSize.min,
                          spacing: 24,
                          children: [
                            _badge(),
                            Column(
                              mainAxisSize: MainAxisSize.min,
                              spacing: 4,
                              children: [
                                Text(
                                  'Booking Confirmed!',
                                  style: textStyle.headlineSmall?.copyWith(
                                    fontFamily: 'Plus Jakarta Sans',
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
                                  c.dateRange,
                                  style: textStyle.labelMedium?.copyWith(
                                    fontFamily: 'DM Sans',
                                    color: AppColors.slateGrey,
                                  ),
                                ),
                              ],
                            ),
                          ],
                        ),
                        Column(
                          mainAxisSize: MainAxisSize.min,
                          spacing: 10,
                          children: [_codeBox(code), _copyButton(code)],
                        ),
                        Column(
                          mainAxisSize: MainAxisSize.min,
                          spacing: 22,
                          children: [
                            _sentNote(email),
                            CustomFilledButton(
                              width: double.infinity,
                              height: 52,
                              backgroundColor: AppColors.lagoonTeal,
                              onPressed: c.viewMyStays,
                              child: const Text('View My Stays'),
                            ),
                          ],
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _header() {
    final TextTheme textStyle = Get.textTheme;
    return Container(
      height: 61,
      padding: const EdgeInsets.symmetric(horizontal: 20),
      decoration: const BoxDecoration(
        color: AppColors.white,
        border: Border(
          bottom: BorderSide(color: AppColors.black05, width: 1.18),
        ),
      ),
      child: Row(
        children: [
          // Not a gap: a 32px counterweight to the close button, so the centred
          // title lands on the true centre. `spacing:` can't express it.
          const SizedBox(width: 32),
          Expanded(
            child: Text(
              'Booking Confirmed',
              textAlign: TextAlign.center,
              style: textStyle.titleMedium?.copyWith(
                fontFamily: 'Plus Jakarta Sans',
                fontWeight: FontWeight.w600,
                color: AppColors.inkBlack,
              ),
            ),
          ),
          InkWell(
            onTap: () => Get.until((r) => r.isFirst),
            customBorder: const CircleBorder(),
            child: Container(
              width: 32,
              height: 32,
              alignment: Alignment.center,
              decoration: const BoxDecoration(
                color: AppColors.pebbleGrey73,
                shape: BoxShape.circle,
              ),
              child: SvgPicture.asset(
                'assets/icons/close.svg',
                width: 16,
                height: 16,
                colorFilter: const ColorFilter.mode(
                  AppColors.inkBlack,
                  BlendMode.srcIn,
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _badge() => Container(
    width: 96,
    height: 96,
    alignment: Alignment.center,
    decoration: BoxDecoration(
      color: AppColors.primary90,
      shape: BoxShape.circle,
      border: Border.all(color: AppColors.mistTeal, width: 4),
    ),
    child: SvgPicture.asset(
      'assets/icons/check.svg',
      width: 44,
      height: 44,
      colorFilter: const ColorFilter.mode(AppColors.white, BlendMode.srcIn),
    ),
  );

  Widget _codeBox(String code) {
    final TextTheme textStyle = Get.textTheme;
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 16),
      decoration: BoxDecoration(
        color: AppColors.cream,
        borderRadius: BorderRadius.circular(12),
      ),
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
    );
  }

  Widget _copyButton(String code) {
    final TextTheme textStyle = Get.textTheme;
    return InkWell(
      onTap: code.isEmpty ? null : () => _copyCode(code),
      borderRadius: BorderRadius.circular(10),
      child: Container(
        width: 230,
        height: 52,
        alignment: Alignment.center,
        decoration: BoxDecoration(
          color: AppColors.linenGrey,
          borderRadius: BorderRadius.circular(10),
        ),
        child: Row(
          mainAxisAlignment: MainAxisAlignment.center,
          spacing: 4,
          children: [
            SvgPicture.asset(
              'assets/icons/copy.svg',
              width: 17,
              height: 17,
              colorFilter: const ColorFilter.mode(
                AppColors.charcoal,
                BlendMode.srcIn,
              ),
            ),
            Text(
              'Copy',
              style: textStyle.labelLarge?.copyWith(
                fontFamily: 'DM Sans',
                color: AppColors.charcoal,
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _sentNote(String email) {
    final TextTheme textStyle = Get.textTheme;
    final base = textStyle.labelMedium?.copyWith(
      fontFamily: 'DM Sans',
      color: AppColors.graphite,
    );
    if (email.isEmpty) {
      return Text(
        'A confirmation is on its way. We look forward to welcoming you.',
        textAlign: TextAlign.center,
        style: base,
      );
    }
    return Text.rich(
      TextSpan(
        style: base,
        children: [
          const TextSpan(text: 'A confirmation has been sent to '),
          TextSpan(
            text: email,
            style: const TextStyle(
              fontWeight: FontWeight.w700,
              color: AppColors.inkBlack,
            ),
          ),
          const TextSpan(text: '. We look forward to welcoming you.'),
        ],
      ),
      textAlign: TextAlign.center,
    );
  }
}
