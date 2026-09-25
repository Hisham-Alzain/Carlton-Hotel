import 'dart:io';

import 'package:camera/camera.dart';
import 'package:carlton/l10n/app_translations.dart';
import 'package:carlton/models/check_in/check_in_enums.dart';
import 'package:carlton/routes/routes.dart';
import 'package:carlton/services/check_in_service.dart';
import 'package:carlton/services/permission_service.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:path_provider/path_provider.dart';
import 'package:permission_handler/permission_handler.dart';

/// Live ID scanner (Figma 2237:4757 / 2237:4807 / 2237:4861).
///
/// Owns a real [CameraController]. Two things about that are load-bearing:
///
/// 1. **Lifecycle.** A camera handle is an OS resource, and Android revokes it
///    when the app backgrounds. Surviving that requires tearing the controller
///    down on pause and rebuilding it on resume — a controller that merely sits
///    there returns to a dead preview texture.
/// 2. **Ordering.** Every `await` here can outlive the route: the guest can pop
///    mid-startup. `isClosed` is re-checked after each one, and a controller
///    built during a stale startup is disposed rather than assigned.
class ScanIdController extends GetxController
    with GetSingleTickerProviderStateMixin, WidgetsBindingObserver {
  final CheckInService service = CheckInService.find;

  final Rx<ScanStage> stage = ScanStage.initializing.obs;

  /// Whether the torch is currently burning.
  final RxBool isTorchOn = false.obs;

  /// Why the camera is unusable, shown on the [ScanStage.unavailable] card.
  final RxString errorMessage = ''.obs;

  /// True when the OS — not a transient fault — is what is blocking us, which
  /// is the only case where sending the guest to app settings helps.
  final RxBool isBlockedByPermission = false.obs;

  /// The captured photo, already copied out of the OS cache. Null until the
  /// guest takes a shot.
  final Rxn<File> capturedPhoto = Rxn<File>();

  /// Drives the sweeping line over the framing brackets. Lives on the
  /// controller rather than a StatefulWidget so the view stays a GetView.
  late final AnimationController scanLineAnimation = AnimationController(
    vsync: this,
    duration: const Duration(milliseconds: 1800),
  )..repeat(reverse: true);

  /// The live camera, or null while stopped. Swapped wholesale by the
  /// lifecycle hook, same shape as [capturedPhoto].
  final Rxn<CameraController> cameraController = Rxn<CameraController>();

  List<CameraDescription> _cameraDevices = const <CameraDescription>[];

  /// Front cameras and some budget rear modules have no torch. Discovered by
  /// the first failed torch attempt, then never retried.
  bool _deviceHasTorch = true;

  /// Guards against overlapping startups — a resume landing on top of an
  /// in-flight startup would leak the first controller.
  bool _isStartingCamera = false;

  bool get isCameraReady =>
      cameraController.value?.value.isInitialized ?? false;
  bool get deviceHasTorch => _deviceHasTorch;

  @override
  void onInit() {
    super.onInit();
    WidgetsBinding.instance.addObserver(this);
    startCamera();
  }

  @override
  void onClose() {
    WidgetsBinding.instance.removeObserver(this);
    scanLineAnimation.dispose();
    // Fire-and-forget: onClose cannot await, and the handle is released either
    // way. Detach first so a late frame cannot touch a disposing controller.
    final CameraController? closing = cameraController.value;
    cameraController.value = null;
    closing?.dispose();
    super.onClose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    // Reviewing a captured photo needs no camera, so leave that stage alone —
    // restarting there would throw the guest back to a live preview.
    if (stage.value == ScanStage.success) return;

    if (state == AppLifecycleState.inactive ||
        state == AppLifecycleState.paused) {
      stopCamera();
    } else if (state == AppLifecycleState.resumed &&
        cameraController.value == null) {
      startCamera();
    }
  }

  /// Releases the hardware handle and rewinds to [ScanStage.initializing] so
  /// the view shows the placeholder rather than a frozen last frame.
  void stopCamera() {
    final CameraController? closing = cameraController.value;
    cameraController.value = null;
    isTorchOn.value = false;
    closing?.dispose();
    if (!isClosed && stage.value == ScanStage.framing) {
      stage.value = ScanStage.initializing;
    }
  }

  /// Permission → device list → initialise. Every failure funnels into
  /// [_showCameraError] so the view has exactly one error path to render.
  Future<void> startCamera() async {
    if (_isStartingCamera) return;
    _isStartingCamera = true;
    try {
      if (!isClosed) {
        stage.value = ScanStage.initializing;
        errorMessage.value = '';
        isBlockedByPermission.value = false;
      }

      final bool isGranted = await PermissionService.find
          .requestCameraPermission();
      if (isClosed) return;
      if (!isGranted) {
        _showCameraError(
          AppTranslations.cameraPermissionDenied,
          blockedByPermission: true,
        );
        return;
      }

      if (_cameraDevices.isEmpty) _cameraDevices = await availableCameras();
      if (isClosed) return;
      if (_cameraDevices.isEmpty) {
        _showCameraError(AppTranslations.cameraNoDevice);
        return;
      }

      final CameraController started = CameraController(
        _cameraDevices.firstWhere(
          (CameraDescription device) =>
              device.lensDirection == CameraLensDirection.back,
          orElse: () => _cameraDevices.first,
        ),
        // High enough that passport MRZ text survives, without the memory cost
        // of max on mid-range Android.
        ResolutionPreset.veryHigh,
        enableAudio: false,
        imageFormatGroup: ImageFormatGroup.jpeg,
      );

      await started.initialize();
      // The guest popped the route (or backgrounded) while we were awaiting:
      // this controller has no owner, so release it instead of leaking it.
      if (isClosed || cameraController.value != null) {
        await started.dispose();
        return;
      }

      cameraController.value = started;
      stage.value = ScanStage.framing;
    } on CameraException catch (error) {
      if (isClosed) return;
      final bool isPermissionError =
          error.code == 'CameraAccessDenied' ||
          error.code == 'CameraAccessDeniedWithoutPrompt' ||
          error.code == 'CameraAccessRestricted';
      _showCameraError(
        isPermissionError
            ? AppTranslations.cameraPermissionDenied
            : AppTranslations.cameraFailed,
        blockedByPermission: isPermissionError,
      );
    } catch (_) {
      if (!isClosed) _showCameraError(AppTranslations.cameraFailed);
    } finally {
      _isStartingCamera = false;
    }
  }

  void _showCameraError(String message, {bool blockedByPermission = false}) {
    errorMessage.value = message;
    isBlockedByPermission.value = blockedByPermission;
    stage.value = ScanStage.unavailable;
  }

  /// Takes the shot and copies it somewhere durable.
  ///
  /// `takePicture` writes into the OS cache directory, which can be purged at
  /// any time — so the file is moved into app documents before anything else
  /// is allowed to hold a reference to it.
  Future<void> capturePhoto() async {
    final CameraController? active = cameraController.value;
    if (active == null ||
        !active.value.isInitialized ||
        active.value.isTakingPicture ||
        stage.value != ScanStage.framing) {
      return;
    }

    stage.value = ScanStage.scanning;
    try {
      final XFile shot = await active.takePicture();
      final Directory documentsDir = await getApplicationDocumentsDirectory();
      final String savedPath =
          '${documentsDir.path}/carlton_id_'
          '${DateTime.now().millisecondsSinceEpoch}.jpg';
      await shot.saveTo(savedPath);
      if (isClosed) return;

      // Drop the previous attempt so repeated re-scans do not pile up files.
      await _deletePreviousPhoto();
      capturedPhoto.value = File(savedPath);
      // The torch is only there to light the document — leaving it burning
      // over the review screen is the classic scanner annoyance.
      await _applyTorch(false);
      if (isClosed) return;
      stage.value = ScanStage.success;
    } on CameraException catch (error) {
      if (isClosed) return;
      stage.value = ScanStage.framing;
      Get.snackbar(
        AppTranslations.cameraCaptureFailed,
        error.description ?? error.code,
      );
    }
  }

  Future<void> toggleTorch() => _applyTorch(!isTorchOn.value);

  Future<void> _applyTorch(bool shouldBeOn) async {
    final CameraController? active = cameraController.value;
    if (active == null || !active.value.isInitialized || !_deviceHasTorch) {
      return;
    }
    try {
      await active.setFlashMode(shouldBeOn ? FlashMode.torch : FlashMode.off);
      if (!isClosed) isTorchOn.value = shouldBeOn;
    } on CameraException {
      // No torch on this lens. Stop offering it rather than failing again.
      _deviceHasTorch = false;
      if (!isClosed) isTorchOn.value = false;
    }
  }

  /// Back to the live preview. The camera survives the review screen, so this
  /// only needs to restart it when a background/resume tore it down.
  Future<void> scanAgain() async {
    await _deletePreviousPhoto();
    capturedPhoto.value = null;
    if (cameraController.value == null) {
      await startCamera();
    } else if (!isClosed) {
      stage.value = ScanStage.framing;
    }
  }

  Future<void> _deletePreviousPhoto() async {
    final File? previous = capturedPhoto.value;
    if (previous == null) return;
    try {
      if (previous.existsSync()) await previous.delete();
    } on FileSystemException {
      // A stale temp file is not worth failing a check-in over.
    }
  }

  /// Promotes the captured document to verified and returns to the Identity
  /// tab, which re-renders in its verified state.
  void confirm() {
    // No OCR/document-verification endpoint exists yet to read the actual
    // scanned document, so the number stays blank rather than a fabricated one.
    service.markIdentityVerified(
      '',
      imagePath: capturedPhoto.value?.path ?? '',
    );
    if (Get.currentRoute == Routes.scanId) Get.back<void>();
  }

  /// Sends the guest to app settings, the only route back from a permanently
  /// denied camera. [startCamera] re-runs on resume via the lifecycle hook.
  Future<void> openSettings() => openAppSettings();

  /// The Upload control reuses the existing, API-wired document upload route
  /// rather than duplicating it.
  void openUpload() => Get.toNamed(Routes.preArrivalDocuments);
}
