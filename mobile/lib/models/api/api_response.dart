import 'package:carlton/models/api/api_exception.dart';
import 'package:carlton/models/api/paginated_meta.dart';

/// Result of an [ApiService] call. **Never thrown — always returned.**
///
/// On failure the service has already shown the error (unless the call opted
/// out with `showErrorDialog: false`), so callers only need to guard before
/// touching [data]:
///
/// ```dart
/// final res = await _api.get<List<dynamic>>(path: '/cities');
/// if (res.statusCode != 200) return;
/// cities.assignAll(res.data!.map((e) => City.fromJson(e)));
/// ```
///
/// [data] is null exactly when the call failed. Dart can't promote it from a
/// `statusCode` check, so reads inside the guard need `!` — which fails
/// loudly at that line if the guard is ever forgotten.
class ApiResponse<T> {
  final int statusCode;
  final T? data;
  final String? message;
  final String? requestId;
  final bool? success;
  final PaginationMeta? meta;

  /// The failure that produced this response, or null on success. Branch on
  /// `error!.errorCode` / `error!.validationErrors` when a caller needs more
  /// than "it failed" — reverting an optimistic update, showing inline field
  /// errors, or reporting explicitly after `showErrorDialog: false`.
  final ApiException? error;

  ApiResponse({
    required this.statusCode,
    required this.data,
    this.message,
    this.requestId,
    this.success,
    this.meta,
    this.error,
  });

  factory ApiResponse.fromJson(
    Map<String, dynamic> json, {
    required int statusCode,
    required T Function(dynamic) dataParser,
  }) {
    final rawData = json['data'];
    PaginationMeta? meta;
    dynamic parsedData = rawData;

    // Detect paginated envelope: data.items + data.meta
    if (rawData is Map<String, dynamic> &&
        rawData.containsKey('items') &&
        rawData.containsKey('meta')) {
      meta = PaginationMeta.fromJson(rawData['meta'] as Map<String, dynamic>);
      parsedData = rawData['items']; // unwrap one level — T will be List<...>
    }

    return ApiResponse<T>(
      statusCode: statusCode,
      data: dataParser(parsedData),
      message: json['message'] as String?,
      requestId: json['request_id'] as String?,
      success: json['success'] as bool?,
      meta: meta,
    );
  }

  factory ApiResponse.raw({required int statusCode, required T? data}) {
    return ApiResponse<T>(statusCode: statusCode, data: data);
  }

  /// Failed call. Carries the exception's status so callers can branch on
  /// [statusCode] alone; `statusCode` is 0 for network-level failures
  /// (offline, timeout, cancelled) that never reached the server.
  factory ApiResponse.failure(ApiException e) {
    return ApiResponse<T>(
      statusCode: e.statusCode,
      data: null,
      message: e.message,
      requestId: e.requestId,
      success: false,
      error: e,
    );
  }

  /// True when the call succeeded. Equivalent to a 2xx check, but also the
  /// place to look when a call can legitimately return 201/204.
  bool get ok => error == null;

  bool get isCreated => statusCode == 201;
  bool get isNoContent => statusCode == 204;
  bool get isPaginated => meta != null;

  /// True when the failure was a cancelled request — a disposed controller
  /// cancelling its token, not something worth reporting or retrying.
  bool get isCancelled => error?.isCancelled ?? false;
}
