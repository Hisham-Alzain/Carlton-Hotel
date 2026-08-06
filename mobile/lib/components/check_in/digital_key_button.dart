import 'package:carlton/l10n/app_translations.dart';
import 'package:carlton/models/check_in/check_in_enums.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';

/// Three-state digital key button (Figma 75:1218).
///
/// idle -> activating -> activated. Only `idle` is tappable; the other two are
/// terminal or in-flight, so swallowing the tap is the correct behaviour.
class DigitalKeyButton extends StatelessWidget {
  final DigitalKeyStatus status;
  final VoidCallback onPressed;

  const DigitalKeyButton({
    required this.status,
    required this.onPressed,
    super.key,
  });

  @override
  Widget build(BuildContext context) {
    final activated = status == DigitalKeyStatus.activated;
    final label = switch (status) {
      DigitalKeyStatus.idle => AppTranslations.addDigitalKey,
      DigitalKeyStatus.activating => AppTranslations.activatingDigitalKey,
      DigitalKeyStatus.activated => AppTranslations.activatedDigitalKey,
    };

    return Card(
      color: activated ? AppColors.forestGreen : AppColors.primary06,
      elevation: 0,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
      child: InkWell(
        onTap: status == DigitalKeyStatus.idle ? onPressed : null,
        borderRadius: BorderRadius.circular(10),
        child: Padding(
          padding: const EdgeInsets.symmetric(vertical: 15),
          child: Row(
            mainAxisAlignment: MainAxisAlignment.center,
            spacing: 10,
            children: [
              Icon(
                Icons.smartphone,
                size: 18,
                color: activated ? AppColors.cream : AppColors.primary,
              ),
              Text(
                label,
                style: Get.textTheme.titleSmall?.copyWith(
                  color: activated ? AppColors.cream : AppColors.primary,
                ),
              ),
              if (status == DigitalKeyStatus.activating)
                const SizedBox(
                  width: 16,
                  height: 16,
                  child: CircularProgressIndicator(
                    strokeWidth: 2,
                    color: AppColors.primary,
                  ),
                ),
              if (activated)
                const Icon(
                  Icons.check_circle,
                  color: AppColors.cream,
                  size: 18,
                ),
            ],
          ),
        ),
      ),
    );
  }
}
