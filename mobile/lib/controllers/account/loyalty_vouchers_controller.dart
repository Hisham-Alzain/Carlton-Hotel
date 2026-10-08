import 'package:carlton/customWidgets/custom_snackbar.dart';
import 'package:carlton/l10n/app_translations.dart';
import 'package:carlton/mixins/paginated_controller_mixin.dart';
import 'package:carlton/models/loyalty.dart';
import 'package:carlton/models/pagination.dart';
import 'package:carlton/services/api/api_service.dart';
import 'package:dio/dio.dart';
import 'package:flutter/services.dart';
import 'package:get/get.dart';

/// The guest's vouchers (`GET /loyalty/vouchers`), newest first.
class LoyaltyVouchersController extends GetxController
    with PaginatedControllerMixin<LoyaltyVoucher> {
  final CancelToken _cancel = CancelToken();

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
  Future<({List<LoyaltyVoucher> items, Pagination pagination})?> fetchPage(
    int page,
    CancelToken cancelToken,
  ) async {
    final res = await ApiService.find.get<List<dynamic>>(
      path: '/loyalty/vouchers',
      queryParameters: {'page': page},
      showErrorDialog: false,
      cancelToken: cancelToken,
    );
    if (!res.hasData) return null;
    return (
      items: res.data!
          .whereType<Map<String, dynamic>>()
          .map(LoyaltyVoucher.fromJson)
          .toList(),
      pagination: res.meta ?? Pagination(),
    );
  }

  void reload() => loadItems(_cancel);

  void copyCode(LoyaltyVoucher voucher) {
    Clipboard.setData(ClipboardData(text: voucher.code));
    CustomSnackbars.showSuccess(message: AppTranslations.copied);
  }
}
