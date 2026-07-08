import 'package:carlton/services/middleware_service.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';

class Middleware extends GetMiddleware {
  @override
  RouteSettings? redirect(String? route) {
    final middlewareService = Get.find<MiddlewareService>();
    if (middlewareService.hasNoToken || middlewareService.isTokenInvalid) {
    } else if (middlewareService.isTokenValid) {}
    return null;
  }
}
