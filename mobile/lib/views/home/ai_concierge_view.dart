import 'package:carlton/components/chat/custom_agent_header.dart';
import 'package:carlton/components/chat/custom_chat_bubble.dart';
import 'package:carlton/constants/demo_data.dart';
import 'package:carlton/controllers/home/ai_concierge_controller.dart';
import 'package:carlton/components/custom_chat_text_field.dart';
import 'package:carlton/components/custom_circle_icon_button.dart';
import 'package:carlton/customWidgets/custom_scaffold.dart';
import 'package:carlton/customWidgets/custom_segmented_button.dart';
import 'package:carlton/l10n/app_translations.dart';
import 'package:carlton/models/segement_item.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';

class AiConciergeView extends GetView<AiConciergeController> {
  const AiConciergeView({super.key});

  @override
  Widget build(BuildContext context) {
    return CustomScaffold(
      appBar: AppBar(
        iconTheme: const IconThemeData(color: AppColors.primary),
        title: GetBuilder<AiConciergeController>(
          builder: (_) => Text(
            controller.tabIndex == 0
                ? AppTranslations.aiTabLabel
                : AppTranslations.customerServiceTabLabel,
            style: Get.textTheme.titleMedium?.copyWith(
              fontWeight: FontWeight.w600,
              color: AppColors.inkBlack,
            ),
          ),
        ),
        actions: [
          const CustomCircleIconButton(
            iconPath: 'assets/icons/chats_header.svg',
          ),
        ],
      ),
      body: GetBuilder<AiConciergeController>(
        builder: (_) => Padding(
          padding: const EdgeInsets.all(10),
          child: Column(
            spacing: 10,
            children: [
              CustomSegmentedButton.track(
                selectedIndex: controller.tabIndex,
                onChanged: controller.switchTab,
                segments: [
                  SegmentItem(
                    iconPath: 'assets/icons/tab_ai.svg',
                    label: AppTranslations.aiTabLabel,
                  ),
                  SegmentItem(
                    iconPath: 'assets/icons/tab_service.svg',
                    label: AppTranslations.customerServiceTabLabel,
                  ),
                ],
              ),
              Expanded(
                child: controller.tabIndex == 0
                    ? const _AiTab()
                    : _CustomerServiceTab(controller: controller),
              ),
              CustomChatTextField(
                controller: controller.messageController,
                canSend: controller.canSend,
                hintText: controller.tabIndex == 1
                    ? 'Send a message'
                    : 'Ask me anything...',
                onSendTap: controller.send,
              ),
            ],
          ),
        ),
      ),
    );
  }
}

/// AI Concierge empty state: illustration, greeting, and suggestion chips.
class _AiTab extends StatelessWidget {
  const _AiTab();

  @override
  Widget build(BuildContext context) {
    final TextTheme textStyle = Get.textTheme;

    return SingleChildScrollView(
      padding: const EdgeInsets.all(10),
      child: Column(
        spacing: 10,
        children: [
          Padding(
            padding: const EdgeInsets.only(bottom: 10),
            child: Image.asset(
              'assets/images/aimg.png',
              width: 150,
              fit: BoxFit.contain,
            ),
          ),
          Text(
            AppTranslations.howMayIAssist,
            textAlign: TextAlign.center,
            style: textStyle.headlineSmall?.copyWith(
              fontWeight: FontWeight.w500,
            ),
          ),
          Text(
            AppTranslations.helpDescription,
            textAlign: TextAlign.center,
            style: textStyle.labelMedium?.copyWith(
              color: AppColors.ashGrey,
              fontWeight: FontWeight.w400,
            ),
          ),
          Wrap(
            spacing: 10,
            runSpacing: 10,
            alignment: WrapAlignment.center,
            children: AiConciergeController.suggestions
                .map(
                  (suggestion) => ActionChip(
                    label: Text(suggestion),
                    onPressed: () => Get.find<AiConciergeController>()
                        .useSuggestion(suggestion),
                  ),
                )
                .toList(),
          ),
        ],
      ),
    );
  }
}

/// Customer Service conversation: agent header + message thread.
class _CustomerServiceTab extends StatelessWidget {
  final AiConciergeController controller;

  const _CustomerServiceTab({required this.controller});

  @override
  Widget build(BuildContext context) {
    return Column(
      spacing: 10,
      children: [
        CustomAgentHeader(
          name: DemoData.csAgentName,
          role: DemoData.csAgentRole,
          initial: DemoData.csAgentInitial,
          onCall: controller.callAgent,
        ),
        const Divider(height: 1, thickness: 1, color: AppColors.black06),
        _QuickReplies(controller: controller),
        Expanded(
          child: ListView.separated(
            padding: const EdgeInsets.symmetric(vertical: 6),
            itemCount: controller.messages.length,
            separatorBuilder: (_, _) => const SizedBox(height: 12),
            itemBuilder: (context, i) => CustomChatBubble(
              message: controller.messages[i],
              agentInitial: DemoData.csAgentInitial,
            ),
          ),
        ),
      ],
    );
  }
}

/// Quick-reply chips at the top of the Customer Service tab: white outlined
/// pills with dark text (Figma).
class _QuickReplies extends StatelessWidget {
  final AiConciergeController controller;

  const _QuickReplies({required this.controller});

  @override
  Widget build(BuildContext context) {
    final TextTheme textStyle = Get.textTheme;

    return SizedBox(
      height: 34,
      child: ListView.separated(
        scrollDirection: Axis.horizontal,
        itemCount: DemoData.csQuickReplies.length,
        separatorBuilder: (_, _) => const SizedBox(width: 8),
        itemBuilder: (context, i) => Material(
          color: AppColors.white,
          borderRadius: BorderRadius.circular(30),
          child: InkWell(
            onTap: () => controller.quickReply(DemoData.csQuickReplies[i]),
            borderRadius: BorderRadius.circular(30),
            child: Container(
              alignment: Alignment.center,
              padding: const EdgeInsets.symmetric(horizontal: 16),
              decoration: BoxDecoration(
                borderRadius: BorderRadius.circular(30),
                border: Border.all(color: AppColors.black10),
              ),
              child: Text(
                DemoData.csQuickReplies[i],
                style: textStyle.labelMedium?.copyWith(
                  fontFamily: 'DM Sans',
                  color: AppColors.inkBlack,
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}
