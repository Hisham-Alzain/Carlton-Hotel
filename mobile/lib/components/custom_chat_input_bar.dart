import 'package:carlton/components/custom_circle_icon_button.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';

class CustomChatInputBar extends StatelessWidget {
  final TextEditingController controller;
  final bool canSend;
  final VoidCallback onSend;
  final String hintText;

  const CustomChatInputBar({
    required this.controller,
    required this.canSend,
    required this.onSend,
    this.hintText = 'Ask me anything...',
    super.key,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.symmetric(horizontal: 20),
      height: 56,
      padding: const EdgeInsets.only(left: 22, right: 9),
      decoration: BoxDecoration(
        color: AppColors.greyField,
        borderRadius: BorderRadius.circular(50),
        border: Border.all(color: Colors.white, width: 1.2),
        boxShadow: const [
          BoxShadow(
            color: Color(0x409B9B9B),
            blurRadius: 2,
            offset: Offset(0, 1),
          ),
        ],
      ),
      child: Row(
        spacing: 12,
        children: [
          Expanded(
            child: TextField(
              controller: controller,
              onSubmitted: (_) => onSend(),
              style: const TextStyle(fontSize: 13, color: AppColors.inkSoft),
              decoration: InputDecoration(
                isCollapsed: true,
                border: InputBorder.none,
                hintText: hintText,
                hintStyle: const TextStyle(
                  fontSize: 13,
                  color: AppColors.inkSoftHint,
                ),
              ),
            ),
          ),
          const Icon(Icons.mic_none, size: 21, color: AppColors.inkSoft),
          CustomCircleIconButton(
            onTap: onSend,
            icon: const Icon(Icons.arrow_upward, size: 22, color: Colors.white),
            color: canSend ? null : AppColors.disabled,
            gradient: canSend
                ? const LinearGradient(
                    begin: Alignment.topCenter,
                    end: Alignment.bottomCenter,
                    colors: [AppColors.primary, AppColors.tealSoft],
                  )
                : null,
          ),
        ],
      ),
    );
  }
}
