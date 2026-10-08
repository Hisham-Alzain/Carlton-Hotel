part of 'pre_arrival_sections.dart';

/// Airport transfer promo (Figma `2209:2555`). Routes into the existing
/// Services tab rather than inventing a second request path.
class AirportTransferSection extends StatelessWidget {
  final VoidCallback onRequest;

  const AirportTransferSection({required this.onRequest, super.key});

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.only(bottom: 20),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(_glassCardRadius),
        border: Border.fromBorderSide(_glassCardBorder),
        boxShadow: const [_glassCardShadow],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        spacing: 14,
        children: [
          Row(
            spacing: 12,
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  spacing: 8,
                  children: [
                    Text(
                      AppTranslations.airportTransfer,
                      style: Get.textTheme.titleSmall?.copyWith(
                        fontSize: 15,
                        color: AppColors.nearBlack,
                      ),
                    ),
                    Text(
                      AppTranslations.airportTransferBody,
                      style: Get.textTheme.bodySmall?.copyWith(
                        fontSize: 11,
                        color: AppColors.steelGrey,
                      ),
                    ),
                  ],
                ),
              ),
              Image.asset(
                'assets/images/airport_transfer.png',
                width: 90,
                height: 75,
                fit: BoxFit.contain,
                cacheWidth: (90 * MediaQuery.devicePixelRatioOf(context))
                    .round(),
              ),
            ],
          ),
          CustomFilledButton(
            height: 44,
            width: double.infinity,
            onPressed: onRequest,
            backgroundColor: AppColors.lagoonTeal,
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(6),
            ),
            textStyle: Get.textTheme.dmLabelLarge?.copyWith(fontSize: 13),
            child: Text(AppTranslations.requestAirportTransfer),
          ),
        ],
      ),
    );
  }
}
