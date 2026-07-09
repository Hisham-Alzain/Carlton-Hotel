import 'package:carlton/enums/enums.dart';
import 'package:carlton/models/address.dart';
import 'package:carlton/services/api/api_service.dart';
import 'package:dio/dio.dart';
import 'package:geolocator/geolocator.dart';
import 'package:get/get.dart';

class AddressService extends GetxService {
  late final ApiService apiService;
  late final CancelToken cancelToken;
  RxList<Address> addresses = RxList.empty();
  Rxn<Address> selectedAddress = Rxn<Address>();
  RxBool loading = true.obs;

  @override
  void onInit() async {
    super.onInit();
    apiService = Get.find<ApiService>();
    cancelToken = CancelToken();
  }

  static AddressService get find => Get.find();

  Future<(Position?, LocationFailure?)> getCurrentLocation() async {
    bool serviceEnabled = await Geolocator.isLocationServiceEnabled();
    if (!serviceEnabled) return (null, LocationFailure.serviceDisabled);

    LocationPermission permission = await Geolocator.checkPermission();
    if (permission == LocationPermission.denied) {
      permission = await Geolocator.requestPermission();
      if (permission == LocationPermission.denied) {
        return (null, LocationFailure.permissionDenied);
      }
    }

    if (permission == LocationPermission.deniedForever) {
      return (null, LocationFailure.permissionForever);
    }

    final position = await Geolocator.getCurrentPosition(
      locationSettings: LocationSettings(accuracy: LocationAccuracy.medium),
    );
    return (position, null);
  }

  // update to use google maps api if needed
  // Future<String?> getAddressFromLatLng(double lat, double lng) async {
  //   try {
  //TODO:check latest version from the new geocoding package, the current version is deprecated

  // List<Placemark> placemarks = await placemarkFromCoordinates(lat, lng);

  // if (placemarks.isEmpty) return null;

  // Placemark place = placemarks.first;

  // return "${place.country},${place.administrativeArea},${place.subLocality},${place.name}";
  //   } catch (e) {
  //     return null;
  //   }
  // }
}
