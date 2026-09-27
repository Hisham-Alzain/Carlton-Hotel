import 'package:carlton/l10n/app_translations.dart';
import 'package:carlton/components/account/custom_list_row.dart';
import 'package:carlton/components/account/custom_settings_section.dart';
import 'package:carlton/components/custom_initial_avatar.dart';
import 'package:carlton/controllers/account/account_controller.dart';
import 'package:carlton/customWidgets/custom_filled_button.dart';
import 'package:carlton/customWidgets/custom_scaffold.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:carlton/customWidgets/custom_texts.dart';
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
                backgroundColor: AppColors.primary,
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
            title: AppTranslations.navAccount,
            children: [
              CustomListRow(
                iconAsset: 'assets/icons/acc_profile.svg',
                title: AppTranslations.myProfile,
                onTap: controller.editProfile,
              ),
              CustomListRow(
                iconAsset: 'assets/icons/acc_preferences.svg',
                title: AppTranslations.checkInTabPreferences,
                onTap: controller.openPreferences,
              ),
              CustomListRow(
                iconAsset: 'assets/icons/acc_notifications.svg',
                title: AppTranslations.notifications,
                onTap: () =>
                    controller.comingSoon(AppTranslations.notifications),
              ),
              CustomListRow(
                iconAsset: 'assets/icons/acc_payments.svg',
                title: AppTranslations.savedPayments,
                onTap: () =>
                    controller.comingSoon(AppTranslations.savedPayments),
              ),
              CustomListRow(
                iconAsset: 'assets/icons/acc_security.svg',
                title: AppTranslations.security,
                onTap: () => controller.comingSoon(AppTranslations.security),
              ),
            ],
          ),
          const SizedBox(height: 24),
          CustomSettingsSection(
            title: AppTranslations.support,
            children: [
              CustomListRow(
                iconAsset: 'assets/icons/acc_help.svg',
                title: AppTranslations.helpAndSupport,
                onTap: controller.openSupport,
              ),
              CustomListRow(
                iconAsset: 'assets/icons/acc_legal.svg',
                title: AppTranslations.legal,
                onTap: controller.openLegal,
              ),
            ],
          ),
          const SizedBox(height: 28),
          CustomFilledButton(
            width: double.infinity,
            height: 52,
            onPressed: controller.confirmSignOut,
            backgroundColor: AppColors.white,
            foregroundColor: AppColors.brickRed,
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(10),
              side: const BorderSide(color: AppColors.brickRed),
            ),
            textStyle: textStyle.labelLarge?.copyWith(
              fontWeight: FontWeight.w600,
            ),
            child: RowTextComponent(
              text: AppTranslations.signOut,
              icon: Icons.logout,
              iconSize: 18,
              spacing: 6,
            ),
          ),
        ],
      ),
    );
  }
}
