import 'package:carlton/components/custom_initial_avatar.dart';
import 'package:carlton/customWidgets/custom_filled_button.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:flutter_svg/flutter_svg.dart';
import 'package:get/get.dart';

/// Customer Service conversation header (Figma): agent avatar · a "role" pill
/// above the agent name · an outlined Call pill with a phone icon. [onCall]
/// wires the phone launch.
class CustomAgentHeader extends StatelessWidget {
  final String name;
  final String role;
  final String initial;
  final VoidCallback onCall;

  const CustomAgentHeader({
    required this.name,
    required this.role,
    required this.initial,
    required this.onCall,
    super.key,
  });

  @override
  Widget build(BuildContext context) {
    final TextTheme textStyle = Get.textTheme;

    return Row(
      spacing: 10,
      children: [
        CustomInitialAvatar(
          initial: initial,
          size: 40,
          backgroundColor: AppColors.dustyTeal,
        ),
        Expanded(
          child: Column(
            spacing: 3,
            crossAxisAlignment: CrossAxisAlignment.start,
            mainAxisSize: MainAxisSize.min,
            children: [
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                decoration: BoxDecoration(
                  color: AppColors.cream,
                  borderRadius: BorderRadius.circular(30),
                ),
                child: Text(
                  role,
                  style: textStyle.labelSmall?.copyWith(
                    fontSize: 9,
                    color: AppColors.primary,
                  ),
                ),
              ),
              Text(
                name,
                style: textStyle.labelLarge?.copyWith(
                  fontWeight: FontWeight.w600,
                  color: AppColors.primary,
                ),
              ),
            ],
          ),
        ),
        CustomFilledButton(
          // Was padding-driven (vertical: 8); CustomFilledButton sizes by
          // height and defaults to 50, which would tower over this header row.
          height: 34,
          onPressed: onCall,
          backgroundColor: AppColors.snowGrey,
          foregroundColor: AppColors.primary,
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(30),
            side: const BorderSide(color: AppColors.black06),
          ),
          textStyle: textStyle.labelLarge?.copyWith(
            fontSize: 13,
            fontWeight: FontWeight.w700,
          ),
          child: Row(
            mainAxisSize: MainAxisSize.min,
            spacing: 6,
            children: [
              SvgPicture.asset(
                'assets/icons/call.svg',
                width: 14,
                height: 14,
                colorFilter: const ColorFilter.mode(
                  AppColors.primary,
                  BlendMode.srcIn,
                ),
              ),
              const Text('Call'),
            ],
          ),
        ),
      ],
    );
  }
}
