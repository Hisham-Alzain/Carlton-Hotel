import 'package:carlton/components/account/custom_list_row.dart';
import 'package:carlton/components/account/custom_settings_section.dart';
import 'package:carlton/components/custom_initial_avatar.dart';
import 'package:carlton/controllers/account/account_controller.dart';
import 'package:carlton/customWidgets/custom_pill_button.dart';
import 'package:carlton/customWidgets/custom_scaffold.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';

class AccountView extends GetView<AccountController> {
  const AccountView({super.key});

  @override
  Widget build(BuildContext context) {
    final TextTheme textStyle = Get.textTheme;

    return CustomScaffold(
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Row(
            spacing: 14,
            children: [
              CustomInitialAvatar(
                initial: controller.name,
                backgroundColor: AppColors.inkBlack,
              ),
              Expanded(
                child: Column(
                  spacing: 4,
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      controller.name,
                      style: textStyle.titleMedium?.copyWith(
                        fontWeight: FontWeight.w700,
                        color: AppColors.primary,
                      ),
                    ),
                    Text(
                      controller.email,
                      style: textStyle.labelMedium?.copyWith(
                        fontFamily: 'DM Sans',
                        color: AppColors.primary50,
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 24),
          CustomSettingsSection(
            title: 'Account',
            children: [
              CustomListRow(
                iconAsset: 'assets/icons/acc_profile.svg',
                title: 'My Profile',
                onTap: () => controller.comingSoon('My Profile'),
              ),
              CustomListRow(
                iconAsset: 'assets/icons/acc_preferences.svg',
                title: 'Preferences',
                onTap: controller.openPreferences,
              ),
              CustomListRow(
                iconAsset: 'assets/icons/acc_notifications.svg',
                title: 'Notifications',
                onTap: () => controller.comingSoon('Notifications'),
              ),
              CustomListRow(
                iconAsset: 'assets/icons/acc_payments.svg',
                title: 'Saved Payments',
                onTap: () => controller.comingSoon('Saved Payments'),
              ),
              CustomListRow(
                iconAsset: 'assets/icons/acc_security.svg',
                title: 'Security',
                onTap: () => controller.comingSoon('Security'),
              ),
            ],
          ),
          const SizedBox(height: 24),
          CustomSettingsSection(
            title: 'Support',
            children: [
              CustomListRow(
                iconAsset: 'assets/icons/acc_help.svg',
                title: 'Help & Support',
                onTap: () => controller.comingSoon('Help & Support'),
              ),
              CustomListRow(
                iconAsset: 'assets/icons/acc_legal.svg',
                title: 'Legal',
                onTap: () => controller.comingSoon('Legal'),
              ),
            ],
          ),
          const SizedBox(height: 28),
          CustomPillButton(
            label: 'Sign Out',
            onTap: controller.confirmSignOut,
            icon: Icons.logout,
            backgroundColor: AppColors.white,
            foregroundColor: AppColors.brickRed,
            borderColor: AppColors.brickRed,
            height: 52,
            expand: true,
            iconSize: 18,
          ),
        ],
      ),
    );
  }
}
