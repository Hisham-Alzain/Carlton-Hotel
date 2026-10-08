import 'package:carlton/theme/theme.dart';
import 'package:carlton/l10n/app_translations.dart';
import 'package:carlton/components/custom_price_summary.dart';
import 'package:carlton/controllers/stays/folio_controller.dart';
import 'package:carlton/customWidgets/custom_containers.dart';
import 'package:carlton/customWidgets/custom_empty_placeholder.dart';
import 'package:carlton/customWidgets/custom_indicators.dart';
import 'package:carlton/customWidgets/custom_scaffold.dart';
import 'package:carlton/extensions/price_extension.dart';
import 'package:carlton/models/folio.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';

part 'folio_widgets.dart';

/// My Bill: the guest's running folio from `GET /folio`.
///
/// Approving the bill *is* express checkout, which also flips the reservation
/// to checked_out, so that action stays behind the confirmation dialog on Home
/// rather than becoming a button on a statement. The one action here is
/// disputing a line (tap it).
class FolioView extends GetView<FolioController> {
  const FolioView({super.key});

  @override
  Widget build(BuildContext context) {
    return CustomScaffold(
      appBar: AppBar(
        iconTheme: const IconThemeData(color: AppColors.inkBlack),
        title: Text(AppTranslations.myBill),
      ),
      body: Obx(() {
        if (controller.loading.value) {
          return const Center(child: LogoLoadingIndicator(size: 50));
        }
        if (controller.error.value) {
          return CustomEmptyPlaceholder.loadFailed(
            title: AppTranslations.folioLoadFailed,
            subtitle: AppTranslations.checkConnectionShort,
            onRetry: controller.load,
          );
        }
        final folio = controller.folio.value;
        // An in-house guest who has charged nothing yet: a real, healthy state,
        // not a failure, so no Retry.
        if (folio == null || folio.items.isEmpty) {
          return CustomEmptyPlaceholder(
            iconWidget: Icon(
              Icons.receipt_long_outlined,
              size: 48,
              color: AppColors.mediumGrey,
            ),
            title: AppTranslations.folioEmpty,
            subtitle: AppTranslations.folioEmptySubtitle,
          );
        }
        return RefreshIndicator(
          onRefresh: controller.load,
          color: AppColors.primary,
          child: ListView(
            physics: const AlwaysScrollableScrollPhysics(),
            padding: const EdgeInsets.all(20),
            children: [
              Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                spacing: 20,
                children: [
                  if (controller.isApproved) const _ApprovedBanner(),
                  Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    spacing: 10,
                    children: [
                      if (folio.openDisputesCount > 0)
                        _DisputesBanner(count: folio.openDisputesCount),
                      Text(
                        AppTranslations.disputeHint,
                        style: Get.textTheme.dmLabelSmall?.copyWith(
                          color: AppColors.taupeBrown,
                        ),
                      ),
                      _statement(folio),
                    ],
                  ),
                ],
              ),
            ],
          ),
        );
      }),
    );
  }

  Widget _statement(Folio folio) => PillContainer(
    radius: 14,
    backgroundColor: AppColors.white,
    padding: const EdgeInsets.all(20),
    border: Border.all(color: AppColors.linenGrey),
    child: Column(
      spacing: 10,
      mainAxisSize: MainAxisSize.min,
      children: [
        for (final item in folio.items)
          _ItemRow(
            key: ValueKey(item.uuid),
            item: item,
            onDispute: () => controller.openDispute(item),
          ),
        const Divider(height: 1, color: AppColors.black06),
        CustomPriceSummaryRow(
          title: AppTranslations.subtotal,
          value: MoneyFormat.usdString(folio.subtotalUsd),
        ),
        CustomPriceSummaryRow(
          title: AppTranslations.total,
          value: MoneyFormat.usdString(folio.totalUsd),
          isTotal: true,
        ),
        for (final payment in folio.payments)
          CustomPriceSummaryRow(
            key: ValueKey(payment.uuid),
            title: AppTranslations.folioPayment(payment.method),
            value: '− ${MoneyFormat.usdString(payment.amountUsd)}',
          ),
        if (folio.payments.isNotEmpty)
          CustomPriceSummaryRow(
            title: AppTranslations.folioPaid,
            value: MoneyFormat.usdString(folio.paidUsd),
          ),
        // Signed: below zero the hotel owes the guest, refunded at
        // the desk.
        CustomPriceSummaryRow(
          title: folio.balanceDue < 0
              ? AppTranslations.folioHotelOwes
              : AppTranslations.folioBalanceDue,
          value: MoneyFormat.usd(folio.balanceDue.abs()),
          isTotal: true,
        ),
      ],
    ),
  );
}
