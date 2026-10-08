import 'package:carlton/mixins/paginated_controller_mixin.dart';
import 'package:carlton/models/loyalty.dart';
import 'package:carlton/models/pagination.dart';
import 'package:carlton/routes/routes.dart';
import 'package:carlton/services/api/api_service.dart';
import 'package:dio/dio.dart';
import 'package:get/get.dart';

/// The Loyalty screen: the points balance (`GET /loyalty/account`) and the
/// ledger under it (`GET /loyalty/ledger`, paginated through the mixin).
///
/// The two load independently, so a failing ledger does not blank the balance
/// and the other way round. This is the one owner of both: the rewards and
/// vouchers screens, a redeem, a cancelled booking and a push all refresh
/// through [reloadAll] rather than keeping a second copy of the balance.
class LoyaltyController extends GetxController
    with PaginatedControllerMixin<LoyaltyLedgerEntry> {
  final CancelToken _cancel = CancelToken();

  final Rxn<LoyaltyAccount> account = Rxn<LoyaltyAccount>();
  final RxBool accountLoading = true.obs;
  final RxBool accountError = false.obs;

  @override
  void onInit() {
    super.onInit();
    initPagination(_cancel);
    reloadAll();
  }

  @override
  void onClose() {
    _cancel.cancel();
    // Chains into PaginatedControllerMixin.onClose → disposes scrollController.
    super.onClose();
  }

  /// Refreshes the balance and the ledger. Named so it does not collide with
  /// `GetxController.refresh`, which the framework calls to notify listeners.
  Future<void> reloadAll() => Future.wait([loadAccount(), loadItems(_cancel)]);

  Future<void> loadAccount() async {
    accountLoading.value = true;
    accountError.value = false;
    final res = await ApiService.find.get<Map<String, dynamic>>(
      path: '/loyalty/account',
      showErrorDialog: false,
      cancelToken: _cancel,
    );
    if (isClosed || res.isCancelled) return;
    if (res.hasData) {
      account.value = LoyaltyAccount.fromJson(res.data!);
    } else if (account.value == null) {
      accountError.value = true;
    }
    accountLoading.value = false;
  }

  @override
  Future<({List<LoyaltyLedgerEntry> items, Pagination pagination})?> fetchPage(
    int page,
    CancelToken cancelToken,
  ) async {
    final res = await ApiService.find.get<List<dynamic>>(
      path: '/loyalty/ledger',
      queryParameters: {'page': page},
      showErrorDialog: false,
      cancelToken: cancelToken,
    );
    if (!res.hasData) return null;
    return (
      items: res.data!
          .whereType<Map<String, dynamic>>()
          .map(LoyaltyLedgerEntry.fromJson)
          .toList(),
      pagination: res.meta ?? Pagination(),
    );
  }

  void openRewards() => Get.toNamed(Routes.loyaltyRewards);
  void openVouchers() => Get.toNamed(Routes.loyaltyVouchers);
}
