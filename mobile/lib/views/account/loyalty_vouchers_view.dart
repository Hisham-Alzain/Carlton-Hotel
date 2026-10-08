import 'package:carlton/components/loyalty/loyalty_page.dart';
import 'package:carlton/components/loyalty/loyalty_voucher_card.dart';
import 'package:carlton/controllers/account/loyalty_vouchers_controller.dart';
import 'package:carlton/l10n/app_translations.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';

/// The guest's vouchers, to copy a code from before booking.
class LoyaltyVouchersView extends GetView<LoyaltyVouchersController> {
  const LoyaltyVouchersView({super.key});

  @override
  Widget build(BuildContext context) {
    return LoyaltyScaffold(
      title: AppTranslations.loyaltyMyVouchers,
      body: Obx(
        () => LoyaltyPagedList(
          loading: controller.loading.value,
          hasError: controller.hasError.value,
          loadingMore: controller.loadingMore.value,
          itemCount: controller.items.length,
          scrollController: controller.scrollController,
          onRetry: controller.reload,
          emptyIcon: Icons.confirmation_number_outlined,
          emptyTitle: AppTranslations.loyaltyNoVouchers,
          emptyBody: AppTranslations.loyaltyNoVouchersBody,
          itemBuilder: (context, index) {
            final voucher = controller.items[index];
            return LoyaltyVoucherCard(
              voucher: voucher,
              onCopy: () => controller.copyCode(voucher),
            );
          },
        ),
      ),
    );
  }
}
