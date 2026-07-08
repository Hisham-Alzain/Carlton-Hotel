import 'package:carlton/models/app_info_version.dart';
import 'package:carlton/services/api/api_service.dart';
import 'package:dio/dio.dart';
import 'package:get/get.dart';
import 'package:package_info_plus/package_info_plus.dart';

class AppVersionChecker {
  AppVersionChecker._();

  /// Never throws — fails open (returns null) on any error, bad response,
  /// or timeout, so a slow/broken endpoint can never block app startup.
  static Future<AppVersionInfo?> check() async {
    try {
      final packageInfo = await PackageInfo.fromPlatform();
      final currentVersion =
          '${packageInfo.version}+${packageInfo.buildNumber}';

      final response = await Get.find<ApiService>()
          .get(
            path: '/user/app/version',
            queryParameters: {
              'platform': GetPlatform.isIOS ? 'ios' : 'android',
              'currentVersion': currentVersion,
            },
            cancelToken: CancelToken(),
          )
          .timeout(const Duration(seconds: 5));

      if (response.statusCode == 200) {
        return AppVersionInfo.fromJson(response.data as Map<String, dynamic>);
      }
      return null;
    } catch (_) {
      return null;
    }
  }
}
