import 'package:carlton/components/check_in/check_in_tab_bar.dart';
import 'package:carlton/controllers/check_in/check_in_controller.dart';
import 'package:carlton/customWidgets/custom_scaffold.dart';
import 'package:carlton/l10n/app_translations.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:carlton/views/check_in/tabs/identity_tab.dart';
import 'package:carlton/views/check_in/tabs/preferences_tab.dart';
import 'package:carlton/views/check_in/tabs/room_key_tab.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';

/// Three-tab check-in wizard (Figma 2237:4567, 2237:4912, 2237:5032).
///
/// The PageView is deliberately non-swipeable: progression is owned by the
/// Continue buttons so a guest can't skip verification by swiping.
class CheckInView extends GetView<CheckInController> {
  const CheckInView({super.key});

  @override
  Widget build(BuildContext context) {
    return CustomScaffold(
      backgroundColor: AppColors.ghostWhite,
      appBar: AppBar(
        backgroundColor: AppColors.ghostWhite,
        elevation: 0,
        leading: const BackButton(color: AppColors.inkBlack),
        title: Text(
          AppTranslations.checkInTitle,
          style: Get.textTheme.titleLarge?.copyWith(color: AppColors.inkBlack),
        ),
      ),
      body: Column(
        children: [
          // Both reactive values are read HERE, inside the Obx, so either one
          // changing repaints the strip.
          Obx(
            () => CheckInTabBar(
              activeIndex: controller.activeTab.value,
              furthestIndex: controller.furthestTab.value,
              onTap: controller.goToTab,
            ),
          ),
          Expanded(
            child: PageView(
              controller: controller.pageController,
              physics: const NeverScrollableScrollPhysics(),
              onPageChanged: (i) => controller.activeTab.value = i,
              children: const [IdentityTab(), PreferencesTab(), RoomKeyTab()],
            ),
          ),
        ],
      ),
    );
  }
}
