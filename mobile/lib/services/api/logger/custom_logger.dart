import 'package:dio/dio.dart';
import 'package:pretty_dio_logger/pretty_dio_logger.dart';

class CustomPrettyDioLogger extends PrettyDioLogger {
  CustomPrettyDioLogger({
    super.requestHeader,
    super.requestBody,
    super.responseHeader,
    super.responseBody,
  });

  bool _disabled(RequestOptions options) =>
      options.extra['disableLogger'] == true ||
      options.responseType == ResponseType.bytes;

  @override
  void onRequest(RequestOptions options, RequestInterceptorHandler handler) {
    if (_disabled(options)) {
      handler.next(options);
      return;
    }
    super.onRequest(options, handler);
  }

  @override
  void onResponse(Response response, ResponseInterceptorHandler handler) {
    if (_disabled(response.requestOptions)) {
      handler.next(response);
      return;
    }
    super.onResponse(response, handler);
  }

  @override
  void onError(DioException err, ErrorInterceptorHandler handler) {
    if (_disabled(err.requestOptions)) {
      handler.next(err);
      return;
    }
    super.onError(err, handler);
  }
}
