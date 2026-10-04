import 'package:carlton/theme/theme.dart';
import 'package:carlton/components/custom_initial_avatar.dart';
import 'package:carlton/components/loyalty/loyalty_card_texture.dart';
import 'package:carlton/l10n/app_translations.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';

/// The Account screen's header: identity and the Loyalty entry point as one
/// surface, not two.
///
/// They used to be a plain avatar row sitting above a separately-styled
/// Loyalty card, and the two read as unrelated — a guest's name doesn't
/// obviously buy them "2,450 points toward Platinum." Folding the tap-through
/// into the same teal-and-gold card the identity sits on makes the connection
/// literal: this membership belongs to this guest, on one card, the way a
/// physical loyalty card carries both at once.
class AccountProfileCard extends StatelessWidget {
  final String name;
  final String email;
  final VoidCallback onTapLoyalty;

  const AccountProfileCard({
    required this.name,
    required this.email,
    required this.onTapLoyalty,
    super.key,
  });

  @override
  Widget build(BuildContext context) {
    final TextTheme textStyle = Get.textTheme;

    return TealFoilCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 18, 16, 16),
            child: Row(
              spacing: 14,
              children: [
                // Gold, not the usual primary teal — an avatar the same
                // colour as the card behind it would vanish into it.
                CustomInitialAvatar(
                  initial: name,
                  backgroundColor: AppColors.antiqueGold,
                  foregroundColor: AppColors.espressoBrown,
                ),
                Expanded(
                  child: Column(
                    spacing: 4,
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        name,
                        style: textStyle.titleMedium?.copyWith(
                          fontWeight: FontWeight.w700,
                          color: AppColors.white,
                        ),
                      ),
                      Text(
                        email,
                        style: textStyle.dmLabelMedium?.copyWith(
                          color: AppColors.white73,
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),

          // Gold fading to nothing, not a flat white rule — the same
          // device LoyaltyPointsCard uses to separate its own sections,
          // so this reads as one more section of that card, not a seam
          // between two different widgets.
          const Padding(
            padding: EdgeInsets.symmetric(horizontal: 16),
            child: DecoratedBox(
              decoration: BoxDecoration(
                gradient: LinearGradient(
                  colors: [AppColors.antiqueGold56, AppColors.white00],
                ),
              ),
              child: SizedBox(height: 1),
            ),
          ),

          InkWell(
            onTap: onTapLoyalty,
            child: Padding(
              padding: const EdgeInsets.fromLTRB(16, 14, 16, 18),
              child: Row(
                spacing: 14,
                children: [
                  Container(
                    width: 40,
                    height: 40,
                    alignment: Alignment.center,
                    decoration: BoxDecoration(
                      borderRadius: BorderRadius.circular(10),
                      gradient: const LinearGradient(
                        colors: [AppColors.antiqueGold, AppColors.sandGold],
                      ),
                    ),
                    child: const Icon(
                      // A membership card, not the medal — the medal
                      // belongs to the tier badge on the card inside
                      // Loyalty, and reusing it here would spend the same
                      // glyph on two different meanings.
                      Icons.card_membership_rounded,
                      size: 20,
                      color: AppColors.espressoBrown,
                    ),
                  ),
                  Expanded(
                    child: Column(
                      spacing: 3,
                      crossAxisAlignment: CrossAxisAlignment.start,
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Text(
                          AppTranslations.loyaltyRow,
                          style: textStyle.labelLarge?.copyWith(
                            fontWeight: FontWeight.w700,
                            color: AppColors.white,
                          ),
                        ),
                        Text(
                          AppTranslations.loyaltyRowBody,
                          style: textStyle.dmLabelSmall?.copyWith(
                            color: AppColors.white73,
                          ),
                        ),
                      ],
                    ),
                  ),
                  // A glass chip around the chevron, not a bare glyph
                  // floating on the gradient — the tier badge's shape
                  // language, scaled down to a dot.
                  Container(
                    width: 26,
                    height: 26,
                    alignment: Alignment.center,
                    decoration: BoxDecoration(
                      shape: BoxShape.circle,
                      color: AppColors.white10,
                      border: Border.all(color: AppColors.antiqueGold56),
                    ),
                    child: const Icon(
                      Icons.chevron_right,
                      size: 16,
                      color: AppColors.sandGold,
                    ),
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}
