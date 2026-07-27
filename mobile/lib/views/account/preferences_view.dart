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
      body: GetBuilder<PreferencesController>(
        builder: (c) => ListView(
          padding: const EdgeInsets.all(16),
          children: [
            CustomDropdownField(
              label: 'Language',
              value: c.languageLabel,
              options: DemoData.languageOptions,
              selectedId: c.languageId,
              onSelected: c.chooseLanguage,
            ),
            const SizedBox(height: 20),
            CustomDropdownField(
              label: 'Currency',
              value: c.currencyLabel,
              options: DemoData.currencyOptions,
              selectedId: c.currencyId,
              onSelected: c.chooseCurrency,
            ),
            const SizedBox(height: 28),
            CustomSettingsSection(
              title: 'Stay Preferences',
              children: [
                CustomDropdownField(
                  label: 'Bed Type',
                  value: c.bedLabel,
                  options: DemoData.bedOptions,
                  selectedId: c.bedId,
                  onSelected: c.chooseBed,
                ),
                CustomDropdownField(
                  label: 'Pillow',
                  value: c.pillowLabel,
                  options: DemoData.pillowOptions,
                  selectedId: c.pillowId,
                  onSelected: c.choosePillow,
                ),
                CustomDropdownField(
                  label: 'Mattress Type',
                  value: c.mattressLabel,
                  options: DemoData.mattressOptions,
                  selectedId: c.mattressId,
                  onSelected: c.chooseMattress,
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
                  trailing: _switch(c.smoking, c.toggleSmoking),
                ),
                CustomListRow(
                  title: 'Early Check-in',
                  subtitle: 'Request early check-in when available',
                  trailing: _switch(c.earlyCheckIn, c.toggleEarlyCheckIn),
                ),
                CustomListRow(
                  title: 'Late Check-out',
                  subtitle: 'Request late check-out when available',
                  trailing: _switch(c.lateCheckout, c.toggleLateCheckout),
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
