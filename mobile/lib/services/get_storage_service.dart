import 'package:carlton/constants/storage_keys.dart';
import 'package:flutter/services.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:get_storage/get_storage.dart';

class StorageService {
  static GetStorage? _box;

  /// Keychain (iOS) / Keystore-wrapped storage (Android). The bearer token
  /// lives here, not in [GetStorage], whose file is plain text.
  static const FlutterSecureStorage _secure = FlutterSecureStorage();

  /// In-memory copy of the token so every request can read it synchronously;
  /// secure storage is async-only. Filled by [loadToken] at startup and kept in
  /// step by [setToken] / [clearToken].
  static String? _token;

  /// Call this once at app startup (before runApp).
  static Future<void> init() async {
    await GetStorage.init();
    _box = GetStorage();
    await loadToken();
  }

  // --- Auth token (secure) ---

  /// The current bearer token, or null when signed out.
  static String? get token => _token;

  /// Reads the token from secure storage into memory. A token left in plain
  /// storage by an older build is moved across once, then deleted from it.
  static Future<void> loadToken() async {
    final legacy = _getBox.read<String?>(StorageKeys.token);
    try {
      _token = await _secure.read(key: StorageKeys.token);
      if (legacy != null && legacy.isNotEmpty) {
        if (_token == null || _token!.isEmpty) {
          await _secure.write(key: StorageKeys.token, value: legacy);
          _token = legacy;
        }
        await _getBox.remove(StorageKeys.token);
      }
    } on PlatformException {
      // The keystore refused (e.g. a restored backup whose key is gone): the
      // guest signs in again rather than the app failing to start.
      _token = legacy;
    } on MissingPluginException {
      // No native side (unit tests): the in-memory copy is all there is.
      _token = legacy;
    }
  }

  static Future<void> setToken(String value) async {
    _token = value;
    try {
      await _secure.write(key: StorageKeys.token, value: value);
    } on PlatformException {
      // Held in memory for this session only; the next launch signs in again.
    } on MissingPluginException {
      // No native side (unit tests).
    }
  }

  static Future<void> clearToken() async {
    _token = null;
    try {
      await _secure.delete(key: StorageKeys.token);
    } on PlatformException {
      // Nothing stored that could be wiped.
    } on MissingPluginException {
      // No native side (unit tests).
    }
    await _getBox.remove(StorageKeys.token);
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
