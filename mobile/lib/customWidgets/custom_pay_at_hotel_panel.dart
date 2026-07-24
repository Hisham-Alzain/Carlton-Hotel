import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:flutter_svg/flutter_svg.dart';

const _headingStyle = TextStyle(
  fontFamily: 'Plus Jakarta Sans',
  fontSize: 12,
  fontWeight: FontWeight.w600,
  color: AppColors.inkBlack,
);

const _bodyStyle = TextStyle(
  fontFamily: 'DM Sans',
  fontSize: 12,
  color: AppColors.inkBlack,
);

class CustomPayAtHotelPanel extends StatelessWidget {
  const CustomPayAtHotelPanel({super.key});

  @override
  Widget build(BuildContext context) {
    return Container(
      clipBehavior: Clip.antiAlias,
      decoration: BoxDecoration(
        color: AppColors.white,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: AppColors.black07, width: 1.18),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: double.infinity,
            padding: const EdgeInsets.symmetric(vertical: 24, horizontal: 20),
            color: AppColors.lagoonTeal.withValues(alpha: 0.10),
            child: Column(
              spacing: 10,
              children: [
                Container(
                  width: 56,
                  height: 56,
                  alignment: Alignment.center,
                  decoration: BoxDecoration(
                    color: AppColors.primary07,
                    borderRadius: BorderRadius.circular(10),
                  ),
                  child: SvgPicture.asset(
                    'assets/icons/pay_hotel.svg',
                    width: 26,
                    height: 26,
                    colorFilter: const ColorFilter.mode(
                      AppColors.primary,
                      BlendMode.srcIn,
                    ),
                  ),
                ),
                const Text(
                  'Pay at Hotel',
                  style: TextStyle(
                    fontFamily: 'Plus Jakarta Sans',
                    fontSize: 17,
                    fontWeight: FontWeight.w600,
                    color: AppColors.primary,
                  ),
                ),
                const Text(
                  'No payment required now',
                  style: TextStyle(
                    fontFamily: 'DM Sans',
                    fontSize: 12,
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
                const Text('How it works', style: _headingStyle),
                const Text(
                  'Your reservation is secured without any charge today. '
                  'Payment will be collected at the front desk upon check-in.',
                  style: _bodyStyle,
                ),
                // 14, not 4: the old spacer sat between two 10px gaps.
                const Padding(
                  padding: EdgeInsets.only(top: 14),
                  child: Text(
                    'Accepted Payment Methods at Hotel',
                    style: _headingStyle,
                  ),
                ),
                const _MethodRow(
                  iconPath: 'assets/icons/card_line.svg',
                  label: 'Visa, Mastercard',
                ),
                const _MethodRow(
                  iconPath: 'assets/icons/bank.svg',
                  label: 'Bank wire transfer',
                ),
                const _MethodRow(
                  iconPath: 'assets/icons/cash.svg',
                  label: 'Cash (SYP or USD)',
                ),
                Padding(
                  padding: const EdgeInsets.only(top: 14),
                  child: Container(
                    width: double.infinity,
                    padding: const EdgeInsets.symmetric(
                      horizontal: 14,
                      vertical: 10,
                    ),
                    decoration: BoxDecoration(
                      color: AppColors.successGreen08,
                      borderRadius: BorderRadius.circular(8),
                    ),
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
                        const Expanded(
                          child: Text(
                            'Free cancellation up to 48 hours before arrival',
                            style: TextStyle(
                              fontFamily: 'DM Sans',
                              fontSize: 12,
                              color: AppColors.inkBlack,
                            ),
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _MethodRow extends StatelessWidget {
  final String iconPath;
  final String label;

  const _MethodRow({required this.iconPath, required this.label});

  @override
  Widget build(BuildContext context) {
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
          style: const TextStyle(
            fontFamily: 'DM Sans',
            fontSize: 13,
            color: AppColors.inkBlack,
          ),
        ),
      ],
    );
  }
}
