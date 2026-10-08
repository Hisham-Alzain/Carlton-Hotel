part of 'stays_controller.dart';

extension StaysCancelActions on StaysController {
  /// The guest quotes the booking code at the desk or on the phone.
  void copyBookingCode(Stay stay) {
    final code = stay.resCode ?? '';
    if (code.isEmpty) return;
    Clipboard.setData(ClipboardData(text: code));
    CustomSnackbars.showSuccess(message: AppTranslations.copied);
  }

  void requestCancel(Stay stay) {
    CustomBottomSheet.show<void>(
      showClose: false,
      heightFactor: 0.5,
      child: CancelReservationSheet(stay: stay),
      actions: Row(
        spacing: 10,
        children: [
          Expanded(
            child: CustomFilledButton(
              width: double.infinity,
              backgroundColor: AppColors.pearlCream,
              foregroundColor: AppColors.inkBlack,
              onPressed: () => Get.back(),
              child: Text(AppTranslations.noKeep),
            ),
          ),
          Expanded(
            child: CustomFilledButton(
              width: double.infinity,
              backgroundColor: AppColors.brickRed,
              onPressed: () {
                Get.back();
                _cancelReservation(stay);
              },
              child: Text(AppTranslations.yesCancel),
            ),
          ),
        ],
      ),
    );
  }

  Future<void> _cancelReservation(Stay stay) async {
    final res = await _api.delete<dynamic>(
      path: '/reservations/${stay.uuid}',
      showErrorDialog: false,
      cancelToken: _cancel,
    );
    if (isClosed || res.isCancelled) return;
    if (res.ok || res.isNoContent) {
      // RxList.removeWhere notifies on its own — no update() needed.
      upcoming.removeWhere((s) => s.uuid == stay.uuid);
      // Cancelling may drop the guest's has_booking entitlement — resync from
      // the authoritative /me rather than guessing a flag flip.
      await MiddlewareService.find.checkToken();
      if (isClosed) return;
      // Home may be showing this booking (pending card / pre-arrival hero).
      if (Get.isRegistered<HomeController>()) {
        Get.find<HomeController>().reloadBooking();
      }
      // A cancel refunds spent points, restores a used voucher and claws back
      // earned points, so an open Loyalty screen is now out of date.
      if (Get.isRegistered<LoyaltyController>()) {
        Get.find<LoyaltyController>().reloadAll();
      }
      CustomSnackbars.showSuccess(message: await _cancelledMessage(stay.uuid));
    } else {
      final code = res.error?.errorCode;
      if (code == ErrorCodes.reservationState) {
        CustomSnackbars.showWarning(
          message: StaysController.cancelErrorMessage(code),
        );
      } else {
        CustomSnackbars.showError(
          message: StaysController.cancelErrorMessage(code),
        );
      }
    }
  }

  /// Re-reads the cancelled booking (`GET /reservations/{uuid}`) so the
  /// message can say what came back: the server reverses the rewards in the
  /// same request, and its `loyalty.status` turns `reversed`. Falls back to
  /// the plain message when nothing was used or the read fails.
  Future<String> _cancelledMessage(String uuid) async {
    final res = await _api.get<Map<String, dynamic>>(
      path: '/reservations/$uuid',
      showErrorDialog: false,
      cancelToken: _cancel,
    );
    if (!res.hasData) return AppTranslations.reservationCancelled;
    return StaysController.cancelledMessage(
      Reservation.fromJson(res.data!).loyalty,
    );
  }
}
