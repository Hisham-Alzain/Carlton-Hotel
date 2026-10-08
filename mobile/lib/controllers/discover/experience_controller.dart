import 'package:carlton/models/experience.dart';
import 'package:carlton/models/home_models.dart';
import 'package:carlton/routes/routes.dart';
import 'package:carlton/services/api/api_service.dart';
import 'package:flutter/widgets.dart';
import 'package:get/get.dart';

/// Backs the experience detail screen (`GET /public/experiences/{uuid}`).
///
/// The list row the guest tapped arrives via `Get.arguments` and is painted
/// immediately, so the screen is never blank; the detail fetch then fills in the
/// description, which the list projection does not carry. The endpoint 404s for
/// a draft experience, which is why a failed fetch leaves the row's own fields
/// standing rather than clearing them.
class ExperienceController extends GetxController {
  late final ExperienceItem item;

  /// Long-form copy, only on the detail response.
  final RxString about = ''.obs;
  final RxBool loading = true.obs;

  /// USD price. Zero hides the price line rather than printing "$0".
  final RxDouble priceUsd = 0.0.obs;

  @override
  void onInit() {
    super.onInit();
    final arg = Get.arguments;
    // No stand-in: without a row there is nothing to show, so leave and let the
    // list stay on screen.
    if (arg is! ExperienceItem) {
      item = const ExperienceItem(
        name: '',
        subtitle: '',
        hours: '',
        imagePath: '',
        category: '',
      );
      loading.value = false;
      // After this frame: onInit runs mid-navigation, so popping here would race
      // the route still being pushed.
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (Get.currentRoute == Routes.experienceDetail) Get.back<void>();
      });
      return;
    }
    item = arg;
    _load();
  }

  Future<void> _load() async {
    if (item.uuid.isEmpty) {
      loading.value = false;
      return;
    }
    final res = await ApiService.find.get<Map<String, dynamic>>(
      path: '/public/experiences/${item.uuid}',
      showErrorDialog: false,
    );
    if (isClosed) return;
    if (res.hasData) {
      final experience = Experience.fromJson(res.data!);
      about.value = experience.description.value;
      priceUsd.value = experience.priceUsd;
    }
    loading.value = false;
  }
}
