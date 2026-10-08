import 'dart:io';

import 'package:carlton/models/booking_models.dart';
import 'package:carlton/models/room_type.dart';
import 'package:carlton/services/settings_service.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:get/get.dart';
import 'package:get_storage/get_storage.dart';

/// The backend sends each amenity an `icon` key. Keys with a bundled SVG use
/// it; `tv` has none and must draw the Material TV icon, not the view glyph.
void main() {
  late Directory tempDir;

  setUpAll(() async {
    TestWidgetsFlutterBinding.ensureInitialized();
    tempDir = await Directory.systemTemp.createTemp('carlton_amenity_test');
    // Amenity names are read through SettingsService's locale.
    TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger
        .setMockMethodCallHandler(
          const MethodChannel('plugins.flutter.io/path_provider'),
          (call) async => tempDir.path,
        );
    await GetStorage.init();
  });

  tearDownAll(() async {
    try {
      if (tempDir.existsSync()) await tempDir.delete(recursive: true);
    } on FileSystemException {
      // Windows keeps the box file open; left for the OS to reap.
    }
  });

  setUp(() {
    Get.testMode = true;
    Get.put<SettingsService>(SettingsService());
  });

  tearDown(Get.reset);

  Map<String, dynamic> amenity(String icon, String name) => {
    'uuid': icon,
    'slug': icon,
    'name': {'en': name},
    'icon': icon,
    'sort_order': 0,
  };

  test('tv uses the Material icon, others their bundled SVG', () {
    final room = RoomOption.fromRoomType(
      RoomType.fromJson({
        'uuid': 'r1',
        'name': {'en': 'Suite'},
        'base_price_usd': '200.00',
        'amenities': [amenity('tv', 'Smart TV'), amenity('jacuzzi', 'Jacuzzi')],
        'highlights': [amenity('tv', 'Smart TV')],
      }),
    );

    final tv = room.amenities.first;
    expect(tv.label, 'Smart TV');
    expect(tv.iconData, Icons.tv_outlined);
    expect(room.highlights.single.iconData, Icons.tv_outlined);

    final jacuzzi = room.amenities.last;
    expect(jacuzzi.iconData, isNull);
    expect(jacuzzi.iconPath, 'assets/icons/jacuzzi.svg');
  });
}
