import 'package:carlton/models/folio.dart';
import 'package:carlton/services/api/api_service.dart';
import 'package:get/get.dart';

/// Backs the My Bill screen: the guest's running folio (`GET /folio`, tier-3b).
///
/// HomeController already fetches the folio for the dashboard summary card, but
/// it keeps only the flattened `(description, amount)` pairs that card needs.
/// This holds the [Folio] itself, because the statement shows what the summary
/// drops: each line's source, the subtotal against the total, and whether the
/// guest has already approved checkout.
class FolioController extends GetxController {
  final Rx<Folio?> folio = Rx<Folio?>(null);
  final RxBool loading = true.obs;

  /// True when the fetch failed. Distinct from "loaded with no items": a guest
  /// who has charged nothing to the room has an empty but valid folio, and only
  /// a failure should offer Retry.
  final RxBool error = false.obs;

  @override
  void onInit() {
    super.onInit();
    load();
  }

  Future<void> load() async {
    loading.value = true;
    error.value = false;
    final res = await ApiService.find.get<Map<String, dynamic>>(
      path: '/folio',
      showErrorDialog: false,
    );
    if (isClosed) return;
    if (res.statusCode == 200 && res.data != null) {
      folio.value = Folio.fromJson(res.data!);
    } else {
      error.value = true;
    }
    loading.value = false;
  }

  /// Already approved for express checkout. The screen acts on this only by not
  /// implying the bill is still open; approval itself lives on Home.
  bool get isApproved => folio.value?.approvedByGuestAt != null;
}
