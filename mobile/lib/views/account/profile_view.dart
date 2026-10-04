import 'package:carlton/theme/theme.dart';
import 'package:carlton/components/custom_initial_avatar.dart';
import 'package:carlton/components/loyalty/loyalty_card_texture.dart';
import 'package:carlton/controllers/account/profile_controller.dart';
import 'package:carlton/customWidgets/custom_filled_button.dart';
import 'package:carlton/customWidgets/custom_scaffold.dart';
import 'package:carlton/customWidgets/custom_text_field.dart';
import 'package:carlton/customWidgets/custom_texts.dart';
import 'package:carlton/customWidgets/custom_validation.dart';
import 'package:carlton/l10n/app_translations.dart';
import 'package:carlton/models/guest.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';

class ProfileView extends GetView<ProfileController> {
  const ProfileView({super.key});

  @override
  Widget build(BuildContext context) {
    return CustomScaffold(
      appBar: AppBar(
        iconTheme: const IconThemeData(color: AppColors.inkBlack),
        title: Text(AppTranslations.myProfile),
      ),
      // One observer: the guest (updated by a save) and the edit/saving flags
      // change what every row shows.
      body: Obx(() {
        final guest = controller.guest;
        if (guest == null) return const SizedBox.shrink();
        final editing = controller.isEditing.value;

        return Form(
          key: controller.formKey,
          child: ListView(
            padding: const EdgeInsets.all(16),
            children: [
              Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                spacing: 16,
                children: [
                  _ProfileHero(guest: guest),
                  _DetailsCard(
                    rows: [
                      _DetailRow(
                        icon: Icons.person_outline,
                        label: AppTranslations.firstNameLabel,
                        value: guest.firstName,
                        field: editing
                            ? _field(
                                controller.firstNameController,
                                TextInputType.name,
                                AppTranslations.firstNameHint,
                                CustomValidation().validateRequiredField,
                              )
                            : null,
                      ),
                      _DetailRow(
                        icon: Icons.badge_outlined,
                        label: AppTranslations.lastNameLabel,
                        value: guest.lastName,
                        field: editing
                            ? _field(
                                controller.lastNameController,
                                TextInputType.name,
                                AppTranslations.lastNameHint,
                                CustomValidation().validateRequiredField,
                              )
                            : null,
                      ),
                      _DetailRow(
                        icon: Icons.mail_outline,
                        label: AppTranslations.email,
                        value: guest.email,
                        verified: guest.emailVerified,
                        ltrValue: true,
                        field: editing
                            ? _field(
                                controller.emailController,
                                TextInputType.emailAddress,
                                AppTranslations.emailAddressHint,
                                // Optional while a phone is on file (empty
                                // clears it); otherwise it is the sign-in
                                // identity. Anything typed must be real.
                                (text) {
                                  final email = text?.trim() ?? '';
                                  if (email.isEmpty &&
                                      !controller.emailRequired) {
                                    return null;
                                  }
                                  return CustomValidation().validateEmail(
                                    email,
                                  );
                                },
                              )
                            : null,
                      ),
                    ],
                  ),
                  editing ? const _EditActions() : const _EditButton(),
                ],
              ),
            ],
          ),
        );
      }),
    );
  }

  static Widget _field(
    TextEditingController controller,
    TextInputType type,
    String hint,
    String? Function(String?) validator,
  ) => CustomTextField(
    controller: controller,
    textInputType: type,
    hintText: hint,
    fillColor: AppColors.white,
    borderColor: AppColors.antiqueGold20,
    validator: validator,
  );
}

/// Identity card: teal gradient with a gold hairline and the Account/Loyalty
/// glow texture; the gold-ringed initial beside the name and verified phone.
class _ProfileHero extends StatelessWidget {
  final Guest guest;

  const _ProfileHero({required this.guest});

  @override
  Widget build(BuildContext context) {
    final TextTheme textStyle = Get.textTheme;
    final phone = guest.phone ?? '';

    return TealFoilCard(
      radius: 20,
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Row(
          spacing: 14,
          children: [
            // Gold ring around a gold avatar — a teal one would vanish
            // into the card behind it.
            Container(
              padding: const EdgeInsets.all(3),
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                border: Border.all(color: AppColors.sandGold, width: 1.5),
              ),
              child: CustomInitialAvatar(
                initial: guest.firstName ?? '',
                size: 58,
                backgroundColor: AppColors.antiqueGold,
                foregroundColor: AppColors.espressoBrown,
              ),
            ),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                spacing: 6,
                children: [
                  Text(
                    guest.fullName,
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                    style: textStyle.titleLarge?.copyWith(
                      fontFamily: 'The Seasons',
                      color: AppColors.white,
                      letterSpacing: 0.4,
                    ),
                  ),
                  const _GoldRule(width: 48),
                  // The phone is the verified sign-in identity: shown,
                  // never edited here.
                  if (phone.isNotEmpty)
                    Row(
                      spacing: 6,
                      children: [
                        Flexible(
                          child: Text(
                            phone,
                            textDirection: TextDirection.ltr,
                            overflow: TextOverflow.ellipsis,
                            style: textStyle.dmLabelMedium?.copyWith(
                              color: AppColors.white73,
                            ),
                          ),
                        ),
                        if (guest.phoneVerified)
                          Tooltip(
                            message: AppTranslations.verified,
                            child: const Icon(
                              Icons.verified,
                              size: 16,
                              color: AppColors.sandGold,
                            ),
                          ),
                      ],
                    ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _DetailsCard extends StatelessWidget {
  final List<Widget> rows;

  const _DetailsCard({required this.rows});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.fromLTRB(16, 14, 16, 4),
      decoration: BoxDecoration(
        color: AppColors.pearlCream,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: AppColors.antiqueGold20),
        boxShadow: const [
          BoxShadow(
            color: AppColors.slateShadow04,
            blurRadius: 12,
            offset: Offset(0, 4),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        spacing: 2,
        children: [
          Text(
            AppTranslations.personalInformation.toUpperCase(),
            style: Get.textTheme.labelSmall?.copyWith(
              fontWeight: FontWeight.w700,
              letterSpacing: 1.6,
              color: AppColors.bronzeGold,
            ),
          ),
          for (var i = 0; i < rows.length; i++) ...[
            if (i > 0) const _GoldRule(),
            rows[i],
          ],
        ],
      ),
    );
  }
}

class _DetailRow extends StatelessWidget {
  final IconData icon;
  final String label;
  final String? value;
  final Widget? field;
  final bool verified;

  /// Emails read left-to-right even in Arabic.
  final bool ltrValue;

  const _DetailRow({
    required this.icon,
    required this.label,
    required this.value,
    this.field,
    this.verified = false,
    this.ltrValue = false,
  });

  @override
  Widget build(BuildContext context) {
    final TextTheme textStyle = Get.textTheme;
    final hasValue = value != null && value!.trim().isNotEmpty;

    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 9),
      child: Row(
        spacing: 12,
        children: [
          Container(
            width: 36,
            height: 36,
            alignment: Alignment.center,
            decoration: BoxDecoration(
              color: AppColors.antiqueGold09,
              borderRadius: BorderRadius.circular(10),
              border: Border.all(color: AppColors.antiqueGold20),
            ),
            child: Icon(icon, size: 18, color: AppColors.walnutGold),
          ),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              spacing: 2,
              children: [
                Text(
                  label,
                  style: textStyle.dmLabelSmall?.copyWith(
                    color: AppColors.taupeBrown,
                  ),
                ),
                field ??
                    Text(
                      hasValue ? value! : AppTranslations.notAdded,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      textDirection: hasValue && ltrValue
                          ? TextDirection.ltr
                          : null,
                      style: textStyle.titleSmall?.copyWith(
                        fontWeight: FontWeight.w600,
                        color: hasValue
                            ? AppColors.inkBlack
                            : AppColors.stoneTaupe,
                        fontStyle: hasValue
                            ? FontStyle.normal
                            : FontStyle.italic,
                      ),
                    ),
              ],
            ),
          ),
          if (field == null && hasValue && verified)
            Tooltip(
              message: AppTranslations.verified,
              child: const Icon(
                Icons.verified,
                size: 18,
                color: AppColors.successGreen,
              ),
            ),
        ],
      ),
    );
  }
}

/// A gold hairline fading out at both ends.
class _GoldRule extends StatelessWidget {
  final double? width;

  const _GoldRule({this.width});

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: width,
      height: 1,
      child: const DecoratedBox(
        decoration: BoxDecoration(
          gradient: LinearGradient(
            colors: [
              AppColors.antiqueGold08,
              AppColors.antiqueGold56,
              AppColors.antiqueGold08,
            ],
          ),
        ),
      ),
    );
  }
}

class _EditButton extends GetView<ProfileController> {
  const _EditButton();

  @override
  Widget build(BuildContext context) {
    return CustomFilledButton(
      width: double.infinity,
      height: 52,
      onPressed: controller.startEditing,
      backgroundColor: AppColors.primary,
      foregroundColor: AppColors.sandGold,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(14),
        side: const BorderSide(color: AppColors.antiqueGold56),
      ),
      child: RowTextComponent(
        text: AppTranslations.editProfile,
        icon: Icons.edit_outlined,
        iconSize: 18,
        spacing: 8,
        mainAxisAlignment: MainAxisAlignment.center,
      ),
    );
  }
}

class _EditActions extends GetView<ProfileController> {
  const _EditActions();

  @override
  Widget build(BuildContext context) {
    return Row(
      spacing: 12,
      children: [
        Expanded(
          child: CustomFilledButton(
            height: 52,
            onPressed: controller.cancelEditing,
            backgroundColor: AppColors.white,
            foregroundColor: AppColors.primary,
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(14),
              side: const BorderSide(color: AppColors.antiqueGold56),
            ),
            child: Text(AppTranslations.cancel),
          ),
        ),
        Expanded(
          child: Obx(
            () => CustomFilledButton(
              height: 52,
              isLoading: controller.isSaving.value,
              onPressed: controller.save,
              backgroundColor: AppColors.primary,
              foregroundColor: AppColors.sandGold,
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(14),
              ),
              child: Text(AppTranslations.saveChanges),
            ),
          ),
        ),
      ],
    );
  }
}
