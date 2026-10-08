import 'package:carlton/constants/error_codes.dart';
import 'package:carlton/controllers/account/loyalty_controller.dart';
import 'package:carlton/customWidgets/custom_dialogs.dart';
import 'package:carlton/customWidgets/custom_snackbar.dart';
import 'package:carlton/extensions/date_extension.dart';
import 'package:carlton/extensions/points_extension.dart';
import 'package:carlton/l10n/app_translations.dart';
import 'package:carlton/mixins/paginated_controller_mixin.dart';
import 'package:carlton/models/api/api_response.dart';
import 'package:carlton/models/loyalty.dart';
import 'package:carlton/models/pagination.dart';
import 'package:carlton/services/api/api_service.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:get/get.dart';
import 'package:uuid/uuid.dart';

/// The rewards catalogue (`GET /loyalty/rewards`) and spending points on it
/// (`POST /loyalty/rewards/{uuid}/redeem`).
///
/// The balance comes from [LoyaltyController], so what the guest can afford and
/// what the Loyalty screen shows can never disagree.
class LoyaltyRewardsController extends GetxController
    with PaginatedControllerMixin<LoyaltyReward> {
  final CancelToken _cancel = CancelToken();

  /// The reward being redeemed — disables every Redeem button while a call is
  /// in flight, so one tap cannot become two.
  final RxnString redeemingUuid = RxnString();

  /// One `Idempotency-Key` per redeem intent, kept until the call settles. A
  /// tap that timed out and is tapped again reuses its key, so the server
  /// returns the voucher it already made instead of spending the points twice.
  final Map<String, String> _intentKeys = {};

  LoyaltyController get loyalty => Get.find<LoyaltyController>();

  @override
  void onInit() {
    super.onInit();
    initPagination(_cancel);
    loadItems(_cancel);
  }

  @override
  void onClose() {
    _cancel.cancel();
    super.onClose();
  }

  @override
  Future<({List<LoyaltyReward> items, Pagination pagination})?> fetchPage(
    int page,
    CancelToken cancelToken,
  ) async {
    final res = await ApiService.find.get<List<dynamic>>(
      path: '/loyalty/rewards',
      queryParameters: {'page': page},
      showErrorDialog: false,
      cancelToken: cancelToken,
    );
    if (!res.hasData) return null;
    return (
      items: res.data!
          .whereType<Map<String, dynamic>>()
          .map(LoyaltyReward.fromJson)
          .toList(),
      pagination: res.meta ?? Pagination(),
    );
  }

  void reload() => loadItems(_cancel);

  bool canAfford(LoyaltyReward reward) =>
      (loyalty.account.value?.availablePoints ?? 0) >= reward.pointsCost;

  /// Asks before spending: points are not given back for a mistaken tap.
  void confirmRedeem(LoyaltyReward reward) {
    if (redeemingUuid.value != null) return;
    CustomDialogs.showConfirmationDialog(
      title: AppTranslations.loyaltyConfirmRedeemTitle,
      message: AppTranslations.loyaltyConfirmRedeemBody(
        points: reward.pointsCost.formatPoints(),
        reward: reward.name.value,
      ),
      icon: Icons.card_giftcard_outlined,
      accentColor: AppColors.primary,
      confirmationText: AppTranslations.loyaltyRedeemReward,
      cancellationText: AppTranslations.cancel,
      onPressed: () => _redeem(reward),
    );
  }

  Future<void> _redeem(LoyaltyReward reward) async {
    if (redeemingUuid.value != null) return;
    final key = _intentKeys.putIfAbsent(reward.uuid, () => const Uuid().v4());
    redeemingUuid.value = reward.uuid;
    final res = await ApiService.find.post<Map<String, dynamic>>(
      path: '/loyalty/rewards/${reward.uuid}/redeem',
      idempotencyKey: key,
      showErrorDialog: false,
    );
    if (isClosed) return;
    redeemingUuid.value = null;

    if (res.hasData) {
      // Settled (201, or 200 on a replay): the next tap is a new intent.
      _intentKeys.remove(reward.uuid);
      loyalty.reloadAll();
      _showVoucher(LoyaltyVoucher.fromJson(res.data!));
      return;
    }
    _report(reward, res);
  }

  void _showVoucher(LoyaltyVoucher voucher) {
    final expires = voucher.expiresAt;
    CustomDialogs.showConfirmationDialog(
      title: AppTranslations.loyaltyVoucherReadyTitle,
      message: expires == null
          ? AppTranslations.loyaltyVoucherCodeIs(voucher.code)
          : '${AppTranslations.loyaltyVoucherCodeIs(voucher.code)} '
                '${AppTranslations.loyaltyVoucherExpires(expires.formatDatePicker())}',
      icon: Icons.verified_outlined,
      accentColor: AppColors.primary,
      confirmationText: AppTranslations.loyaltyCopyCode,
      cancellationText: AppTranslations.close,
      onPressed: () {
        Clipboard.setData(ClipboardData(text: voucher.code));
        CustomSnackbars.showSuccess(message: AppTranslations.copied);
      },
    );
  }

  void _report(LoyaltyReward reward, ApiResponse<Map<String, dynamic>> res) {
    final error = res.error;
    // A lost connection never reached the server: keep the key so the retry is
    // the same intent. Anything the server answered closes this intent.
    if (error != null && !error.isNetworkError) _intentKeys.remove(reward.uuid);

    switch (error?.errorCode) {
      case ErrorCodes.loyaltyInsufficientPoints:
        final available = (error!.context['available_points'] as num?)?.toInt();
        final requested = (error.context['requested_points'] as num?)?.toInt();
        loyalty.loadAccount();
        CustomSnackbars.showError(
          message: available != null && requested != null
              ? AppTranslations.loyaltyNeedMorePoints(
                  (requested - available).formatPoints(),
                )
              : AppTranslations.loyaltyNotEnoughPoints,
        );
      case ErrorCodes.loyaltyRewardUnavailable || ErrorCodes.notFound:
        reload();
        CustomSnackbars.showError(
          message: AppTranslations.loyaltyRewardUnavailable,
        );
      default:
        if (error != null) ApiService.find.dialogs.showError(error);
    }
  }
}
