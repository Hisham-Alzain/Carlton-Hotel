import 'package:carlton/components/account/custom_dropdown_field.dart';
import 'package:carlton/components/custom_toggle_tile.dart';
import 'package:carlton/constants/demo_data.dart';
import 'package:carlton/controllers/check_in/check_in_controller.dart';
import 'package:carlton/customWidgets/custom_filled_button.dart';
import 'package:carlton/customWidgets/custom_text_field.dart';
import 'package:carlton/l10n/app_translations.dart';
import 'package:carlton/models/preference_option.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';

/// Preferences tab (Figma 75:808).
///
/// The three dropdowns reuse the account dropdown and the existing DemoData
/// option lists verbatim; only the toggle tile is new to this feature.
class PreferencesTab extends GetView<CheckInController> {
  const PreferencesTab({super.key});

  String _labelFor(List<PreferenceOption> options, String id) =>
      options.firstWhere((o) => o.id == id, orElse: () => options.first).label;

  @override
  Widget build(BuildContext context) {
    return SingleChildScrollView(
      padding: const EdgeInsets.all(20),
      child: Obx(() {
        final prefs = controller.service.preferences.value;
        return Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          spacing: 15,
          children: [
            CustomDropdownField(
              label: AppTranslations.bedType,
              value: _labelFor(DemoData.bedOptions, prefs.bedTypeId),
              options: DemoData.bedOptions,
              selectedId: prefs.bedTypeId,
              onSelected: (o) => controller.service.preferences.value = prefs
                  .copyWith(bedTypeId: o.id),
            ),
            CustomDropdownField(
              label: AppTranslations.pillowLabel,
              value: _labelFor(DemoData.pillowOptions, prefs.pillowId),
              options: DemoData.pillowOptions,
              selectedId: prefs.pillowId,
              onSelected: (o) => controller.service.preferences.value = prefs
                  .copyWith(pillowId: o.id),
            ),
            CustomDropdownField(
              label: AppTranslations.mattressType,
              value: _labelFor(DemoData.mattressOptions, prefs.mattressId),
              options: DemoData.mattressOptions,
              selectedId: prefs.mattressId,
              onSelected: (o) => controller.service.preferences.value = prefs
                  .copyWith(mattressId: o.id),
            ),
            Text(
              AppTranslations.roomType,
              style: Get.textTheme.titleSmall?.copyWith(
                color: AppColors.primary,
              ),
            ),
            CustomToggleTile(
              title: AppTranslations.smokingRoom,
              subtitle: AppTranslations.smokingRoomSubtitle,
              value: prefs.smokingRoom,
              onChanged: (v) => controller.service.preferences.value = prefs
                  .copyWith(smokingRoom: v),
            ),
            CustomToggleTile(
              title: AppTranslations.earlyCheckIn,
              subtitle: AppTranslations.earlyCheckInSubtitle,
              value: prefs.earlyCheckIn,
              onChanged: (v) => controller.service.preferences.value = prefs
                  .copyWith(earlyCheckIn: v),
            ),
            CustomToggleTile(
              title: AppTranslations.lateCheckOut,
              subtitle: AppTranslations.lateCheckOutSubtitle,
              value: prefs.lateCheckOut,
              onChanged: (v) => controller.service.preferences.value = prefs
                  .copyWith(lateCheckOut: v),
            ),
            CustomToggleTile(
              title: AppTranslations.extraPillows,
              subtitle: AppTranslations.extraPillowsSubtitle,
              value: prefs.extraPillows,
              onChanged: (v) => controller.service.preferences.value = prefs
                  .copyWith(extraPillows: v),
            ),
            Text(
              AppTranslations.specialRequestsTitle,
              style: Get.textTheme.titleSmall?.copyWith(
                color: AppColors.primary,
              ),
            ),
            CustomTextField(
              controller: controller.notesController,
              textInputType: TextInputType.multiline,
              hintText: AppTranslations.specialRequestsHint,
              maxLines: 4,
              fillColor: AppColors.primary06,
            ),
            CustomFilledButton(
              height: 50,
              onPressed: controller.savePreferencesAndAdvance,
              backgroundColor: AppColors.lagoonTeal,
              child: Text(AppTranslations.continueLabel),
            ),
          ],
        );
      }),
    );
  }
}
