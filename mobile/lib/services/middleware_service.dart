import 'package:carlton/constants/storage_keys.dart';
import 'package:carlton/customWidgets/custom_dialogs.dart';
import 'package:carlton/enums/enums.dart';
import 'package:carlton/services/api/api_service.dart';
import 'package:carlton/services/get_storage_service.dart';
import 'package:dio/dio.dart';
import 'package:get/get.dart';

class MiddlewareService extends GetxService {
  late final ApiService apiService;
  late bool isFirstTime;
  RxBool isGuest = false.obs;
  MiddlewareCases middlewareCase = MiddlewareCases.noToken;
  String userType = '';

  @override
  void onInit() {
    apiService = Get.find<ApiService>();
    isFirstTime = StorageService.getBool(StorageKeys.isFirstTime) ?? true;
    isGuest.value = false;
    super.onInit();
  }

  static MiddlewareService get find => Get.find();

  // Helper methods to check auth state
  bool get isTokenValid => middlewareCase == MiddlewareCases.validToken;
  bool get isTokenInvalid => middlewareCase == MiddlewareCases.invalidToken;
  bool get hasNoToken => middlewareCase == MiddlewareCases.noToken;
  bool get isCustomer => userType == 'customer';

  Future<void> checkToken() async {
    final String? token = StorageService.getString(StorageKeys.token);
    if (token == null) {
      middlewareCase = MiddlewareCases.noToken;
      return;
    } else {
      try {
        // log('token: $token');
        final response = await apiService.dio.get('/user/check-token');
        if (response.statusCode == 200) {
          // await NotificationService.find.requestPermission();
          userType = response.data['data']['role'];
          middlewareCase = MiddlewareCases.validToken;
        }
      } on DioException catch (e) {
        if (e.response?.statusCode == 401) {
          middlewareCase = MiddlewareCases.invalidToken;
          await StorageService.remove(StorageKeys.token);
          Future.delayed(const Duration(milliseconds: 3500), () async {
            CustomDialogs.showSessionExpiredDialog();
          });
        }
      }
    }
  }
}
