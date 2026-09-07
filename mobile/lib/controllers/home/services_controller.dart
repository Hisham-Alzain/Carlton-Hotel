import 'package:carlton/components/sheets/service_request_sheet.dart';
import 'package:carlton/constants/demo_data.dart';
import 'package:carlton/constants/error_codes.dart';
import 'package:carlton/customWidgets/custom_bottom_sheet.dart';
import 'package:carlton/customWidgets/custom_snackbar.dart';
import 'package:carlton/enums/enums.dart';
import 'package:carlton/l10n/app_translations.dart';
import 'package:carlton/models/home_models.dart';
import 'package:carlton/models/service_catalog_item.dart';
import 'package:carlton/models/service_item.dart';
import 'package:carlton/models/service_request.dart';
import 'package:carlton/models/stay.dart';
import 'package:carlton/routes/routes.dart';
import 'package:carlton/services/api/api_service.dart';
import 'package:carlton/services/middleware_service.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:intl/intl.dart';

class ServicesController extends GetxController
    with GetSingleTickerProviderStateMixin {
  // Drives the themed Material TabBar (All Services / Active Requests).
  late final TabController tabController;
  final RxInt tabIndex = 0.obs;

  // Auth + entitlement come from the unified session (MiddlewareService).
  bool get isLoggedIn => MiddlewareService.find.isAuthenticated;
  bool get hasReservation => MiddlewareService.find.hasBooking;

  /// The three distinct home states the screen renders. A booking always wins
  /// (it implies a signed-in account → stay card); otherwise a signed-in user
  /// sees the Explore & Book prompt and a guest sees the sign-in banner.
  ServicesHomeState get homeState {
    if (hasReservation) return ServicesHomeState.activeStay;
    if (isLoggedIn) return ServicesHomeState.exploreAndBook;
    return ServicesHomeState.guestBrowse;
  }

  // Active-stay header (Services stay card) — populated from `GET /stays/active`
  // once the guest is checked in; blank until then (no more demo Room 812).
  final RxString room = ''.obs;
  final RxString stayRoomName = ''.obs;
  final RxString checkedInTime = ''.obs;
  final RxInt nightsRemaining = 0.obs;
  final String stayImagePath = 'assets/images/stay_room.png';

  // The eight hub tiles are decorative demo (icons/subtitles); each carries a
  // stable [ServiceItem.code] matched against the fetched catalog.
  final List<ServiceItem> services = DemoData.services;

  /// Live service catalog (`GET /public/service-catalog`, active only) and the
  /// guest's own active requests (`GET /service-requests`, checked-in only) —
  /// first-page fetch only, same rationale as Discover (not the paginated
  /// mixin).
  final RxList<ServiceCatalogItem> catalog = <ServiceCatalogItem>[].obs;
  final RxList<ServiceRequest> activeRequests = <ServiceRequest>[].obs;

  /// Do Not Disturb switch state (the `toggle`-kind tile), backed by
  /// `PATCH /stays/active/dnd`. Optimistic; reverts on failure.
  final RxBool dndEnabled = false.obs;

  @override
  void onInit() {
    super.onInit();
    tabController = TabController(length: 2, vsync: this);
    tabController.addListener(() {
      if (tabIndex.value != tabController.index) {
        tabIndex.value = tabController.index;
      }
    });
    _loadCatalog();
    if (MiddlewareService.find.isCheckedIn) {
      _loadActiveRequests();
      _loadActiveStay();
    }
  }

  @override
  void onClose() {
    tabController.dispose();
    super.onClose();
  }

  /// Fetches the public service catalog. A failure leaves the grid on its demo
  /// tiles (tap falls through to "coming soon") rather than erroring.
  Future<void> _loadCatalog() async {
    final res = await ApiService.find.get<List<dynamic>>(
      path: '/public/service-catalog',
      showErrorDialog: false,
    );
    if (isClosed) return;
    if (res.statusCode != 200 || res.data == null) return;
    catalog.value = res.data!
        .whereType<Map<String, dynamic>>()
        .map(ServiceCatalogItem.fromJson)
        .where((c) => c.isActive)
        .toList();
  }

  /// Loads the guest's own service requests (tier-3b — checked in). Empty is a
  /// valid state, not an error.
  Future<void> _loadActiveRequests() async {
    final res = await ApiService.find.get<List<dynamic>>(
      path: '/service-requests',
      showErrorDialog: false,
    );
    if (isClosed) return;
    if (res.statusCode != 200 || res.data == null) return;
    activeRequests.value = ServiceRequest.listFromJson(res.data!);
  }

  /// Loads the active-stay header (`GET /stays/active`, tier-3b) for the Services
  /// stay card. A failure (or no active stay) leaves it blank, not errored.
  Future<void> _loadActiveStay() async {
    final res = await ApiService.find.get<Map<String, dynamic>?>(
      path: '/stays/active',
      showErrorDialog: false,
    );
    if (isClosed) return;
    if (!res.ok || res.data == null) return;
    final s = ActiveStay.fromJson(res.data!);
    room.value = (s.roomNumber != null && s.roomNumber!.isNotEmpty)
        ? 'Room ${s.roomNumber}'
        : s.roomName.value;
    stayRoomName.value = s.roomName.value;
    nightsRemaining.value = s.nightsRemaining;
    checkedInTime.value = s.checkedInAt != null
        ? _timeFormat.format(s.checkedInAt!)
        : (s.checkIn != null ? _dateFormat.format(s.checkIn!) : '');
  }

  static final DateFormat _timeFormat = DateFormat('h:mm a');
  static final DateFormat _dateFormat = DateFormat('MMM d');

  void switchTab(int index) => tabController.animateTo(index);

  void quickRequest(String label) =>
      CustomSnackbars.showInfo(message: AppTranslations.itemSelected(label));

  /// Opens a services-hub grid tile by its stable [tileCode], routing on the
  /// matching catalog item's [ServiceCatalogItem.kind]. Unknown codes/kinds
  /// fall back to the coming-soon snackbar.
  void openServiceCategory(String tileCode) {
    final item = _catalogByCode(tileCode);
    if (item == null) {
      CustomSnackbars.showInfo(message: AppTranslations.serviceComingSoon);
      return;
    }
    switch (item.kind) {
      // catalog → its microservice list → per-item request sheet.
      case 'catalog':
        Get.toNamed(Routes.serviceCategory, arguments: item);
      // direct → a notes sheet that posts the category's default item straight.
      case 'direct':
        final uuid = item.defaultItemUuid;
        if (uuid == null || uuid.isEmpty) {
          CustomSnackbars.showInfo(message: AppTranslations.serviceComingSoon);
          return;
        }
        openServiceRequest(
          ServiceCatalogOption(
            uuid: uuid,
            name: item.name,
            description: item.description,
          ),
        );
      // link → navigate; the only target today is the dining list.
      case 'link':
        if (item.linkTarget == 'dining') {
          Get.toNamed(Routes.discover, arguments: DiscoverSection.dining);
        } else {
          CustomSnackbars.showInfo(message: AppTranslations.serviceComingSoon);
        }
      // toggle → the DND switch sheet.
      case 'toggle':
        _openDndSheet();
      default:
        CustomSnackbars.showInfo(message: AppTranslations.serviceComingSoon);
    }
  }

  ServiceCatalogItem? _catalogByCode(String code) {
    for (final item in catalog) {
      if (item.code == code) return item;
    }
    return null;
  }

  /// Title/subtitle go to the sheet shell rather than the body so they share
  /// the close button's row (Figma "Services 4").
  void openServiceRequest(ServiceCatalogOption option) {
    final eta = option.expectedMinutes != null
        ? ' · ${AppTranslations.etaMinutes(option.expectedMinutes!)}'
        : '';
    CustomBottomSheet.show<void>(
      title: option.name.value,
      subtitle: '${option.description.value}$eta',
      child: ServiceRequestSheet(
        option: option,
        stayLabel: [room, stayRoomName].where((s) => s.isNotEmpty).join(' · '),
        onSubmit: (notes) {
          submitServiceRequest(serviceItemUuid: option.uuid, notes: notes);
          Get.back();
        },
      ),
    );
  }

  /// Submits a service request (tier-3b). On success prepends the new row to
  /// [activeRequests] and jumps to the Active Requests tab.
  Future<void> submitServiceRequest({
    required String serviceItemUuid,
    String? notes,
    String priority = 'normal',
  }) async {
    final res = await ApiService.find.post<Map<String, dynamic>>(
      path: '/service-requests',
      data: {
        'service_item_uuid': serviceItemUuid,
        'priority': priority,
        if (notes != null && notes.trim().isNotEmpty) 'notes': notes.trim(),
      },
      showErrorDialog: false,
    );
    if (isClosed) return;
    if (res.statusCode == 201 && res.data != null) {
      // RxList.insert notifies on its own.
      activeRequests.insert(0, ServiceRequest.fromJson(res.data!));
      switchTab(1);
      CustomSnackbars.showSuccess(message: AppTranslations.requestSubmitted);
      return;
    }
    switch (res.error?.errorCode) {
      case ErrorCodes.noActiveReservation:
        CustomSnackbars.showError(message: AppTranslations.requestNeedsCheckIn);
      case ErrorCodes.validationFailed:
        CustomSnackbars.showError(
          message: res.error?.message ?? AppTranslations.requestFailed,
        );
      default:
        CustomSnackbars.showError(message: AppTranslations.requestFailed);
    }
  }

  /// The DND (`toggle`) tile opens a small sheet with a Switch bound here.
  void _openDndSheet() {
    CustomBottomSheet.show<void>(
      title: AppTranslations.dndTitle,
      subtitle: AppTranslations.dndSubtitle,
      child: Obx(
        () => SwitchListTile(
          value: dndEnabled.value,
          onChanged: toggleDnd,
          contentPadding: EdgeInsets.zero,
          title: Text(AppTranslations.dndSwitchLabel),
        ),
      ),
    );
  }

  /// Optimistically flips DND, then persists via `PATCH /stays/active/dnd`
  /// (tier-3b). Reverts on failure.
  Future<void> toggleDnd(bool enabled) async {
    final previous = dndEnabled.value;
    dndEnabled.value = enabled;
    final res = await ApiService.find.patch<Map<String, dynamic>>(
      path: '/stays/active/dnd',
      data: {'enabled': enabled},
      showErrorDialog: false,
    );
    if (isClosed) return;
    if (res.statusCode != 200) {
      dndEnabled.value = previous;
      final message = res.error?.errorCode == ErrorCodes.noActiveReservation
          ? AppTranslations.dndNeedsCheckIn
          : AppTranslations.dndFailed;
      CustomSnackbars.showError(message: message);
    }
  }

  /// No edit endpoint exists yet — kept as a coming-soon no-op.
  void editRequest(ServiceRequest request) =>
      CustomSnackbars.showInfo(message: AppTranslations.editComingSoon);
}
