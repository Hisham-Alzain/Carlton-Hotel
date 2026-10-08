import 'package:carlton/components/loyalty/loyalty_card_texture.dart';
import 'package:carlton/customWidgets/custom_containers.dart';
import 'package:carlton/customWidgets/custom_filled_button.dart';
import 'package:carlton/extensions/points_extension.dart';
import 'package:carlton/extensions/price_extension.dart';
import 'package:carlton/l10n/app_translations.dart';
import 'package:carlton/models/loyalty.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:carlton/theme/theme.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';

/// One reward in the catalogue: what it is, what it costs in points, and the
/// Redeem button — or what is still missing when the balance falls short.
class LoyaltyRewardCard extends StatelessWidget {
  final LoyaltyReward reward;

  /// Points still needed; 0 when the guest can afford it.
  final int missingPoints;

  /// True while this reward's redeem call is in flight.
  final bool redeeming;

  /// Disabled while any redeem is running, so one tap cannot become two.
  final bool enabled;
  final VoidCallback onRedeem;

  const LoyaltyRewardCard({
    required this.reward,
    required this.missingPoints,
    required this.redeeming,
    required this.enabled,
    required this.onRedeem,
    super.key,
  });

  @override
  Widget build(BuildContext context) {
    final TextTheme textStyle = Get.textTheme;
    final description = reward.description.value;
    final affordable = missingPoints == 0;

    return Container(
      padding: const EdgeInsets.all(15),
      decoration: loyaltyPanelDecoration,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        spacing: 14,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            spacing: 12,
            children: [
              CustomIconChip(
                size: 44,
                radius: 12,
                backgroundColor: AppColors.antiqueGold09,
                border: Border.all(color: AppColors.antiqueGold20),
                child: Icon(
                  switch (reward.type) {
                    LoyaltyRewardType.freeNight => Icons.hotel_outlined,
                    LoyaltyRewardType.roomUpgrade =>
                      Icons.arrow_circle_up_outlined,
                    _ => Icons.sell_outlined,
                  },
                  size: 20,
                  color: AppColors.bronzeGold,
                ),
              ),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  spacing: 4,
                  children: [
                    Text(
                      reward.name.value,
                      style: textStyle.titleSmall?.copyWith(
                        fontWeight: FontWeight.w700,
                        color: AppColors.primary,
                      ),
                    ),
                    if (description.isNotEmpty)
                      Text(
                        description,
                        style: textStyle.dmLabelMedium?.copyWith(
                          color: AppColors.taupeBrown,
                        ),
                      ),
                    if (reward.discountUsd != null)
                      Text(
                        AppTranslations.loyaltyValueOff(
                          MoneyFormat.usdString(reward.discountUsd),
                        ),
                        style: textStyle.dmLabelMedium?.copyWith(
                          fontWeight: FontWeight.w600,
                          color: AppColors.forestGreen,
                        ),
                      ),
                  ],
                ),
              ),
              PillContainer(
                backgroundColor: AppColors.antiqueGold09,
                radius: 20,
                padding: const EdgeInsets.symmetric(
                  horizontal: 10,
                  vertical: 4,
                ),
                child: Text(
                  AppTranslations.loyaltyPointsCost(
                    reward.pointsCost.formatPoints(),
                  ),
                  style: textStyle.dmLabelSmall?.copyWith(
                    fontWeight: FontWeight.w700,
                    color: AppColors.bronzeGold,
                  ),
                ),
              ),
            ],
          ),
          if (affordable)
            CustomFilledButton(
              height: 44,
              isLoading: redeeming,
              onPressed: enabled ? onRedeem : null,
              child: Text(AppTranslations.loyaltyRedeemReward),
            )
          else
            Text(
              AppTranslations.loyaltyNeedMorePoints(
                missingPoints.formatPoints(),
              ),
              textAlign: TextAlign.center,
              style: textStyle.dmLabelMedium?.copyWith(
                color: AppColors.taupeBrown,
              ),
            ),
        ],
      ),
    );
  }
}
