import 'package:carlton/models/api/paginated_meta.dart';

class ApiResponse<T> {
  final int statusCode;
  final T data;
  final String? message;
  final String? requestId;
  final bool? success;
  final PaginationMeta? meta;

  ApiResponse({
    required this.statusCode,
    required this.data,
    this.message,
    this.requestId,
    this.success,
    this.meta,
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

  factory ApiResponse.raw({required int statusCode, required T data}) {
    return ApiResponse<T>(statusCode: statusCode, data: data);
  }

  bool get isCreated => statusCode == 201;
  bool get isNoContent => statusCode == 204;
  bool get isPaginated => meta != null;
}
