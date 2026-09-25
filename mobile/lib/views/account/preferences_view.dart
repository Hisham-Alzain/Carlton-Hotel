import 'package:carlton/l10n/app_translations.dart';
import 'package:carlton/constants/preference_options.dart';
import 'package:carlton/components/account/custom_dropdown_field.dart';
import 'package:carlton/components/account/custom_list_row.dart';
import 'package:carlton/components/account/custom_settings_section.dart';
import 'package:carlton/controllers/account/preferences_controller.dart';
import 'package:carlton/customWidgets/custom_scaffold.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';

class PreferencesView extends GetView<PreferencesController> {
  const PreferencesView({super.key});

  @override
  Widget build(BuildContext context) {
    return CustomScaffold(
      appBar: AppBar(
        title: Text(AppTranslations.preferences),
        iconTheme: const IconThemeData(color: AppColors.primary),
      ),
      // SingleChildScrollView + Column rather than ListView: ListView has no
      // `spacing:`. `stretch` is load-bearing — ListView sized its children to
      // full width for free, and a Column centres them instead, which would
      // shrink both dropdowns and every settings card.
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(16),
        // Outer 28 separates the page's three blocks; the inner 20 is the
        // tighter pairing between the two standalone dropdowns. Each block is
        // its own Obx so a change in one section never rebuilds the others.
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          spacing: 28,
          children: [
            Obx(
              () => Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.stretch,
                spacing: 20,
                children: [
                  CustomDropdownField(
                    label: AppTranslations.language,
                    value: controller.languageLabel,
                    options: PreferenceOptions.languageOptions,
                    selectedId: controller.languageId,
                    onSelected: controller.chooseLanguage,
                  ),
                  CustomDropdownField(
                    label: AppTranslations.currency,
                    value: controller.currencyLabel,
                    options: PreferenceOptions.currencyOptions,
                    selectedId: controller.currencyId,
                    onSelected: controller.chooseCurrency,
                  ),
                ],
              ),
            ),
            Obx(
              () => CustomSettingsSection(
                title: AppTranslations.stayPreferences,
                children: [
                  CustomDropdownField(
                    label: AppTranslations.bedType,
                    value: controller.bedLabel,
                    options: PreferenceOptions.bedOptions,
                    selectedId: controller.bedId.value,
                    onSelected: controller.chooseBed,
                  ),
                  CustomDropdownField(
                    label: AppTranslations.pillowLabel,
                    value: controller.pillowLabel,
                    options: PreferenceOptions.pillowOptions,
                    selectedId: controller.pillowId.value,
                    onSelected: controller.choosePillow,
                  ),
                  CustomDropdownField(
                    label: AppTranslations.mattressType,
                    value: controller.mattressLabel,
                    options: PreferenceOptions.mattressOptions,
                    selectedId: controller.mattressId.value,
                    onSelected: controller.chooseMattress,
                  ),
                ],
              ),
            ),
            Obx(
              () => CustomSettingsSection(
                title: AppTranslations.roomType,
                children: [
                  CustomListRow(
                    title: AppTranslations.smokingRoom,
                    subtitle: AppTranslations.smokingRoomSubtitle,
                    trailing: _switch(
                      controller.smoking.value,
                      controller.toggleSmoking,
                    ),
                  ),
                  CustomListRow(
                    title: AppTranslations.earlyCheckIn,
                    subtitle: AppTranslations.earlyCheckInSubtitle,
                    trailing: _switch(
                      controller.earlyCheckIn.value,
                      controller.toggleEarlyCheckIn,
                    ),
                  ),
                  CustomListRow(
                    title: AppTranslations.lateCheckOut,
                    subtitle: AppTranslations.lateCheckOutSubtitle,
                    trailing: _switch(
                      controller.lateCheckout.value,
                      controller.toggleLateCheckout,
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

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
}
