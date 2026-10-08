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

part 'profile_widgets.dart';

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
                      _DetailRow(
                        icon: Icons.phone_outlined,
                        label: AppTranslations.phoneNumber,
                        value: guest.phone,
                        ltrValue: true,
                        field: editing
                            ? _field(
                                controller.phoneController,
                                TextInputType.phone,
                                AppTranslations.phoneNumberHint,
                                // Required while there is no email: the phone
                                // is then the only sign-in identity.
                                (text) {
                                  final phone = text?.trim() ?? '';
                                  if (phone.isEmpty &&
                                      !controller.phoneRequired) {
                                    return null;
                                  }
                                  // Stored in full international form
                                  // (+963…): check the digits after the +.
                                  return CustomValidation().validatePhoneNumber(
                                    phone.replaceFirst('+', ''),
                                    dialCode: '',
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
