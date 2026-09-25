import 'package:carlton/customWidgets/custom_indicators.dart';
import 'package:carlton/l10n/app_translations.dart';
import 'package:carlton/models/check_in/check_in_enums.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:flutter_svg/svg.dart';
import 'package:get/get.dart';

/// Three-state digital key button (Figma 2237:5322 `Button` component set).
///
/// idle -> activating -> activated. Only `idle` is tappable; the other two are
/// terminal or in-flight, so swallowing the tap is the correct behaviour.
///
/// The design keeps the label ink and the grey fill constant across idle and
/// activating; only `activated` swaps the fill to a pale green wash.
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
    final radius = BorderRadius.circular(10);

    return Material(
      color: activated ? AppColors.successGreen18 : AppColors.pearlSilver,
      borderRadius: radius,
      child: InkWell(
        onTap: status == DigitalKeyStatus.idle ? onPressed : null,
        borderRadius: radius,
        child: Container(
          height: 52,
          decoration: BoxDecoration(
            borderRadius: radius,
            border: Border.all(color: AppColors.white),
          ),
          child: Row(
            mainAxisAlignment: MainAxisAlignment.center,
            spacing: 4,
            children: [
              SvgPicture.asset(
                'assets/icons/chk_phone_key.svg',
                width: 24,
                height: 24,
                colorFilter: const ColorFilter.mode(
                  AppColors.onyxBlack,
                  BlendMode.srcIn,
                ),
              ),
              Text(
                label,
                style: Get.textTheme.titleSmall?.copyWith(
                  letterSpacing: 0.3,
                  height: 21 / 14,
                  color: AppColors.onyxBlack,
                ),
              ),
              if (status == DigitalKeyStatus.activating)
                // AppColors.onyxBlack, not the indicator's default white logo —
                // the activating state keeps the light pearlSilver fill, where
                // a white mark would be invisible.
                const SpinningIconIndicator(
                  size: 16,
                  color: AppColors.onyxBlack,
                ),
              if (activated)
                SvgPicture.asset(
                  'assets/icons/check_circle.svg',
                  width: 24,
                  height: 24,
                  colorFilter: const ColorFilter.mode(
                    AppColors.onyxBlack,
                    BlendMode.srcIn,
                  ),
                ),
            ],
          ),
        ),
      ),
    );
  }
}
