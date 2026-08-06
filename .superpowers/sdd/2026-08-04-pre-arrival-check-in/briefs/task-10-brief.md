# Task 10: Scanner view

**Files:**
- Modify: `lib/views/check_in/scan_id_view.dart`
- Test: none new (Task 9 covers the machine; this is presentation)

**Interfaces:**
- Consumes: `ScanIdController`, `DemoData.demoPassportAsset`.

- [ ] **Step 1: Write the view**

```dart
// lib/views/check_in/scan_id_view.dart
import 'package:carlton/constants/demo_data.dart';
import 'package:carlton/controllers/check_in/scan_id_controller.dart';
import 'package:carlton/customWidgets/custom_filled_button.dart';
import 'package:carlton/l10n/app_translations.dart';
import 'package:carlton/models/check_in/check_in_enums.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:flutter_svg/flutter_svg.dart';
import 'package:get/get.dart';

/// One view, three stages (Figma 75:653, 75:703, 75:757).
class ScanIdView extends GetView<ScanIdController> {
  const ScanIdView({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.pearlCream,
      appBar: AppBar(
        backgroundColor: AppColors.pearlCream,
        elevation: 0,
        leading: const BackButton(color: AppColors.primary),
        title: Text(
          AppTranslations.scanYourId,
          style: Get.textTheme.titleLarge
              ?.copyWith(color: AppColors.primary),
        ),
      ),
      body: Obx(() {
        final stage = controller.stage.value;
        return Column(
          children: [
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 20),
              child: Text(
                AppTranslations.positionIdInFrame,
                style: Get.textTheme.bodyMedium
                    ?.copyWith(color: AppColors.taupeBrown),
              ),
            ),
            Expanded(
              child: stage == ScanStage.success
                  ? const _SuccessBody()
                  : _CaptureBody(scanning: stage == ScanStage.scanning),
            ),
            if (stage != ScanStage.success) const _ControlRow(),
          ],
        );
      }),
    );
  }
}

class _CaptureBody extends StatelessWidget {
  final bool scanning;

  const _CaptureBody({required this.scanning});

  @override
  Widget build(BuildContext context) {
    return Container(
      color: AppColors.abyssTeal,
      padding: const EdgeInsets.all(20),
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        spacing: 20,
        children: [
          Stack(
            alignment: Alignment.center,
            children: [
              Image.asset(DemoData.demoPassportAsset),
              // Bracket overlay: four green corners drawn as a border.
              Positioned.fill(
                child: DecoratedBox(
                  decoration: BoxDecoration(
                    border: Border.all(
                      color: AppColors.successGreen,
                      width: 3,
                    ),
                  ),
                ),
              ),
              if (scanning)
                Container(height: 2, color: AppColors.successGreen),
            ],
          ),
          if (scanning) ...[
            const LinearProgressIndicator(
              color: AppColors.successGreen,
              backgroundColor: AppColors.cream20,
            ),
            Text(
              AppTranslations.scanningLabel,
              style: Get.textTheme.titleMedium
                  ?.copyWith(color: AppColors.cream),
            ),
            Text(
              AppTranslations.dontMoveId,
              style: Get.textTheme.bodySmall
                  ?.copyWith(color: AppColors.cream60),
            ),
          ],
        ],
      ),
    );
  }
}

class _SuccessBody extends GetView<ScanIdController> {
  const _SuccessBody();

  @override
  Widget build(BuildContext context) {
    return SingleChildScrollView(
      padding: const EdgeInsets.all(20),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        spacing: 20,
        children: [
          const CircleAvatar(
            radius: 25,
            backgroundColor: AppColors.lagoonTeal,
            child: Icon(Icons.check, color: Colors.white),
          ),
          Text(
            AppTranslations.scanSuccessful,
            textAlign: TextAlign.center,
            style: Get.textTheme.headlineSmall
                ?.copyWith(color: AppColors.primary),
          ),
          Text(
            AppTranslations.capturedIdDetails,
            textAlign: TextAlign.center,
            style: Get.textTheme.bodyMedium
                ?.copyWith(color: AppColors.taupeBrown),
          ),
          Card(
            color: Colors.white,
            elevation: 0,
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(10),
            ),
            child: Padding(
              padding: const EdgeInsets.all(15),
              child: Column(
                spacing: 10,
                children: [
                  Row(
                    children: [
                      Text(
                        AppTranslations.frontOfId,
                        style: Get.textTheme.titleSmall
                            ?.copyWith(color: AppColors.primary),
                      ),
                      const Spacer(),
                      Text(
                        AppTranslations.editLabel,
                        style: Get.textTheme.labelLarge
                            ?.copyWith(color: AppColors.lagoonTeal),
                      ),
                    ],
                  ),
                  Image.asset(DemoData.demoPassportAsset),
                  Text(
                    AppTranslations.makeSureDetailsClear,
                    style: Get.textTheme.bodySmall
                        ?.copyWith(color: AppColors.taupeBrown),
                  ),
                ],
              ),
            ),
          ),
          CustomFilledButton(
            height: 50,
            onPressed: controller.confirm,
            backgroundColor: AppColors.lagoonTeal,
            child: Text(AppTranslations.continueLabel),
          ),
          CustomFilledButton(
            height: 50,
            onPressed: controller.scanAgain,
            backgroundColor: AppColors.primary08,
            foregroundColor: AppColors.primary,
            child: Text(AppTranslations.scanAgain),
          ),
        ],
      ),
    );
  }
}

class _ControlRow extends GetView<ScanIdController> {
  const _ControlRow();

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.all(20),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceEvenly,
        children: [
          // Gap G3: Flash is decorative — there is no camera to light.
          _Control(
            asset: 'assets/icons/chk_flash.svg',
            label: AppTranslations.flashLabel,
            onTap: null,
          ),
          _Control(
            asset: 'assets/icons/chk_id_card.svg',
            label: AppTranslations.scanLabel,
            onTap: controller.startScan,
            highlighted: true,
          ),
          _Control(
            asset: 'assets/icons/chk_upload.svg',
            label: AppTranslations.uploadLabel,
            onTap: controller.openUpload,
          ),
        ],
      ),
    );
  }
}

class _Control extends StatelessWidget {
  final String asset;
  final String label;
  final VoidCallback? onTap;
  final bool highlighted;

  const _Control({
    required this.asset,
    required this.label,
    required this.onTap,
    this.highlighted = false,
  });

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      child: Column(
        spacing: 10,
        children: [
          CircleAvatar(
            radius: 25,
            backgroundColor:
                highlighted ? AppColors.lagoonTeal : AppColors.primary08,
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
    );
  }
}
```

- [ ] **Step 2: Analyze**

Run: `flutter analyze`
Expected: 0 issues

- [ ] **Step 3: Run the full suite**

Run: `flutter test`
Expected: all pass

- [ ] **Step 4: Commit** *(only with authorization)*

```bash
git add lib/views/check_in/scan_id_view.dart
git commit -m "feat(check-in): add mocked scanner view with three stages"
```

---

