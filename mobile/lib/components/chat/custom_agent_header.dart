import 'package:carlton/components/custom_initial_avatar.dart';
import 'package:carlton/customWidgets/custom_pill_button.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
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
        CustomPillButton(
          label: 'Call',
          onTap: onCall,
          iconAsset: 'assets/icons/call.svg',
          backgroundColor: AppColors.snowGrey,
          foregroundColor: AppColors.primary,
          borderColor: AppColors.black06,
          radius: 30,
          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
          iconSize: 14,
          fontSize: 13,
          fontWeight: FontWeight.w700,
        ),
      ],
    );
  }
}
