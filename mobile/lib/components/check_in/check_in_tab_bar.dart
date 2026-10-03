import 'package:carlton/l10n/app_translations.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';

/// Wizard progress strip (Figma 2237:4567 / 2237:4912 / 2237:5032).
///
/// Not a TabBar — see CheckInController for why. Locked tabs render muted and
/// swallow taps rather than being removed, so the guest can see what is coming.
///
/// Takes [furthestIndex] as a plain int rather than an `isUnlocked` callback on
/// purpose. A callback would read `furthestTab.value` during THIS widget's
/// build — outside the caller's `Obx` builder — so GetX would register no
/// subscription and a change to furthestTab alone could not repaint the strip,
/// leaving completed tabs rendered locked and swallowing back-navigation taps.
/// Passing the value forces the read to happen inside the Obx.
class CheckInTabBar extends StatelessWidget {
  final int activeIndex;
  final int furthestIndex;
  final ValueChanged<int> onTap;

  const CheckInTabBar({
    required this.activeIndex,
    required this.furthestIndex,
    required this.onTap,
    super.key,
  });

  @override
  Widget build(BuildContext context) {
    final labels = <String>[
      AppTranslations.checkInTabIdentity,
      AppTranslations.checkInTabPreferences,
      AppTranslations.checkInTabRoomKey,
    ];

    return Row(
      children: List<Widget>.generate(labels.length, (index) {
        final active = index == activeIndex;
        final unlocked = index <= furthestIndex;
        return Expanded(
          child: InkWell(
            onTap: unlocked ? () => onTap(index) : null,
            child: Padding(
              padding: const EdgeInsets.only(top: 10),
              child: Column(
                spacing: 10,
                children: [
                  Text(
                    labels[index],
                    textAlign: TextAlign.center,
                    style: Get.textTheme.titleSmall?.copyWith(
                      color: active
                          ? AppColors.primary
                          : unlocked
                          ? AppColors.mediumGrey
                          : AppColors.silverGrey,
                      fontWeight: active ? FontWeight.w700 : FontWeight.w500,
                    ),
                  ),
                  Container(
                    height: 2,
                    color: active ? AppColors.primary : AppColors.iceBlue,
                  ),
                ],
              ),
            ),
          ),
        );
      }),
    );
  }
}
