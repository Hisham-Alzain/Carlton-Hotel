import 'package:carlton/components/reviews/review_submit_sheet.dart';
import 'package:carlton/components/room_details_content.dart';
import 'package:carlton/controllers/booking/booking_flow_controller.dart';
import 'package:carlton/controllers/reviews/review_controller.dart';
import 'package:carlton/customWidgets/custom_bottom_sheet.dart';
import 'package:carlton/customWidgets/custom_filled_button.dart';
import 'package:carlton/customWidgets/custom_scaffold.dart';
import 'package:carlton/customWidgets/custom_snackbar.dart';
import 'package:carlton/models/booking_models.dart';
import 'package:carlton/routes/routes.dart';
import 'package:carlton/services/middleware_service.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';

/// Full-screen room details (Figma "One Room Page") reached by tapping a room
/// on Home. Same layout as the booking [RoomDetailsSheet], rendered as a pushed
/// route instead of a sheet. The room arrives via `Get.arguments`.
class RoomDetailsView extends StatelessWidget {
  const RoomDetailsView({super.key});

  /// Opens the "Write a Review" sheet, auth-gated: a POST while unauthenticated
  /// returns 401 and fires the global logout — the wrong outcome here.
  void _openReviewSheet(RoomOption room) {
    if (!MiddlewareService.find.isAuthenticated) {
      CustomSnackbars.showInfo(message: 'Sign in to leave a review');
      Get.toNamed(Routes.signIn);
      return;
    }
    CustomBottomSheet.show<bool>(
      title: 'Write a Review',
      subtitle: room.name,
      child: ReviewSubmitSheet(controller: Get.find<ReviewController>()),
    );
  }

  @override
  Widget build(BuildContext context) {
    final roomArgument = Get.arguments;
    if (roomArgument is! RoomOption) {
      WidgetsBinding.instance.addPostFrameCallback((_) => Get.back());
      return const Scaffold(backgroundColor: AppColors.white);
    }
    final room = roomArgument;
    final TextTheme textStyle = Get.textTheme;

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
                onPressed: () => Get.find<BookingFlowController>()
                    .beginBookingWithRoom(room),
                child: const Text('Select This Room'),
              ),
              // A demo room (no uuid) can't be reviewed — hide the CTA entirely.
              if (room.uuid.isNotEmpty)
                CustomFilledButton(
                  width: double.infinity,
                  height: 50,
                  backgroundColor: AppColors.white,
                  foregroundColor: AppColors.primary,
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(10),
                    side: const BorderSide(color: AppColors.primary),
                  ),
                  textStyle: textStyle.labelLarge?.copyWith(
                    fontWeight: FontWeight.w600,
                  ),
                  onPressed: () => _openReviewSheet(room),
                  child: const Text('Write a Review'),
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
