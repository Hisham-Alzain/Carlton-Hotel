import 'package:carlton/constants/error_codes.dart';
import 'package:carlton/customWidgets/custom_snackbar.dart';
import 'package:carlton/l10n/app_translations.dart';
import 'package:carlton/mixins/paginated_controller_mixin.dart';
import 'package:carlton/models/pagination.dart';
import 'package:carlton/models/review.dart';
import 'package:carlton/services/api/api_service.dart';
import 'package:dio/dio.dart';
import 'package:get/get.dart';

/// Owns BOTH the reviews list (`GET /public/reviews/{type}/{uuid}`) and the
/// submit (`POST /reviews/{type}/{uuid}`) for ONE target. Used by the restaurant
/// Reviews tab (autoLoad: true) and the Room Details submit sheet
/// (autoLoad: false — the list is never rendered there).
///
/// A separate controller because [RestaurantController] already mixes
/// `GetSingleTickerProviderStateMixin` + `GetBuilder` and owns the menu, and the
/// single-`T` [PaginatedControllerMixin] can't be mixed twice — the same reason
/// Phase 2 kept multi-content controllers off the mixin.
class ReviewController extends GetxController
    with PaginatedControllerMixin<Review> {
  final ReviewTargetType reviewType;
  final String targetUuid;
  final bool autoLoad;

  ReviewController({
    required this.reviewType,
    required this.targetUuid,
    this.autoLoad = true,
  });

  final CancelToken _cancelToken = CancelToken();

  @override
  void onInit() {
    super.onInit();
    initPagination(_cancelToken);
    // A demo target (empty uuid) renders the empty state rather than calling
    // the API; the submit sheet path never loads the list at all.
    if (autoLoad && targetUuid.isNotEmpty) loadItems(_cancelToken);
  }

  @override
  void onClose() {
    _cancelToken.cancel();
    // Chains into PaginatedControllerMixin.onClose → disposes scrollController.
    super.onClose();
  }

  @override
  Future<({List<Review> items, Pagination pagination})?> fetchPage(
    int page,
    CancelToken cancelToken,
  ) async {
    final res = await ApiService.find.get<List<dynamic>>(
      path: '/public/reviews/${reviewType.apiPath}/$targetUuid',
      queryParameters: {'page': page},
      showErrorDialog: false,
      cancelToken: cancelToken,
    );
    if (res.statusCode != 200 || res.data == null) return null;
    final items = res.data!
        .whereType<Map<String, dynamic>>()
        .map(Review.fromJson)
        .toList();
    return (items: items, pagination: res.meta ?? Pagination());
  }

  /// Submits (or edits) this guest's review. Returns true on success; on failure
  /// it surfaces the error via a snackbar and returns false. The caller must
  /// have auth-gated the entry point — a POST while unauthenticated returns 401
  /// and fires the global logout.
  Future<bool> submitReview({required int rating, String? comment}) async {
    final res = await ApiService.find.post<Map<String, dynamic>>(
      path: '/reviews/${reviewType.apiPath}/$targetUuid',
      data: {
        'rating': rating,
        if (comment != null && comment.isNotEmpty) 'comment': comment,
      },
      showErrorDialog: false,
    );
    if (isSaved(res.statusCode)) {
      // Refresh the list so the just-submitted (or edited) review shows.
      if (autoLoad) await loadItems(_cancelToken);
      return true;
    }
    switch (res.error?.errorCode) {
      case ErrorCodes.validationFailed:
        {
          final errors = res.error!.validationErrors;
          CustomSnackbars.showError(
            message: errors.isNotEmpty && errors.values.first.isNotEmpty
                ? errors.values.first.first
                : res.error!.message,
          );
        }
      default:
        CustomSnackbars.showError(
          message: res.error?.message ?? AppTranslations.unknownError,
        );
    }
    return false;
  }

  /// HTTP 201 (first submission) AND HTTP 200 (edit) are BOTH success — do not
  /// branch UI on which. THE hermetic test seam: [submitReview] MUST call this,
  /// never an inline `== 201`, so the 200-vs-201 regression is caught by a pure
  /// unit test.
  static bool isSaved(int? statusCode) =>
      statusCode == 200 || statusCode == 201;
}
