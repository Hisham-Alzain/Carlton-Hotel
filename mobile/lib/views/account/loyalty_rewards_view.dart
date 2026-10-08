import 'package:carlton/components/loyalty/loyalty_page.dart';
import 'package:carlton/components/loyalty/loyalty_reward_card.dart';
import 'package:carlton/controllers/account/loyalty_rewards_controller.dart';
import 'package:carlton/l10n/app_translations.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';

/// The rewards catalogue: spend points, receive a voucher.
class LoyaltyRewardsView extends GetView<LoyaltyRewardsController> {
  const LoyaltyRewardsView({super.key});

  @override
  Widget build(BuildContext context) {
    return LoyaltyScaffold(
      title: AppTranslations.loyaltyRewardsTitle,
      body: Obx(() {
        // The balance, redeeming state and list are read here, in one Obx, so
        // a redeem repaints the buttons and the affordability together.
        final available =
            controller.loyalty.account.value?.availablePoints ?? 0;
        final busy = controller.redeemingUuid.value;
        return LoyaltyPagedList(
          loading: controller.loading.value,
          hasError: controller.hasError.value,
          loadingMore: controller.loadingMore.value,
          itemCount: controller.items.length,
          scrollController: controller.scrollController,
          onRetry: controller.reload,
          emptyIcon: Icons.card_giftcard_outlined,
          emptyTitle: AppTranslations.loyaltyNoRewards,
          emptyBody: AppTranslations.loyaltyNoRewardsBody,
          itemBuilder: (context, index) {
            final reward = controller.items[index];
            final missing = reward.pointsCost - available;
            return LoyaltyRewardCard(
              reward: reward,
              missingPoints: missing > 0 ? missing : 0,
              redeeming: busy == reward.uuid,
              enabled: busy == null,
              onRedeem: () => controller.confirmRedeem(reward),
            );
          },
        );
      }),
    );
  }
}
