import 'package:carlton/l10n/app_translations.dart';
import 'package:carlton/customWidgets/custom_containers.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:flutter_svg/flutter_svg.dart';
import 'package:get/get.dart';

class CustomPayAtHotelPanel extends StatelessWidget {
  const CustomPayAtHotelPanel({super.key});

  @override
  Widget build(BuildContext context) {
    final TextTheme textStyle = Get.textTheme;
    return Container(
      clipBehavior: Clip.antiAlias,
      decoration: BoxDecoration(
        color: AppColors.white,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: AppColors.black07, width: 1),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: double.infinity,
            padding: const EdgeInsets.all(10),
            color: AppColors.lagoonTeal.withValues(alpha: 0.10),
            child: Column(
              spacing: 10,
              children: [
                Container(
                  width: 60,
                  height: 60,
                  alignment: Alignment.center,
                  decoration: BoxDecoration(
                    color: AppColors.primary07,
                    borderRadius: BorderRadius.circular(10),
                  ),
                  child: SvgPicture.asset(
                    'assets/icons/pay_hotel.svg',
                    width: 25,
                    height: 25,
                    colorFilter: const ColorFilter.mode(
                      AppColors.primary,
                      BlendMode.srcIn,
                    ),
                  ),
                ),
                Text(
                  AppTranslations.payAtHotel,
                  style: textStyle.titleMedium?.copyWith(
                    fontWeight: FontWeight.w600,
                    color: AppColors.primary,
                  ),
                ),
                Text(
                  'No payment required now',
                  style: textStyle.labelMedium?.copyWith(
                    fontFamily: 'DM Sans',
                    color: AppColors.graphite,
                  ),
                ),
              ],
            ),
          ),
          Padding(
            padding: const EdgeInsets.all(20),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              spacing: 10,
              children: [
                Text(
                  AppTranslations.howItWorks,
                  style: _headingStyle(textStyle),
                ),
                Text(
                  'Your reservation is secured without any charge today. '
                  'Payment will be collected at the front desk upon check-in.',
                  style: textStyle.labelMedium?.copyWith(
                    fontFamily: 'DM Sans',
                    color: AppColors.inkBlack,
                  ),
                ),
                // 14, not 4: the old spacer sat between two 10px gaps.
                Text(
                  'Accepted Payment Methods at Hotel',
                  style: _headingStyle(textStyle),
                ),
                _MethodRow(
                  iconPath: 'assets/icons/pay_card.svg',
                  label: AppTranslations.acceptedCards,
                ),
                _MethodRow(
                  iconPath: 'assets/icons/bank.svg',
                  label: AppTranslations.bankWire,
                ),
                _MethodRow(
                  iconPath: 'assets/icons/cash.svg',
                  label: AppTranslations.acceptedCash,
                ),
                PillContainer(
                  width: double.infinity,
                  radius: 8,
                  backgroundColor: AppColors.successGreen08,
                  child: Row(
                    spacing: 8,
                    children: [
                      SvgPicture.asset(
                        'assets/icons/check.svg',
                        width: 14,
                        height: 14,
                        colorFilter: const ColorFilter.mode(
                          AppColors.successGreen,
                          BlendMode.srcIn,
                        ),
                      ),
                      Expanded(
                        child: Text(
                          'Free cancellation up to 48 hours before arrival',
                          style: textStyle.labelMedium?.copyWith(
                            fontFamily: 'DM Sans',
                            color: AppColors.inkBlack,
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  TextStyle? _headingStyle(TextTheme textStyle) => textStyle.labelMedium
      ?.copyWith(fontWeight: FontWeight.w600, color: AppColors.inkBlack);
}

class _MethodRow extends StatelessWidget {
  final String iconPath;
  final String label;

  const _MethodRow({required this.iconPath, required this.label});

  @override
  Widget build(BuildContext context) {
    final TextTheme textStyle = Get.textTheme;
    return Row(
      spacing: 10,
      children: [
        SvgPicture.asset(
          iconPath,
          width: 15,
          height: 15,
          colorFilter: const ColorFilter.mode(
            AppColors.graphite,
            BlendMode.srcIn,
          ),
        ),
        Text(
          label,
          style: textStyle.labelMedium?.copyWith(
            fontFamily: 'DM Sans',
            color: AppColors.inkBlack,
          ),
        ),
      ],
    );
  }
}
