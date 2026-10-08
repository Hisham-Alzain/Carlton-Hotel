import 'package:carlton/l10n/app_translations.dart';
import 'package:carlton/components/sheets/sign_out_sheet.dart';
import 'package:carlton/customWidgets/custom_snackbar.dart';
import 'package:carlton/constants/error_codes.dart';
import 'package:carlton/customWidgets/custom_dialogs.dart';
import 'package:carlton/enums/enums.dart';
import 'package:carlton/extensions/points_extension.dart';
import 'package:carlton/models/guest.dart';
import 'package:carlton/models/loyalty.dart';
import 'package:carlton/routes/routes.dart';
import 'package:carlton/services/api/api_service.dart';
import 'package:carlton/services/middleware_service.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';

class AccountController extends GetxController {
  Guest? get _guest => MiddlewareService.find.guest.value;

  String get name => _guest?.fullName ?? '';
  String get email => _guest?.email ?? '';

  void openPreferences() => Get.toNamed(Routes.preferences);

  void openLoyalty() => Get.toNamed(Routes.loyalty);

  void openNotifications() => Get.toNamed(Routes.notifications);

  void openSavedPayments() => Get.toNamed(Routes.savedPayments);

  void openSecurity() => Get.toNamed(Routes.security);

  /// My Profile page — the stored details, with Edit Profile inside it.
  void openProfile() => Get.toNamed(Routes.profile);

  /// Published FAQs (`GET /public/faqs`) plus a route into the staff chat.
  void openSupport() => Get.toNamed(Routes.support);

  /// Terms, privacy and about, from `GET /public/pages/{slug}`.
  void openLegal() => Get.toNamed(Routes.legal);

  /// True while the loyalty check before the delete dialog, or the delete
  /// itself, is running — disables the button so one tap is one request.
  final RxBool deleting = false.obs;

  /// Delete account (`DELETE /auth/guest/me`). Irreversible, so it asks first,
  /// and tells the guest what the points programme will take with it: every
  /// point and unspent voucher is forfeited with the account.
  Future<void> confirmDeleteAccount() async {
    if (deleting.value) return;
    deleting.value = true;
    final forfeit = await _loyaltyAtStake();
    if (isClosed) return;
    deleting.value = false;

    CustomDialogs.showConfirmationDialog(
      type: AppDialogType.destructive,
      title: AppTranslations.deleteAccountTitle,
      message: [AppTranslations.deleteAccountBody, ?forfeit].join('\n\n'),
      icon: Icons.delete_outline,
      accentColor: AppColors.brickRed,
      confirmationText: AppTranslations.deleteAccountConfirm,
      cancellationText: AppTranslations.cancel,
      onPressed: _deleteAccount,
    );
  }

  /// "You will also lose N points and M vouchers", or null when there is
  /// nothing to lose. When the balance could not be read the guest is still
  /// warned, in general terms: deletion forfeits everything either way.
  Future<String?> _loyaltyAtStake() async {
    final results = await Future.wait([
      ApiService.find.get<Map<String, dynamic>>(
        path: '/loyalty/account',
        showErrorDialog: false,
      ),
      ApiService.find.get<List<dynamic>>(
        path: '/loyalty/vouchers',
        // Paged (15 by default): ask for them all so the count is right.
        queryParameters: {'status': 'active', 'per_page': 100},
        showErrorDialog: false,
      ),
    ]);
    final points = results[0].hasData
        ? LoyaltyAccount.fromJson(
            results[0].data! as Map<String, dynamic>,
          ).availablePoints
        : null;
    final vouchers = results[1].hasData
        ? (results[1].data! as List)
              .whereType<Map<String, dynamic>>()
              .map(LoyaltyVoucher.fromJson)
              .where((v) => v.isUsable)
              .length
        : null;
    return forfeitMessage(points: points, vouchers: vouchers);
  }

  /// The forfeit line for the delete dialog. A null count means it could not
  /// be read, so the line cannot claim there is nothing to lose.
  static String? forfeitMessage({
    required int? points,
    required int? vouchers,
  }) {
    if (points == null || vouchers == null) {
      return AppTranslations.deleteForfeitUnknown;
    }
    if (points > 0 && vouchers > 0) {
      return AppTranslations.deleteForfeitBoth(
        points: points.formatPoints(),
        count: vouchers.formatPoints(),
      );
    }
    if (points > 0) {
      return AppTranslations.deleteForfeitPoints(points.formatPoints());
    }
    if (vouchers > 0) {
      return AppTranslations.deleteForfeitVouchers(vouchers.formatPoints());
    }
    return null;
  }

  Future<void> _deleteAccount() async {
    if (deleting.value) return;
    deleting.value = true;
    final res = await ApiService.find.delete<dynamic>(
      path: '/auth/guest/me',
      data: {'confirm': true},
      showErrorDialog: false,
    );
    if (isClosed) return;
    deleting.value = false;

    if (res.ok) {
      // The server already revoked every token, this one included, so there is
      // nothing to log out remotely.
      await MiddlewareService.find.signOut(revokeRemotely: false);
      Get.offAllNamed(Routes.reservationChoice);
      CustomSnackbars.showSuccess(message: AppTranslations.accountDeleted);
      return;
    }
    final error = res.error;
    if (error?.errorCode == ErrorCodes.guestAccountDeletionBlocked) {
      _showDeletionBlocked(error!.context);
    } else if (error != null) {
      ApiService.find.dialogs.showError(error);
    }
  }

  /// An active booking, an open bill or an upcoming service booking stops the
  /// deletion. Says which, and names the bookings so the desk can find them.
  void _showDeletionBlocked(Map<String, dynamic> context) {
    final reasons = (context['reasons'] as List? ?? const [])
        .map(
          (r) => switch (r) {
            'active_reservation' => AppTranslations.deleteReasonReservation,
            'open_folio' => AppTranslations.deleteReasonFolio,
            'upcoming_service_booking' => AppTranslations.deleteReasonService,
            _ => null,
          },
        )
        .whereType<String>();
    final codes = (context['booking_codes'] as List? ?? const []).join(', ');
    CustomDialogs.showConfirmationDialog(
      type: AppDialogType.warning,
      title: AppTranslations.deleteBlockedTitle,
      message: [
        ...reasons,
        if (codes.isNotEmpty) AppTranslations.deleteBlockedBookings(codes),
        AppTranslations.deleteBlockedBody,
      ].join('\n'),
      icon: Icons.info_outline,
      accentColor: AppColors.antiqueGold,
      cancellationText: AppTranslations.close,
    );
  }

  /// Sign-out calls `POST /auth/guest/logout` (MiddlewareService.signOut) to
  /// revoke the token, then clears the local session whatever the answer — a
  /// guest must always be able to sign out, even offline.
  void confirmSignOut() {
    showSignOutSheet(
      onConfirm: () async {
        await MiddlewareService.find.signOut();
        // Back to the entry fork ("do you have a reservation?") rather than
        // straight to Sign In — a signed-out guest may want to browse or link a
        // booking, and Sign In is only one of those three paths.
        Get.offAllNamed(Routes.reservationChoice);
      },
    );
  }
}
