part of 'scan_id_view.dart';

/// The live preview inside the green framing brackets. `scanning` is the brief
/// window between the shutter and the file landing on disk.
class _CameraCaptureArea extends GetView<ScanIdController> {
  final bool scanning;
  final bool initializing;

  const _CameraCaptureArea({
    required this.scanning,
    required this.initializing,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      color: AppColors.abyssTeal,
      padding: const EdgeInsets.all(20),
      margin: const EdgeInsets.only(top: 10),
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        spacing: 20,
        children: [
          // ID-1 card ratio (85.6 × 54mm). Framing to the document rather than
          // the sensor is what makes the guest line the ID up correctly.
          AspectRatio(
            aspectRatio: 85.6 / 54,
            child: ClipRRect(
              borderRadius: BorderRadius.circular(10),
              child: Stack(
                fit: StackFit.expand,
                children: [
                  if (initializing)
                    const _CameraLoadingPlaceholder()
                  else
                    Obx(() {
                      final CameraController? cam =
                          controller.cameraController.value;
                      if (cam == null || !cam.value.isInitialized) {
                        return const _CameraLoadingPlaceholder();
                      }
                      // The sensor is never card-shaped: cover the frame and
                      // let the sides crop, rather than letterboxing.
                      return FittedBox(
                        fit: BoxFit.cover,
                        clipBehavior: Clip.hardEdge,
                        child: SizedBox(
                          width: cam.value.previewSize?.height ?? 1,
                          height: cam.value.previewSize?.width ?? 1,
                          child: CameraPreview(cam),
                        ),
                      );
                    }),
                  const _IdFrameBrackets(),
                  if (!scanning && !initializing) const _SweepingScanLine(),
                  if (scanning)
                    ColoredBox(
                      color: AppColors.abyssTeal.withValues(alpha: 0.55),
                      child: const Center(
                        child: LogoLoadingIndicator(
                          size: 50,
                          color: AppColors.successGreen,
                          shadowColor: AppColors.successGreen,
                        ),
                      ),
                    ),
                ],
              ),
            ),
          ),
          if (scanning) ...[
            Text(
              AppTranslations.scanningLabel,
              style: Get.textTheme.titleMedium?.copyWith(
                color: AppColors.cream,
              ),
            ),
            Text(
              AppTranslations.dontMoveId,
              style: Get.textTheme.bodySmall?.copyWith(
                color: AppColors.cream60,
              ),
            ),
          ] else if (initializing)
            Text(
              AppTranslations.startingCamera,
              style: Get.textTheme.bodySmall?.copyWith(
                color: AppColors.cream60,
              ),
            ),
        ],
      ),
    );
  }
}

/// Stands in for the preview texture before the camera is live, so the frame
/// does not flash empty black on entry.
class _CameraLoadingPlaceholder extends StatelessWidget {
  const _CameraLoadingPlaceholder();

  @override
  Widget build(BuildContext context) {
    return const ColoredBox(
      color: AppColors.slateTeal,
      child: Center(
        child: LogoLoadingIndicator(
          size: 50,
          color: AppColors.cream60,
          shadowColor: AppColors.cream60,
        ),
      ),
    );
  }
}

/// The four green corner brackets from Figma 75:653.
class _IdFrameBrackets extends StatelessWidget {
  const _IdFrameBrackets();

  @override
  Widget build(BuildContext context) {
    return IgnorePointer(
      child: Padding(
        padding: const EdgeInsets.all(12),
        child: Stack(
          children: <Widget>[
            for (final Alignment corner in <Alignment>[
              Alignment.topLeft,
              Alignment.topRight,
              Alignment.bottomLeft,
              Alignment.bottomRight,
            ])
              Align(
                alignment: corner,
                child: _FrameCornerBracket(corner: corner),
              ),
          ],
        ),
      ),
    );
  }
}

class _FrameCornerBracket extends StatelessWidget {
  final Alignment corner;

  const _FrameCornerBracket({required this.corner});

  @override
  Widget build(BuildContext context) {
    const BorderSide side = BorderSide(color: AppColors.successGreen, width: 3);
    final bool top = corner.y < 0;
    final bool left = corner.x < 0;

    return SizedBox(
      width: 34,
      height: 34,
      child: DecoratedBox(
        decoration: BoxDecoration(
          border: Border(
            top: top ? side : BorderSide.none,
            bottom: top ? BorderSide.none : side,
            left: left ? side : BorderSide.none,
            right: left ? BorderSide.none : side,
          ),
        ),
      ),
    );
  }
}

/// The sweeping aim line. Driven by the controller's ticker so this stays a
/// GetView rather than a StatefulWidget.
class _SweepingScanLine extends GetView<ScanIdController> {
  const _SweepingScanLine();

  @override
  Widget build(BuildContext context) {
    return IgnorePointer(
      child: AnimatedBuilder(
        animation: controller.scanLineAnimation,
        builder: (context, _) => Align(
          alignment: Alignment(0, controller.scanLineAnimation.value * 2 - 1),
          child: Container(
            height: 2,
            margin: const EdgeInsets.symmetric(horizontal: 20),
            color: AppColors.successGreen,
          ),
        ),
      ),
    );
  }
}

class _CameraControlsRow extends GetView<ScanIdController> {
  const _CameraControlsRow();

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.all(20),
      child: Obx(() {
        final bool ready = controller.stage.value == ScanStage.framing;
        return Row(
          mainAxisAlignment: MainAxisAlignment.spaceEvenly,
          children: [
            _CameraControlButton(
              asset: 'assets/icons/chk_flash.svg',
              label: AppTranslations.flashLabel,
              // Disabled on lenses with no torch, so the control never lies
              // about what tapping it will do.
              onTap: ready && controller.deviceHasTorch
                  ? controller.toggleTorch
                  : null,
              highlighted: controller.isTorchOn.value,
            ),
            _CameraControlButton(
              asset: 'assets/icons/chk_id_card.svg',
              label: AppTranslations.scanLabel,
              onTap: ready ? controller.capturePhoto : null,
              highlighted: true,
            ),
            _CameraControlButton(
              asset: 'assets/icons/chk_upload.svg',
              label: AppTranslations.uploadLabel,
              onTap: controller.openUpload,
            ),
          ],
        );
      }),
    );
  }
}

class _CameraControlButton extends StatelessWidget {
  final String asset;
  final String label;
  final VoidCallback? onTap;
  final bool highlighted;

  const _CameraControlButton({
    required this.asset,
    required this.label,
    required this.onTap,
    this.highlighted = false,
  });

  @override
  Widget build(BuildContext context) {
    return Opacity(
      opacity: onTap == null ? 0.4 : 1,
      child: InkWell(
        onTap: onTap,
        child: Column(
          spacing: 10,
          children: [
            CustomIconChip.circle(
              size: 50,
              backgroundColor: highlighted
                  ? AppColors.lagoonTeal
                  : AppColors.pearlCream,
              // The Figma SVGs carry their own stroke colours (white on the
              // teal Scan button, grey on the two flanking ones), so no tint.
              child: SvgPicture.asset(asset, width: 22),
            ),
            Text(
              label,
              style: Get.textTheme.bodySmall?.copyWith(
                color: highlighted
                    ? AppColors.lagoonTeal
                    : AppColors.taupeBrown,
              ),
            ),
          ],
        ),
      ),
    );
  }
}
