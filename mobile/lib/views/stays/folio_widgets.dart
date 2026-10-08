part of 'folio_view.dart';

/// Shown once the guest has approved the bill for express checkout, so the
/// statement does not read as still open.
class _ApprovedBanner extends StatelessWidget {
  const _ApprovedBanner();

  @override
  Widget build(BuildContext context) {
    return PillContainer(
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
              style: Get.textTheme.dmLabelMedium?.copyWith(
                color: AppColors.inkBlack,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

/// How many lines are under review, so a guest who disputed earlier sees it
/// is still being handled.
class _DisputesBanner extends StatelessWidget {
  final int count;

  const _DisputesBanner({required this.count});

  @override
  Widget build(BuildContext context) {
    return PillContainer(
      radius: 12,
      backgroundColor: AppColors.cream,
      padding: const EdgeInsets.all(14),
      child: Row(
        spacing: 10,
        children: [
          const Icon(
            Icons.report_gmailerrorred_outlined,
            size: 18,
            color: AppColors.brickRed,
          ),
          Expanded(
            child: Text(
              AppTranslations.disputesOpenCount(count),
              style: Get.textTheme.dmLabelMedium?.copyWith(
                color: AppColors.inkBlack,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

/// One folio line; tapping it disputes the charge. [FolioItem.sourceType] is
/// rendered because it is the only thing separating an identically-named room
/// charge from a service booking.
class _ItemRow extends StatelessWidget {
  final FolioItem item;
  final VoidCallback onDispute;

  const _ItemRow({required this.item, required this.onDispute, super.key});

  @override
  Widget build(BuildContext context) {
    final TextTheme textStyle = Get.textTheme;
    final unit = item.unitPriceUsd;

    return InkWell(
      onTap: onDispute,
      borderRadius: BorderRadius.circular(8),
      child: Row(
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
                if (item.quantity > 1 && unit != null)
                  Text(
                    AppTranslations.folioQuantity(
                      item.quantity,
                      MoneyFormat.usdString(unit),
                    ),
                    style: textStyle.dmLabelSmall?.copyWith(
                      color: AppColors.taupeBrown,
                    ),
                  ),
                if (_sourceLabel.isNotEmpty)
                  Text(
                    _sourceLabel,
                    style: textStyle.dmLabelSmall?.copyWith(
                      color: AppColors.taupeBrown,
                    ),
                  ),
                if (item.hasOpenDispute)
                  PillContainer(
                    backgroundColor: AppColors.brickRed07,
                    child: Text(
                      AppTranslations.disputeUnderReview,
                      style: textStyle.dmLabelSmall?.copyWith(
                        fontWeight: FontWeight.w700,
                        color: AppColors.brickRed,
                      ),
                    ),
                  ),
              ],
            ),
          ),
          Text(
            MoneyFormat.usdString(item.amountUsd),
            style: textStyle.titleSmall?.copyWith(
              fontWeight: FontWeight.w600,
              color: item.sourceType == 'credit'
                  ? AppColors.forestGreen
                  : AppColors.inkBlack,
            ),
          ),
        ],
      ),
    );
  }

  /// The wire sends a snake_case enum; anything unrecognised stays unlabelled
  /// rather than printing the raw token.
  String get _sourceLabel => switch (item.sourceType) {
    'reservation' => AppTranslations.folioSourceRoom,
    'service_booking' => AppTranslations.folioSourceService,
    'service_request' => AppTranslations.folioSourceInRoom,
    'manual' => AppTranslations.folioSourceDesk,
    'credit' => AppTranslations.folioSourceCredit,
    _ => '',
  };
}
