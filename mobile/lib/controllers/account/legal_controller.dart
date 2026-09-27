import 'package:carlton/models/info_page.dart';
import 'package:carlton/services/api/api_service.dart';
import 'package:get/get.dart';

/// Backs the Legal screen. Fetches each slug in [InfoPageSlug.all] from
/// `GET /public/pages/{slug}` and renders whichever ones came back.
///
/// One request per page because the API exposes no bulk page read — there are
/// three, they are fetched in parallel, and a slug the CMS has not published
/// 404s and is simply absent from the list rather than failing the screen.
class LegalController extends GetxController {
  final RxList<InfoPage> pages = <InfoPage>[].obs;
  final RxBool loading = true.obs;

  /// True only when *every* page failed, which means the screen has nothing to
  /// show and a Retry is the useful offer. A partial result is not an error.
  final RxBool error = false.obs;

  /// Which page is expanded, by slug; empty means all collapsed.
  final RxString expandedSlug = ''.obs;

  @override
  void onInit() {
    super.onInit();
    load();
  }

  Future<void> load() async {
    loading.value = true;
    error.value = false;
    final responses = await Future.wait(
      InfoPageSlug.all.map(
        (slug) => ApiService.find.get<Map<String, dynamic>>(
          path: '/public/pages/$slug',
          showErrorDialog: false,
        ),
      ),
    );
    if (isClosed) return;
    final loaded = <InfoPage>[];
    for (final res in responses) {
      if (res.statusCode == 200 && res.data != null) {
        loaded.add(InfoPage.fromJson(res.data!));
      }
    }
    pages.assignAll(loaded);
    error.value = loaded.isEmpty;
    loading.value = false;
    // Open the first page so the screen does not read as an empty list of
    // headings; there are only ever three and the first is the terms.
    if (loaded.isNotEmpty) expandedSlug.value = loaded.first.slug;
  }

  void toggle(String slug) =>
      expandedSlug.value = expandedSlug.value == slug ? '' : slug;

  bool isExpanded(String slug) => expandedSlug.value == slug;
}
