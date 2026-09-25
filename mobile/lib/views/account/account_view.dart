import 'package:carlton/l10n/app_translations.dart';
import 'package:carlton/components/account/account_profile_card.dart';
import 'package:carlton/components/account/custom_list_row.dart';
import 'package:carlton/components/account/custom_settings_section.dart';
import 'package:carlton/controllers/account/account_controller.dart';
import 'package:carlton/customWidgets/custom_filled_button.dart';
import 'package:carlton/customWidgets/custom_scaffold.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:flutter_svg/flutter_svg.dart';
import 'package:get/get.dart';

class AccountView extends GetView<AccountController> {
  const AccountView({super.key});

  @override
  Widget build(BuildContext context) {
    final TextTheme textStyle = Get.textTheme;

    return CustomScaffold(
      // SingleChildScrollView + Column rather than ListView: ListView has no
      // `spacing:`. `stretch` is load-bearing — ListView sized its children to
      // full width for free, and a Column would centre them, shrinking the
      // settings cards and the Sign Out button.
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(16),
        // Outer 28 sets the destructive Sign Out button apart; the inner 24 is
        // the rhythm between the profile header and the two settings groups.
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          spacing: 28,
          children: [
            Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.stretch,
              spacing: 24,
              children: [
                // Identity and the Loyalty entry point as one card, not a
                // plain avatar row sitting above a differently-styled one —
                // this membership belongs to this guest, so both live on the
                // single teal-and-gold surface (see AccountProfileCard).
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
                      onTap: () =>
                          controller.comingSoon(AppTranslations.myProfile),
                    ),
                    CustomListRow(
                      iconAsset: 'assets/icons/acc_preferences.svg',
                      title: AppTranslations.preferences,
                      onTap: controller.openPreferences,
                    ),
                    CustomListRow(
                      iconAsset: 'assets/icons/acc_notifications.svg',
                      title: AppTranslations.notifications,
                      onTap: () => controller.comingSoon('Notifications'),
                    ),
                    CustomListRow(
                      iconAsset: 'assets/icons/acc_payments.svg',
                      title: AppTranslations.savedPayments,
                      onTap: () => controller.comingSoon('Saved Payments'),
                    ),
                    CustomListRow(
                      iconAsset: 'assets/icons/acc_security.svg',
                      title: AppTranslations.security,
                      onTap: () =>
                          controller.comingSoon(AppTranslations.security),
                    ),
                  ],
                ),
                CustomSettingsSection(
                  title: AppTranslations.support,
                  children: [
                    CustomListRow(
                      iconAsset: 'assets/icons/acc_help.svg',
                      title: AppTranslations.helpAndSupport,
                      onTap: () =>
                          controller.comingSoon(AppTranslations.helpAndSupport),
                    ),
                    CustomListRow(
                      iconAsset: 'assets/icons/acc_legal.svg',
                      title: AppTranslations.legal,
                      onTap: () => controller.comingSoon(AppTranslations.legal),
                    ),
                  ],
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
              child: Row(
                mainAxisSize: MainAxisSize.min,
                spacing: 6,
                children: [
                  SvgPicture.asset(
                    'assets/icons/acc_signout.svg',
                    width: 18,
                    height: 18,
                    colorFilter: const ColorFilter.mode(
                      AppColors.brickRed,
                      BlendMode.srcIn,
                    ),
                  ),
                  Text(AppTranslations.signOut),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}
