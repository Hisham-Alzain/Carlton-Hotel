import 'package:carlton/customWidgets/custom_image.dart';
import 'package:carlton/customWidgets/custom_texts.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';

class CustomStayCard extends StatelessWidget {
  final String room;
  final String checkedInTime;
  final int nightsRemaining;
  final String imagePath;

  const CustomStayCard({
    required this.room,
    required this.checkedInTime,
    required this.nightsRemaining,
    required this.imagePath,
    super.key,
  });

  @override
  Widget build(BuildContext context) {
    final TextTheme textStyle = Get.textTheme;

    return Container(
      height: 120,
      width: double.infinity,
      clipBehavior: Clip.antiAlias,
      decoration: BoxDecoration(
        color: AppColors.primary,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: Colors.white, width: 1.5),
      ),
      child: Row(
        children: [
          Expanded(
            child: Padding(
              padding: const EdgeInsets.all(10),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                mainAxisAlignment: MainAxisAlignment.center,
                mainAxisSize: MainAxisSize.min,
                spacing: 10,
                children: [
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 10),
                    height: 30,
                    decoration: BoxDecoration(
                      color: AppColors.sandBeige,
                      borderRadius: BorderRadius.circular(8),
                    ),
                    child: RowTextComponent(
                      path: 'assets/icons/bed.svg',
                      iconSize: 20,
                      spacing: 10,
                      text: room,
                      textStyle: textStyle.labelMedium?.copyWith(
                        color: AppColors.espressoBrown,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ),
                  _InfoLine(prefix: 'Checked in since ', value: checkedInTime),
                  _InfoLine(
                    prefix: 'Nights remaining  ',
                    value: '$nightsRemaining',
                  ),
                ],
              ),
            ),
          ),
          Container(
            width: 144,
            foregroundDecoration: const BoxDecoration(
              gradient: LinearGradient(
                begin: Alignment.topLeft,
                end: Alignment.bottomRight,
                colors: [AppColors.iceBlue50, AppColors.surfTeal50],
              ),
            ),
            child: CustomImage(
              path: imagePath,
              fit: BoxFit.cover,
              height: double.infinity,
            ),
          ),
        ],
      ),
    );
  }
}

class _InfoLine extends StatelessWidget {
  final String prefix;
  final String value;

  const _InfoLine({required this.prefix, required this.value});

  @override
  Widget build(BuildContext context) {
    final TextTheme textStyle = Get.textTheme;

    return Text.rich(
      TextSpan(
        style: textStyle.labelSmall?.copyWith(
          color: Colors.white,
          fontWeight: FontWeight.w300,
        ),
        children: [
          TextSpan(text: prefix),
          TextSpan(
            text: value,
            style: textStyle.labelSmall?.copyWith(
              color: Colors.white,
              fontWeight: FontWeight.w700,
            ),
          ),
        ],
      ),
      maxLines: 1,
      overflow: TextOverflow.ellipsis,
      softWrap: false,
    );
  }
}
