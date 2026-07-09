import 'package:get_storage/get_storage.dart';

class StorageService {
  static GetStorage? _box;

  /// Call this once at app startup (before runApp).
  static Future<void> init() async {
    await GetStorage.init();
    _box = GetStorage();
  }

  static GetStorage get _getBox {
    _box ??= GetStorage();
    return _box!;
  }

  // --- String ---
  static String? getString(String key) => _getBox.read<String?>(key);
  static Future<void> setString(String key, String value) async =>
      await _getBox.write(key, value);

  // --- Bool ---
  static bool? getBool(String key) => _getBox.read<bool?>(key);
  static Future<void> setBool(String key, bool value) async =>
      await _getBox.write(key, value);

  // --- Int / Double / dynamic helpers (optional) ---
  static int? getInt(String key) => _getBox.read<int?>(key);
  static Future<void> setInt(String key, int value) async =>
      await _getBox.write(key, value);

  static double? getDouble(String key) => _getBox.read<double?>(key);
  static Future<void> setDouble(String key, double value) async =>
      await _getBox.write(key, value);

  // --- Generic read/write ---
  static T? read<T>(String key) => _getBox.read<T?>(key);
  static Future<void> write(String key, dynamic value) async =>
      await _getBox.write(key, value);

  // --- Removal / Clear ---
  static Future<void> remove(String key) async => await _getBox.remove(key);
  static Future<void> clear() async => await _getBox.erase();

  // --- Utility ---
  static bool containsKey(String key) => _getBox.hasData(key);
  static List<String> getAllKeys() => _getBox.getKeys().cast<String>().toList();
}
