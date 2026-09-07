import 'package:carlton/components/account/custom_dropdown_field.dart';
import 'package:carlton/components/account/custom_list_row.dart';
import 'package:carlton/components/account/custom_settings_section.dart';
import 'package:carlton/constants/demo_data.dart';
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
        title: const Text('Preferences'),
        iconTheme: const IconThemeData(color: AppColors.primary),
      ),
      body: Obx(
        () => ListView(
          padding: const EdgeInsets.all(16),
          children: [
            CustomDropdownField(
              label: 'Language',
              value: controller.languageLabel,
              options: DemoData.languageOptions,
              selectedId: controller.languageId,
              onSelected: controller.chooseLanguage,
            ),
            const SizedBox(height: 20),
            CustomDropdownField(
              label: 'Currency',
              value: controller.currencyLabel,
              options: DemoData.currencyOptions,
              selectedId: controller.currencyId,
              onSelected: controller.chooseCurrency,
            ),
            const SizedBox(height: 28),
            CustomSettingsSection(
              title: 'Stay Preferences',
              children: [
                CustomDropdownField(
                  label: 'Bed Type',
                  value: controller.bedLabel,
                  options: DemoData.bedOptions,
                  selectedId: controller.bedId.value,
                  onSelected: controller.chooseBed,
                ),
                CustomDropdownField(
                  label: 'Pillow',
                  value: controller.pillowLabel,
                  options: DemoData.pillowOptions,
                  selectedId: controller.pillowId.value,
                  onSelected: controller.choosePillow,
                ),
                CustomDropdownField(
                  label: 'Mattress Type',
                  value: controller.mattressLabel,
                  options: DemoData.mattressOptions,
                  selectedId: controller.mattressId.value,
                  onSelected: controller.chooseMattress,
                ),
              ],
            ),
            const SizedBox(height: 28),
            CustomSettingsSection(
              title: 'Room Type',
              children: [
                CustomListRow(
                  title: 'Smoking Room',
                  subtitle: 'Request smoking-permitted room',
                  trailing: _switch(controller.smoking.value, controller.toggleSmoking),
                ),
                CustomListRow(
                  title: 'Early Check-in',
                  subtitle: 'Request early check-in when available',
                  trailing: _switch(controller.earlyCheckIn.value, controller.toggleEarlyCheckIn),
                ),
                CustomListRow(
                  title: 'Late Check-out',
                  subtitle: 'Request late check-out when available',
                  trailing: _switch(controller.lateCheckout.value, controller.toggleLateCheckout),
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
