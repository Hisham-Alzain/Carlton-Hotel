import 'package:carlton/customWidgets/custom_containers.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:flutter_svg/flutter_svg.dart';
import 'package:get/get.dart';

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
    final TextTheme textStyle = Get.textTheme;
    return Container(
      width: double.infinity,
      clipBehavior: Clip.antiAlias,
      decoration: BoxDecoration(
        color: AppColors.white,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: AppColors.black07, width: 1),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Container(
            color: AppColors.inkBlack,
            padding: const EdgeInsets.all(10),
            child: Column(
              spacing: 10,
              children: [
                Container(
                  width: 50,
                  height: 50,
                  alignment: Alignment.center,
                  decoration: BoxDecoration(
                    color: badgeColor,
                    borderRadius: BorderRadius.circular(12),
                  ),
                  child: SvgPicture.asset(
                    glyphPath,
                    width: 30,
                    height: 30,
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
                  style: textStyle.titleMedium?.copyWith(
                    fontWeight: FontWeight.w600,
                    color: AppColors.white,
                  ),
                ),
                Text(
                  subtitle,
                  textAlign: TextAlign.center,
                  style: textStyle.labelMedium?.copyWith(
                    fontFamily: 'DM Sans',
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
              spacing: 10,
              children: [
                //TODO:do not use for loop
                for (final b in bullets)
                  Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    spacing: 10,
                    children: [
                      Container(
                        decoration: BoxDecoration(
                          shape: BoxShape.circle,
                          border: Border.all(color: AppColors.successGreen),
                        ),
                        child: const Icon(
                          Icons.check,
                          size: 10,
                          color: AppColors.successGreen,
                        ),
                      ),
                      Text(
                        b,
                        style: textStyle.labelMedium?.copyWith(
                          fontFamily: 'DM Sans',
                          color: AppColors.inkBlack,
                        ),
                      ),
                    ],
                  ),
                PillContainer(
                  width: double.infinity,
                  padding: const EdgeInsets.all(10),
                  backgroundColor: AppColors.whisperGrey,
                  radius: 8,
                  child: Text(
                    footer,
                    style: textStyle.labelSmall?.copyWith(
                      fontFamily: 'DM Sans',
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
