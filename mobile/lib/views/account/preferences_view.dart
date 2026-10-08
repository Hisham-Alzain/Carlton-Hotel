import 'package:carlton/l10n/app_translations.dart';
import 'package:carlton/components/account/custom_dropdown_field.dart';
import 'package:carlton/components/account/custom_list_row.dart';
import 'package:carlton/components/account/custom_settings_section.dart';
import 'package:carlton/constants/preference_options.dart';
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
        title: Text(AppTranslations.checkInTabPreferences),
        iconTheme: const IconThemeData(color: AppColors.primary),
      ),
      body: Obx(
        () => ListView(
          padding: const EdgeInsets.all(16),
          children: [
            Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              spacing: 24,
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
                CustomSettingsSection(
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
                      label: AppTranslations.floorLabel,
                      value: controller.floorLabel,
                      options: PreferenceOptions.floorOptions,
                      selectedId: controller.floorId.value,
                      onSelected: controller.chooseFloor,
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
                CustomSettingsSection(
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
              ],
            ),
          ],
        ),
      ),
    );
  }

  Widget _switch(bool value, ValueChanged<bool> onChanged) => Switch(
    value: value,
    onChanged: onChanged,
    activeThumbColor: AppColors.white,
    activeTrackColor: AppColors.primary,
  );
}
