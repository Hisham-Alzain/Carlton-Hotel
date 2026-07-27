import 'package:carlton/components/custom_initial_avatar.dart';
import 'package:carlton/models/chat_message.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';

/// One chat message: agent messages sit left with the agent avatar and a light
/// white bubble; the guest's sit right in a lagoon-teal bubble. Colours match
/// the Figma Customer Service screen.
class CustomChatBubble extends StatelessWidget {
  final ChatMessage message;

  /// Initial shown in the agent avatar (guest messages carry no avatar).
  final String agentInitial;

  const CustomChatBubble({
    required this.message,
    required this.agentInitial,
    super.key,
  });

  @override
  Widget build(BuildContext context) {
    final TextTheme textStyle = Get.textTheme;
    final bool isAgent = message.sender == ChatSender.agent;

    final bubble = Flexible(
      child: Column(
        crossAxisAlignment: isAgent
            ? CrossAxisAlignment.start
            : CrossAxisAlignment.end,
        spacing: 4,
        children: [
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
            decoration: BoxDecoration(
              color: isAgent ? AppColors.white : AppColors.lagoonTeal,
              border: isAgent ? Border.all(color: AppColors.linenGrey) : null,
              boxShadow: isAgent
                  ? const [
                      BoxShadow(
                        color: AppColors.black06,
                        blurRadius: 8,
                        offset: Offset(0, 2),
                      ),
                    ]
                  : null,
              borderRadius: BorderRadius.only(
                topLeft: const Radius.circular(14),
                topRight: const Radius.circular(14),
                bottomLeft: Radius.circular(isAgent ? 4 : 14),
                bottomRight: Radius.circular(isAgent ? 14 : 4),
              ),
            ),
            child: Text(
              message.text,
              style: textStyle.labelMedium?.copyWith(
                fontFamily: 'DM Sans',
                height: 1.4,
                color: isAgent ? AppColors.inkBlack : AppColors.white,
              ),
            ),
          ),
          Text(
            message.time,
            style: textStyle.labelSmall?.copyWith(
              fontFamily: 'DM Sans',
              color: AppColors.taupeBrown,
            ),
          ),
        ],
      ),
    );

    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      mainAxisAlignment: isAgent
          ? MainAxisAlignment.start
          : MainAxisAlignment.end,
      spacing: 8,
      children: isAgent
          ? [
              CustomInitialAvatar(
                initial: agentInitial,
                size: 32,
                backgroundColor: AppColors.dustyTeal,
              ),
              bubble,
            ]
          : [bubble],
    );
  }
}
