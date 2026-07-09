import 'dart:io';
import 'package:carlton/customWidgets/custom_dialogs.dart';
import 'package:device_info_plus/device_info_plus.dart';
import 'package:get/get.dart';
import 'package:permission_handler/permission_handler.dart';

/// Service for handling runtime permissions across different platforms
/// Manages permission requests for photos, notifications, and files
class PermissionService extends GetxService {
  static PermissionService get find => Get.find();

  /// Generic permission request handler
  /// Handles the complete permission flow including:
  /// - Already granted permissions
  /// - First-time denials
  /// - Permanently denied permissions (redirects to app settings)
  ///
  /// [permission]: The specific permission to request
  /// Returns: [bool] indicating if permission was granted
  Future<bool> _requestPermission(Permission permission) async {
    final status = await permission.status;

    if (status.isGranted) return true; // Permission already granted
    if (status.isDenied) {
      // First-time denial, request permission
      return await permission.request().isGranted;
    }
    if (status.isPermanentlyDenied) {
      // User permanently denied, redirect to app settings
      await openAppSettings();
      return false; // Return false as we can't guarantee user will grant permission
    }
    return false; // Default case for any other status
  }

  /// Requests location permission
  /// Handles both Android and iOS location access
  ///
  /// Android:
  /// - Uses ACCESS_FINE_LOCATION
  ///
  /// iOS:
  /// - Uses locationWhenInUse permission
  ///
  /// Returns: [bool] indicating if location permission was granted
  Future<bool> requestLocationPermission() async {
    try {
      if (Platform.isIOS) {
        return await _requestPermission(Permission.locationWhenInUse);
      } else if (Platform.isAndroid) {
        return await _requestPermission(Permission.location);
      }
      return false; // Unsupported platform
    } catch (e) {
      CustomDialogs.showErrorDialog(message: e.toString());
      return false;
    }
  }

  /// Requests camera permission
  /// Works for both iOS and Android
  ///
  /// Returns: [bool] indicating if camera permission was granted
  Future<bool> requestCameraPermission() async {
    try {
      if (Platform.isAndroid || Platform.isIOS) {
        return await _requestPermission(Permission.camera);
      }
      return false;
    } catch (e) {
      CustomDialogs.showErrorDialog(message: e.toString());
      return false;
    }
  }

  /// Requests photo library access permission
  /// Handles platform-specific differences:
  /// - iOS: Uses photos permission
  /// - Android: Uses storage permission for SDK <= 32, photos permission for SDK > 32
  ///
  /// Returns: [bool] indicating if photo permission was granted
  Future<bool> requestPhotoPermission() async {
    try {
      if (Platform.isIOS) {
        return await _requestPermission(Permission.photos);
      } else if (Platform.isAndroid) {
        final androidInfo = await DeviceInfoPlugin().androidInfo;
        final permission = androidInfo.version.sdkInt <= 32
            ? Permission
                  .storage // For older Android versions
            : Permission.photos; // For Android 13+ (API 33+)
        return await _requestPermission(permission);
      }
      return false; // Unsupported platform
    } catch (e) {
      CustomDialogs.showErrorDialog(message: e.toString());
      return false;
    }
  }

  /// Requests notification permission
  /// Handles notification permissions for both iOS and Android
  /// Note: Android notification permissions work differently than other permissions
  ///
  /// Returns: [bool] indicating if notification permission was granted
  Future<bool> requestNotificationPermission() async {
    try {
      return await _requestPermission(Permission.notification);
    } catch (e) {
      CustomDialogs.showErrorDialog(message: e.toString());
      return false;
    }
  }

  /// Requests file system access permission
  /// Handles platform and version-specific file access:
  /// - Android 10 (API 29) and below: Uses storage permission
  /// - Android 11-12 (API 30-32): Uses manageExternalStorage for broader access
  /// - Android 13+ (API 33+): Uses manageExternalStorage (note: restricted permission)
  ///
  /// Important: MANAGE_EXTERNAL_STORAGE is a restricted permission on Android
  /// and may require justification for Google Play Store approval
  ///
  /// Returns: [bool] indicating if file permission was granted
  Future<bool> requestFilesPermission() async {
    try {
      if (Platform.isAndroid) {
        final androidInfo = await DeviceInfoPlugin().androidInfo;

        if (androidInfo.version.sdkInt <= 29) {
          // Android 10 and below - storage permission provides file access
          return await _requestPermission(Permission.storage);
        } else if (androidInfo.version.sdkInt <= 32) {
          // Android 11-12 - manageExternalStorage for broader file system access
          return await _requestPermission(Permission.manageExternalStorage);
        } else {
          // Android 13+ - manageExternalStorage (restricted permission)
          return await _requestPermission(Permission.manageExternalStorage);
        }
      }
      // iOS comment: File system access on iOS is typically handled through
      // app sandbox or document pickers rather than broad permissions
      // Uncomment and implement iOS-specific file permission logic if needed
      // else if (Platform.isIOS) {
      //   return await _requestPermission(Permission.storage);
      // }
      return false; // Unsupported platform
    } catch (e) {
      CustomDialogs.showErrorDialog(message: e.toString());
      return false;
    }
  }

  // Useful for checking permissions before attempting operations
  Future<bool> hasFilesPermission() async {
    if (Platform.isAndroid) {
      final androidInfo = await DeviceInfoPlugin().androidInfo;
      if (androidInfo.version.sdkInt <= 29) {
        return await Permission.storage.status.isGranted;
      } else {
        return await Permission.manageExternalStorage.status.isGranted;
      }
    }
    return false;
  }
}
