import 'package:carlton/mixins/paginated_controller_mixin.dart';
import 'package:carlton/models/faq.dart';
import 'package:carlton/models/pagination.dart';
import 'package:carlton/routes/routes.dart';
import 'package:carlton/services/api/api_service.dart';
import 'package:dio/dio.dart';
import 'package:get/get.dart';

/// Backs the Help & Support screen: the hotel's published FAQs
/// (`GET /public/faqs`) plus a route into the real staff chat.
///
/// Paginated through [PaginatedControllerMixin] because the endpoint is: it is a
/// `BasePublicIndexController` list, 15 per page, so a one-shot fetch would
/// silently drop every FAQ past the first page. The FAQ list is the whole
/// screen, so a failed first load is a state the guest can act on — the mixin's
/// `hasError` drives a Retry rather than the silent degradation the Home rails
/// use.
class SupportController extends GetxController
    with PaginatedControllerMixin<Faq> {
  final CancelToken _cancelToken = CancelToken();

  /// Which answer is expanded, by uuid. Only one at a time: the answers run long
  /// and two open at once pushes the rest off screen.
  final RxString expandedUuid = ''.obs;

  @override
  void onInit() {
    super.onInit();
    initPagination(_cancelToken);
    loadItems(_cancelToken);
  }

  @override
  void onClose() {
    _cancelToken.cancel();
    // Chains into PaginatedControllerMixin.onClose → disposes scrollController.
    super.onClose();
  }

  /// Retry from the error state. The view has no cancel token to pass, so it
  /// cannot call [loadItems] itself.
  Future<void> reload() => loadItems(_cancelToken);

  @override
  Future<({List<Faq> items, Pagination pagination})?> fetchPage(
    int page,
    CancelToken cancelToken,
  ) async {
    final res = await ApiService.find.get<List<dynamic>>(
      path: '/public/faqs',
      queryParameters: {'page': page},
      showErrorDialog: false,
      cancelToken: cancelToken,
    );
    if (!res.hasData) return null;
    return (
      items: Faq.listFromJson(res.data),
      pagination: res.meta ?? Pagination(),
    );
  }

  void toggle(String uuid) =>
      expandedUuid.value = expandedUuid.value == uuid ? '' : uuid;

  bool isExpanded(String uuid) => expandedUuid.value == uuid;

  /// The FAQs answer the common questions; anything else goes to the staff chat,
  /// which is a real conversation (`POST /conversations`).
  void contactUs() => Get.toNamed(Routes.aiConcierge);
}
