import 'package:carlton/constants/preference_options.dart';
import 'package:carlton/components/account/custom_dropdown_field.dart';
import 'package:carlton/components/account/custom_list_row.dart';
import 'package:carlton/components/account/custom_settings_section.dart';
import 'package:carlton/controllers/check_in/check_in_controller.dart';
import 'package:carlton/customWidgets/custom_filled_button.dart';
import 'package:carlton/customWidgets/custom_text_field.dart';
import 'package:carlton/l10n/app_translations.dart';
import 'package:carlton/models/preference_option.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';

/// Preferences tab (Figma 2237:4912 / G06).
///
/// Uses the same building blocks as `views/account/preferences_view.dart` —
/// [CustomDropdownField] for the three stay choices and
/// [CustomSettingsSection] + [CustomListRow] for the toggle groups — so the
/// two preference screens stay one design, not two that drift apart.
class PreferencesTab extends GetView<CheckInController> {
  const PreferencesTab({super.key});

  String _labelFor(List<PreferenceOption> options, String id) =>
      options.firstWhere((o) => o.id == id, orElse: () => options.first).label;

  /// Figma inverts the Material default: the ON track is the pale tint and the
  /// THUMB carries the brand colour, not the reverse.
  Widget _switch(bool value, ValueChanged<bool> onChanged) => Switch(
    value: value,
    onChanged: onChanged,
    thumbColor: WidgetStateProperty.resolveWith(
      (states) => states.contains(WidgetState.selected)
          ? AppColors.primary
          : AppColors.white,
    ),
    trackColor: WidgetStateProperty.resolveWith(
      (states) => states.contains(WidgetState.selected)
          ? AppColors.frostTeal
          : AppColors.cloudGrey,
    ),
    trackOutlineColor: const WidgetStatePropertyAll(Colors.transparent),
  );

  @override
  Widget build(BuildContext context) {
    return SingleChildScrollView(
      padding: const EdgeInsets.all(20),
      child: Obx(() {
        final prefs = controller.service.preferences.value;
        return Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          spacing: 20,
          children: [
            CustomDropdownField(
              label: AppTranslations.bedType,
              value: _labelFor(PreferenceOptions.bedOptions, prefs.bedTypeId),
              options: PreferenceOptions.bedOptions,
              selectedId: prefs.bedTypeId,
              onSelected: (o) => controller.service.preferences.value = prefs
                  .copyWith(bedTypeId: o.id),
            ),
            CustomDropdownField(
              label: AppTranslations.pillowLabel,
              value: _labelFor(PreferenceOptions.pillowOptions, prefs.pillowId),
              options: PreferenceOptions.pillowOptions,
              selectedId: prefs.pillowId,
              onSelected: (o) => controller.service.preferences.value = prefs
                  .copyWith(pillowId: o.id),
            ),
            CustomDropdownField(
              label: AppTranslations.mattressType,
              value: _labelFor(
                PreferenceOptions.mattressOptions,
                prefs.mattressId,
              ),
              options: PreferenceOptions.mattressOptions,
              selectedId: prefs.mattressId,
              onSelected: (o) => controller.service.preferences.value = prefs
                  .copyWith(mattressId: o.id),
            ),
            CustomSettingsSection(
              title: AppTranslations.roomType,
              children: [
                CustomListRow(
                  title: AppTranslations.smokingRoom,
                  subtitle: AppTranslations.smokingRoomSubtitle,
                  trailing: _switch(
                    prefs.smokingRoom,
                    (v) => controller.service.preferences.value = prefs
                        .copyWith(smokingRoom: v),
                  ),
                ),
                CustomListRow(
                  title: AppTranslations.earlyCheckIn,
                  subtitle: AppTranslations.earlyCheckInSubtitle,
                  trailing: _switch(
                    prefs.earlyCheckIn,
                    (v) => controller.service.preferences.value = prefs
                        .copyWith(earlyCheckIn: v),
                  ),
                ),
                CustomListRow(
                  title: AppTranslations.lateCheckOut,
                  subtitle: AppTranslations.lateCheckOutSubtitle,
                  trailing: _switch(
                    prefs.lateCheckOut,
                    (v) => controller.service.preferences.value = prefs
                        .copyWith(lateCheckOut: v),
                  ),
                ),
                CustomListRow(
                  title: AppTranslations.extraPillows,
                  subtitle: AppTranslations.extraPillowsSubtitle,
                  trailing: _switch(
                    prefs.extraPillows,
                    (v) => controller.service.preferences.value = prefs
                        .copyWith(extraPillows: v),
                  ),
                ),
              ],
            ),
            CustomSettingsSection(
              title: AppTranslations.specialRequestsTitle,
              children: [
                CustomTextField(
                  controller: controller.notesController,
                  textInputType: TextInputType.multiline,
                  hintText: AppTranslations.specialRequestsHint,
                  maxLines: 4,
                  fillColor: AppColors.whisperGrey,
                ),
              ],
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
