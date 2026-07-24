import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:flutter_svg/flutter_svg.dart';

class CustomWalletPanel extends StatelessWidget {
  final String glyphPath;
  final Color badgeColor;
  final bool tintGlyphWhite;
  final String title;
  final String subtitle;
  final List<String> bullets;
  final String footer;

  const CustomWalletPanel({
    required this.glyphPath,
    required this.badgeColor,
    required this.tintGlyphWhite,
    required this.title,
    required this.subtitle,
    required this.bullets,
    required this.footer,
    super.key,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      clipBehavior: Clip.antiAlias,
      decoration: BoxDecoration(
        color: AppColors.white,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: AppColors.black07, width: 1.18),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Container(
            color: AppColors.inkBlack,
            padding: const EdgeInsets.symmetric(vertical: 22, horizontal: 20),
            child: Column(
              spacing: 8,
              children: [
                Container(
                  width: 48,
                  height: 48,
                  alignment: Alignment.center,
                  decoration: BoxDecoration(
                    color: badgeColor,
                    borderRadius: BorderRadius.circular(12),
                  ),
                  child: SvgPicture.asset(
                    glyphPath,
                    width: 26,
                    height: 26,
                    colorFilter: tintGlyphWhite
                        ? const ColorFilter.mode(
                            AppColors.white,
                            BlendMode.srcIn,
                          )
                        : null,
                  ),
                ),
                Text(
                  title,
                  textAlign: TextAlign.center,
                  style: const TextStyle(
                    fontFamily: 'Plus Jakarta Sans',
                    fontSize: 16,
                    fontWeight: FontWeight.w600,
                    color: AppColors.white,
                  ),
                ),
                Text(
                  subtitle,
                  textAlign: TextAlign.center,
                  style: const TextStyle(
                    fontFamily: 'DM Sans',
                    fontSize: 12,
                    color: AppColors.white73,
                  ),
                ),
              ],
            ),
          ),
          Padding(
            padding: const EdgeInsets.all(18),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              spacing: 12,
              children: [
                for (final b in bullets)
                  Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    spacing: 8,
                    children: [
                      const Icon(
                        Icons.check_circle,
                        size: 18,
                        color: AppColors.successGreen,
                      ),
                      Expanded(
                        child: Text(
                          b,
                          style: const TextStyle(
                            fontFamily: 'DM Sans',
                            fontSize: 13,
                            color: AppColors.inkBlack,
                          ),
                        ),
                      ),
                    ],
                  ),
                Container(
                  width: double.infinity,
                  padding: const EdgeInsets.symmetric(
                    horizontal: 14,
                    vertical: 10,
                  ),
                  decoration: BoxDecoration(
                    color: AppColors.whisperGrey,
                    borderRadius: BorderRadius.circular(8),
                  ),
                  child: Text(
                    footer,
                    style: const TextStyle(
                      fontFamily: 'DM Sans',
                      fontSize: 11,
                      color: AppColors.dimGrey,
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
