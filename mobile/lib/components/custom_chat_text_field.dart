import 'package:carlton/services/settings_service.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';

class CustomChatTextField extends StatelessWidget {
  final TextEditingController controller;
  final String? hintText;
  final double? height;
  final bool canSend;
  final void Function(String)? onChanged;
  final void Function(String)? onSubmitted;
  final VoidCallback? onMicTap;
  final VoidCallback? onSendTap;

  const CustomChatTextField({
    required this.controller,
    this.hintText,
    this.height = 60,
    required this.canSend,
    this.onChanged,
    this.onSubmitted,
    this.onMicTap,
    this.onSendTap,
    super.key,
  });
  @override
  Widget build(BuildContext context) {
    final theme = Get.theme;

    final inputStyle = theme.textTheme.bodyLarge?.copyWith(
      color: AppColors.espressoInk,
      fontWeight: FontWeight.w400,
    );
    final hintStyle = theme.textTheme.bodyLarge?.copyWith(
      color: AppColors.espressoInk50,
      fontWeight: FontWeight.w400,
    );

    // Radius larger than half the field height forces a full pill.
    OutlineInputBorder pillBorder(Color color) => OutlineInputBorder(
      borderRadius: BorderRadius.circular(height!),
      borderSide: BorderSide(width: 1.5, color: color),
    );

    return SizedBox(
      height: height,
      child: TextField(
        controller: controller,
        style: inputStyle,
        onChanged: onChanged,
        onSubmitted: onSubmitted,
        textDirection: SettingsService.find.locale.value.languageCode == 'ar'
            ? TextDirection.rtl
            : TextDirection.ltr,
        decoration: InputDecoration(
          filled: true,
          fillColor: AppColors.whisperGrey,
          hintText: hintText ?? 'Ask me anything...',
          hintStyle: hintStyle,
          border: pillBorder(Colors.white),
          enabledBorder: pillBorder(Colors.white),
          focusedBorder: pillBorder(AppColors.primary),
          suffixIcon: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              // IconButton(
              //   icon: const Icon(Icons.mic_none),
              //   color: AppColors.espressoInk50,
              //   onPressed: onMicTap,
              // ),
              GestureDetector(
                onTap: onSendTap,
                child: Container(
                  margin: const EdgeInsets.all(10),
                  width: 40,
                  height: 40,
                  decoration: BoxDecoration(
                    shape: BoxShape.circle,
                    color: canSend ? AppColors.primary : AppColors.silverGrey,
                  ),
                  child: const Icon(
                    Icons.arrow_upward,
                    color: Colors.white,
                    size: 24,
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
