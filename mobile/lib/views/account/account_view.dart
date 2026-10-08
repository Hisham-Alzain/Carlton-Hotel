import 'package:carlton/l10n/app_translations.dart';
import 'package:carlton/components/account/account_profile_card.dart';
import 'package:carlton/components/account/custom_list_row.dart';
import 'package:carlton/components/account/custom_settings_section.dart';
import 'package:carlton/controllers/account/account_controller.dart';
import 'package:carlton/customWidgets/custom_filled_button.dart';
import 'package:carlton/customWidgets/custom_scaffold.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:carlton/customWidgets/custom_texts.dart';
import 'package:carlton/services/middleware_service.dart';
import 'package:carlton/customWidgets/custom_empty_placeholder.dart';
import 'package:carlton/routes/routes.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';

class AccountView extends GetView<AccountController> {
  const AccountView({super.key});

  @override
  Widget build(BuildContext context) {
    final TextTheme textStyle = Get.textTheme;

    return CustomScaffold(
      // Nothing here belongs to a signed-out visitor, so the whole tab is the
      // sign-in gate until MiddlewareService holds a guest.
      body: Obx(
        () => MiddlewareService.find.isAuthenticated
            ? _content(textStyle)
            : CustomEmptyPlaceholder(
                iconPath: 'assets/images/ring.png',
                iconWidth: 90,
                iconHeight: 65,
                title: AppTranslations.accountSignInPromptTitle,
                primaryLabel: AppTranslations.signInButtonLabel,
                onPrimary: () => Get.toNamed(Routes.signIn),
                secondaryLabel: AppTranslations.createAccountLink,
                onSecondary: () => Get.toNamed(Routes.phoneEntry),
              ),
      ),
    );
  }

  Widget _content(TextTheme textStyle) {
    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          spacing: 24,
          children: [
            // Identity and the Loyalty entry point as one card, not a plain
            // avatar row sitting above a differently-styled one — the membership
            // belongs to this guest, so both live on the single teal-and-gold
            // surface (see AccountProfileCard).
            AccountProfileCard(
              name: controller.name,
              email: controller.email,
              onTapLoyalty: controller.openLoyalty,
            ),
            CustomSettingsSection(
              title: AppTranslations.navAccount,
              children: [
                CustomListRow(
                  iconAsset: 'assets/icons/acc_profile.svg',
                  title: AppTranslations.myProfile,
                  onTap: controller.openProfile,
                ),
                CustomListRow(
                  iconAsset: 'assets/icons/acc_preferences.svg',
                  title: AppTranslations.checkInTabPreferences,
                  onTap: controller.openPreferences,
                ),
                CustomListRow(
                  iconAsset: 'assets/icons/acc_notifications.svg',
                  title: AppTranslations.notifications,
                  onTap: controller.openNotifications,
                ),
                CustomListRow(
                  iconAsset: 'assets/icons/acc_payments.svg',
                  title: AppTranslations.savedPayments,
                  onTap: controller.openSavedPayments,
                ),
                CustomListRow(
                  iconAsset: 'assets/icons/acc_security.svg',
                  title: AppTranslations.security,
                  onTap: controller.openSecurity,
                ),
              ],
            ),
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
            Obx(
              () => TextButton(
                onPressed: controller.deleting.value
                    ? null
                    : controller.confirmDeleteAccount,
                child: Text(
                  AppTranslations.deleteAccount,
                  style: textStyle.labelLarge?.copyWith(
                    color: AppColors.brickRed,
                  ),
                ),
              ),
            ),
          ],
        ),
      ],
    );
  }
}
