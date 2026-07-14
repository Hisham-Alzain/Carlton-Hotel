// import 'dart:io';
// import 'package:carlton/customWidgets/custom_dialogs.dart';
// import 'package:carlton/l10n/app_translations.dart';
// import 'package:carlton/models/picked_file.dart';
// import 'package:carlton/services/permission_service.dart';
// import 'package:file_picker/file_picker.dart';
// import 'package:flutter/foundation.dart';
// import 'package:get/get.dart';
// import 'package:path_provider/path_provider.dart';

// NOTE: entire service disabled pending the file_picker v12 API migration —
// re-enable and update the picker calls when file upload/download ships.

// class FileService extends GetxService {
//   late final PermissionService permissionService;

//   @override
//   onInit() {
//     permissionService = Get.find<PermissionService>();
//     super.onInit();
//   }

//   // Check if a file exists at the given path
//   Future<bool> fileExists(String filePath) async {
//     try {
//       final file = File(filePath);
//       return await file.exists();
//     } catch (e) {
//       CustomDialogs.showErrorDialog(
//         message: '${AppTranslations.errorCheckingFile}: $e',
//       );
//       return false;
//     }
//   }

//   // Pick a file using file_picker
//   Future<File?> pickFile({
//     List<String>? allowedExtensions,
//     FileType type = FileType.any,
//   }) async {
//     try {
//       // Request file permission first
//       final hasPermission = await permissionService.requestFilesPermission();
//       if (!hasPermission) {
//         CustomDialogs.showErrorDialog(
//           message: AppTranslations.permissionDenied,
//         );
//         return null;
//       }

//       // 👈 CHANGED: Removed .platform
//       FilePickerResult? result = await FilePicker.pickFiles(
//         type: type,
//         allowedExtensions: allowedExtensions,
//       );

//       if (result != null && result.files.single.path != null) {
//         return File(result.files.single.path!);
//       }
//       return null;
//     } catch (e) {
//       CustomDialogs.showErrorDialog(
//         message: '${AppTranslations.errorPickingFile}: $e',
//       );
//       return null;
//     }
//   }

//   Future<PickedFile?> pickFileCrossPlatform({
//     List<String>? allowedExtensions,
//     FileType type = FileType.any,
//   }) async {
//     try {
//       if (!kIsWeb) {
//         final hasPermission = await permissionService.requestFilesPermission();
//         if (!hasPermission) {
//           CustomDialogs.showErrorDialog(
//             message: AppTranslations.permissionDenied,
//           );
//           return null;
//         }
//       }

//       // 👈 CHANGED: Removed .platform
//       FilePickerResult? result = await FilePicker.pickFiles(
//         type: type,
//         allowedExtensions: allowedExtensions,
//         withData: kIsWeb,
//       );

//       if (result == null) return null;

//       final f = result.files.single;
//       return kIsWeb
//           ? PickedFile(name: f.name, bytes: f.bytes)
//           : PickedFile(name: f.name, path: f.path);
//     } catch (e) {
//       CustomDialogs.showErrorDialog(
//         message: '${AppTranslations.errorPickingFile}: $e',
//       );
//       return null;
//     }
//   }

//   // Pick multiple files
//   Future<List<File>?> pickMultipleFiles({
//     List<String>? allowedExtensions,
//     FileType type = FileType.any,
//   }) async {
//     try {
//       // Request file permission first
//       final hasPermission = await permissionService.requestFilesPermission();
//       if (!hasPermission) {
//         CustomDialogs.showErrorDialog(
//           message: AppTranslations.permissionDenied,
//         );
//         return null;
//       }

//       // 👈 CHANGED: Removed .platform
//       FilePickerResult? result = await FilePicker.pickFiles(
//         type: type,
//         allowedExtensions: allowedExtensions,
//         allowMultiple: true,
//       );

//       if (result != null) {
//         return result.paths
//             .where((path) => path != null)
//             .map((path) => File(path!))
//             .toList();
//       }
//       return null;
//     } catch (e) {
//       CustomDialogs.showErrorDialog(
//         message: '${AppTranslations.errorPickingMultipleFiles}: $e',
//       );
//       return null;
//     }
//   }

//   Future<List<PickedFile>?> pickMultipleFilesCrossPlatform({
//     List<String>? allowedExtensions,
//     FileType type = FileType.any,
//   }) async {
//     try {
//       // Skip permission check on web — not needed
//       if (!kIsWeb) {
//         final hasPermission = await permissionService.requestFilesPermission();
//         if (!hasPermission) {
//           CustomDialogs.showErrorDialog(
//             message: AppTranslations.permissionDenied,
//           );
//           return null;
//         }
//       }

//       // 👈 CHANGED: Removed .platform
//       FilePickerResult? result = await FilePicker.pickFiles(
//         type: type,
//         allowedExtensions: allowedExtensions,
//         allowMultiple: true,
//         withData: kIsWeb, // 👈 on web, load bytes directly
//       );

//       if (result == null) return null;

//       return result.files.map((f) {
//         if (kIsWeb) {
//           return PickedFile(name: f.name, bytes: f.bytes);
//         } else {
//           return PickedFile(name: f.name, path: f.path);
//         }
//       }).toList();
//     } catch (e) {
//       CustomDialogs.showErrorDialog(
//         message: '${AppTranslations.errorPickingMultipleFiles}: $e',
//       );
//       return null;
//     }
//   }

//   // Save a file with user-selected location
//   Future<String?> saveFile({
//     required String fileName,
//     required Uint8List data,
//   }) async {
//     try {
//       // Request file permission first
//       final hasPermission = await permissionService.requestFilesPermission();
//       if (!hasPermission) {
//         CustomDialogs.showErrorDialog(
//           message: AppTranslations.permissionDenied,
//         );
//         return null;
//       }

//       // 👈 CHANGED: Removed .platform
//       String? outputFile = await FilePicker.saveFile(fileName: fileName);

//       if (outputFile != null) {
//         final file = File(outputFile);
//         await file.writeAsBytes(data);
//         return outputFile;
//       }
//       return null;
//     } catch (e) {
//       CustomDialogs.showErrorDialog(
//         message: '${AppTranslations.errorSavingFile}: $e',
//       );
//       return null;
//     }
//   }

//   // Read file as bytes
//   Future<Uint8List?> readFileAsBytes(String filePath) async {
//     try {
//       final file = File(filePath);
//       if (await file.exists()) {
//         return await file.readAsBytes();
//       }
//       CustomDialogs.showErrorDialog(message: AppTranslations.fileNotFound);
//       return null;
//     } catch (e) {
//       CustomDialogs.showErrorDialog(
//         message: '${AppTranslations.errorReadingFile}: $e',
//       );
//       return null;
//     }
//   }

//   // Read file as string
//   Future<String?> readFileAsString(String filePath) async {
//     try {
//       final file = File(filePath);
//       if (await file.exists()) {
//         return await file.readAsString();
//       }
//       CustomDialogs.showErrorDialog(message: AppTranslations.fileNotFound);
//       return null;
//     } catch (e) {
//       CustomDialogs.showErrorDialog(
//         message: '${AppTranslations.errorReadingFile}: $e',
//       );
//       return null;
//     }
//   }

//   // Write bytes to a file
//   Future<bool> writeFile({
//     required String filePath,
//     required Uint8List data,
//   }) async {
//     try {
//       final file = File(filePath);
//       await file.writeAsBytes(data);
//       return true;
//     } catch (e) {
//       CustomDialogs.showErrorDialog(
//         message: '${AppTranslations.errorWritingFile}: $e',
//       );
//       return false;
//     }
//   }

//   // Write string to a file
//   Future<bool> writeStringToFile({
//     required String filePath,
//     required String content,
//   }) async {
//     try {
//       final file = File(filePath);
//       await file.writeAsString(content);
//       return true;
//     } catch (e) {
//       CustomDialogs.showErrorDialog(
//         message: '${AppTranslations.errorWritingFile}: $e',
//       );
//       return false;
//     }
//   }

//   // Get application documents directory (safe location to store files)
//   Future<String> getAppDocumentsPath() async {
//     final directory = await getApplicationDocumentsDirectory();
//     return directory.path;
//   }

//   // Get temporary directory
//   Future<String> getTemporaryPath() async {
//     final directory = await getTemporaryDirectory();
//     return directory.path;
//   }

//   // Get external storage directory (if available)
//   Future<String?> getExternalStoragePath() async {
//     try {
//       final directory = await getExternalStorageDirectory();
//       return directory?.path;
//     } catch (e) {
//       CustomDialogs.showErrorDialog(
//         message: '${AppTranslations.errorGettingExternalStorage}: $e',
//       );
//       return null;
//     }
//   }

//   // Check if a directory exists
//   Future<bool> directoryExists(String path) async {
//     try {
//       final directory = Directory(path);
//       return await directory.exists();
//     } catch (e) {
//       CustomDialogs.showErrorDialog(
//         message: '${AppTranslations.errorCheckingDirectory}: $e',
//       );
//       return false;
//     }
//   }

//   // Create a directory
//   Future<bool> createDirectory(String path) async {
//     try {
//       final directory = Directory(path);
//       await directory.create(recursive: true);
//       return true;
//     } catch (e) {
//       CustomDialogs.showErrorDialog(
//         message: '${AppTranslations.errorCreatingDirectory}: $e',
//       );
//       return false;
//     }
//   }

//   // List files in a directory
//   Future<List<File>> listFiles(String path, {bool recursive = false}) async {
//     try {
//       final directory = Directory(path);
//       if (await directory.exists()) {
//         return await directory
//             .list(recursive: recursive)
//             .where((entity) => entity is File)
//             .map((entity) => entity as File)
//             .toList();
//       }
//       CustomDialogs.showErrorDialog(message: AppTranslations.directoryNotFound);
//       return [];
//     } catch (e) {
//       CustomDialogs.showErrorDialog(
//         message: '${AppTranslations.errorListingFiles}: $e',
//       );
//       return [];
//     }
//   }

//   // Delete a file
//   Future<bool> deleteFile(String path) async {
//     try {
//       final file = File(path);
//       if (await file.exists()) {
//         await file.delete();
//         return true;
//       }
//       CustomDialogs.showErrorDialog(message: AppTranslations.fileNotFound);
//       return false;
//     } catch (e) {
//       CustomDialogs.showErrorDialog(
//         message: '${AppTranslations.errorDeletingFile}: $e',
//       );
//       return false;
//     }
//   }

//   // Delete a directory
//   Future<bool> deleteDirectory(String path) async {
//     try {
//       final directory = Directory(path);
//       if (await directory.exists()) {
//         await directory.delete(recursive: true);
//         return true;
//       }
//       CustomDialogs.showErrorDialog(message: AppTranslations.directoryNotFound);
//       return false;
//     } catch (e) {
//       CustomDialogs.showErrorDialog(
//         message: '${AppTranslations.errorDeletingDirectory}: $e',
//       );
//       return false;
//     }
//   }

//   // Get file information
//   Future<Map<String, dynamic>?> getFileInfo(String path) async {
//     try {
//       final file = File(path);
//       if (await file.exists()) {
//         final stat = await file.stat();
//         return {
//           'path': path,
//           'size': stat.size,
//           'modified': stat.modified,
//           'accessed': stat.accessed,
//           'changed': stat.changed,
//         };
//       }
//       CustomDialogs.showErrorDialog(message: AppTranslations.fileNotFound);
//       return null;
//     } catch (e) {
//       CustomDialogs.showErrorDialog(
//         message: '${AppTranslations.errorGettingFileInfo}: $e',
//       );
//       return null;
//     }
//   }

//   String mimeFromName(String name) {
//     final ext = name.split('.').last.toLowerCase();
//     const map = {
//       'pdf': 'application/pdf',
//       'doc': 'application/msword',
//       'docx':
//           'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
//       'jpg': 'image/jpeg',
//       'jpeg': 'image/jpeg',
//       'png': 'image/png',
//     };
//     return map[ext] ?? 'application/octet-stream';
//   }
// }
