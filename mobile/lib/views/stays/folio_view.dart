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

/// My Bill: the guest's running folio from `GET /folio`.
///
/// Read-only on purpose. Approving the bill *is* express checkout, which also
/// flips the reservation to checked_out, so that action stays behind the
/// confirmation dialog on Home rather than becoming a button on a statement.
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
          return const Center(child: CustomProgressIndicator());
        }
        if (controller.error.value) {
          return CustomEmptyPlaceholder(
            iconWidget: const Icon(
              Icons.wifi_off_rounded,
              size: 48,
              color: AppColors.mediumGrey,
            ),
            title: AppTranslations.folioLoadFailed,
            subtitle: AppTranslations.checkConnectionShort,
            primaryLabel: AppTranslations.retry,
            onPrimary: controller.load,
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
              if (controller.isApproved) const _ApprovedBanner(),
              PillContainer(
                radius: 14,
                backgroundColor: AppColors.white,
                padding: const EdgeInsets.all(20),
                border: Border.all(color: AppColors.linenGrey),
                child: Column(
                  spacing: 10,
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    for (final item in folio.items) _ItemRow(item: item),
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
                  ],
                ),
              ),
            ],
          ),
        );
      }),
    );
  }
}

/// Shown once the guest has approved the bill for express checkout, so the
/// statement does not read as still open.
class _ApprovedBanner extends StatelessWidget {
  const _ApprovedBanner();

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 20),
      child: PillContainer(
        radius: 12,
        backgroundColor: AppColors.cream,
        padding: const EdgeInsets.all(20),
        child: Row(
          spacing: 10,
          children: [
            const Icon(
              Icons.verified_outlined,
              size: 18,
              color: AppColors.forestGreen,
            ),
            Expanded(
              child: Text(
                AppTranslations.folioApproved,
                style: Get.textTheme.labelMedium?.copyWith(
                  fontFamily: 'DM Sans',
                  color: AppColors.inkBlack,
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

/// One folio line. [FolioItem.sourceType] is rendered because it is the only
/// thing separating an identically-named room charge from a service booking.
class _ItemRow extends StatelessWidget {
  final FolioItem item;

  const _ItemRow({required this.item});

  @override
  Widget build(BuildContext context) {
    final TextTheme textStyle = Get.textTheme;

    return Row(
      spacing: 10,
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Expanded(
          child: Column(
            spacing: 5,
            crossAxisAlignment: CrossAxisAlignment.start,
            mainAxisSize: MainAxisSize.min,
            children: [
              Text(
                item.description,
                style: textStyle.titleSmall?.copyWith(
                  color: AppColors.inkBlack,
                ),
              ),
              if (_sourceLabel.isNotEmpty)
                Text(
                  _sourceLabel,
                  style: textStyle.labelSmall?.copyWith(
                    fontFamily: 'DM Sans',
                    color: AppColors.taupeBrown,
                  ),
                ),
            ],
          ),
        ),
        Text(
          MoneyFormat.usdString(item.amountUsd),
          style: textStyle.titleSmall?.copyWith(
            fontWeight: FontWeight.w600,
            color: AppColors.inkBlack,
          ),
        ),
      ],
    );
  }

  /// The wire sends a snake_case enum; anything unrecognised stays unlabelled
  /// rather than printing the raw token.
  String get _sourceLabel => switch (item.sourceType) {
    'reservation' => AppTranslations.folioSourceRoom,
    'service_booking' => AppTranslations.folioSourceService,
    'service_request' => AppTranslations.folioSourceInRoom,
    _ => '',
  };
}
