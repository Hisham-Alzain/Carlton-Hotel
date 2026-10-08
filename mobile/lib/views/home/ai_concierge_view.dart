import 'package:carlton/theme/theme.dart';
import 'dart:io';
import 'package:carlton/components/chat/custom_agent_header.dart';
import 'package:carlton/components/chat/custom_chat_bubble.dart';
import 'package:carlton/controllers/home/ai_concierge_controller.dart';
import 'package:carlton/components/custom_chat_text_field.dart';
import 'package:carlton/components/custom_circle_icon_button.dart';
import 'package:carlton/customWidgets/custom_containers.dart';
import 'package:carlton/customWidgets/custom_empty_placeholder.dart';
import 'package:carlton/customWidgets/custom_scaffold.dart';
import 'package:carlton/customWidgets/custom_segmented_button.dart';
import 'package:carlton/l10n/app_translations.dart';
import 'package:carlton/models/segement_item.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:carlton/customWidgets/custom_indicators.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';

part 'ai_concierge_widgets.dart';

class AiConciergeView extends GetView<AiConciergeController> {
  const AiConciergeView({super.key});

  @override
  Widget build(BuildContext context) {
    return CustomScaffold(
      appBar: AppBar(
        iconTheme: const IconThemeData(color: AppColors.primary),
        title: Obx(
          () => Text(
            controller.tabIndex.value == 0
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
      // Three separate observers. The input bar is the reason: `canSend` flips
      // on every keystroke, and a single body-wide observer would repaint the
      // whole message thread per character typed.
      body: Padding(
        padding: const EdgeInsets.all(10),
        child: Column(
          spacing: 10,
          children: [
            Obx(
              () => CustomSegmentedButton.track(
                expanded: true,
                selectedIndex: controller.tabIndex.value,
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
            ),
            Expanded(
              child: Obx(
                () => controller.tabIndex.value == 0
                    ? const _AiTab()
                    : const _CustomerServiceTab(),
              ),
            ),
            Obx(() => _buildInputBar(controller)),
          ],
        ),
      ),
    );
  }

  /// AI tab → plain text field. Customer Service tab → attachment button +
  /// field, with a pending-image chip above and send gated while sending.
  Widget _buildInputBar(AiConciergeController controller) {
    final isChat = controller.tabIndex.value == 1;
    final attachment = controller.pendingAttachment.value;
    final canSend = isChat
        ? (controller.canSend.value || attachment != null) &&
              !controller.sending.value
        : controller.canSend.value;

    final field = CustomChatTextField(
      controller: controller.messageController,
      canSend: canSend,
      hintText: isChat
          ? AppTranslations.sendAMessage
          : AppTranslations.askMeAnything,
      onSendTap: controller.send,
    );

    if (!isChat) return field;

    return Column(
      mainAxisSize: MainAxisSize.min,
      spacing: 8,
      children: [
        if (attachment != null)
          _AttachmentPreview(
            file: attachment,
            onRemove: controller.removeAttachment,
          ),
        Row(
          spacing: 8,
          children: [
            _AttachmentButton(
              onTap: controller.sending.value
                  ? null
                  : controller.pickAttachment,
            ),
            Expanded(child: field),
          ],
        ),
      ],
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
        spacing: 20,
        children: [
          Image.asset(
            'assets/images/aimg.png',
            width: 150,
            fit: BoxFit.contain,
          ),
          Column(
            spacing: 10,
            children: [
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
        ],
      ),
    );
  }
}

/// Customer Service conversation: agent header + quick replies + message thread
/// with loading / empty / error states (real staff chat).
class _CustomerServiceTab extends StatelessWidget {
  const _CustomerServiceTab();

  @override
  Widget build(BuildContext context) {
    final controller = Get.find<AiConciergeController>();

    return Column(
      spacing: 10,
      children: [
        // Scoped: the hotel's name arrives with the settings fetch, after this
        // tab can already be on screen.
        Obx(
          () => CustomAgentHeader(
            name: controller.agentName.value,
            role: AppTranslations.guestRelations,
            initial: controller.agentInitial,
            onCall: controller.callAgent,
          ),
        ),
        const Divider(height: 1, thickness: 1, color: AppColors.black06),
        _QuickReplies(controller: controller),
        // Only the message list observes the thread — the agent header and the
        // quick-reply chips above it are static for the tab's lifetime.
        Expanded(child: Obx(() => _thread(controller))),
      ],
    );
  }

  Widget _thread(AiConciergeController controller) {
    if (controller.loadingThread.value) {
      return const Center(child: LogoLoadingIndicator(size: 50));
    }
    // No live mirror, so Retry is the recovery path.
    if (controller.threadError.value) {
      return CustomEmptyPlaceholder.loadFailed(
        title: AppTranslations.loadMessagesFailed,
        onRetry: controller.refreshThread,
      );
    }
    // Before the guest's first message (REST opens the conversation on the
    // first send).
    if (controller.messages.isEmpty) {
      return CustomEmptyPlaceholder(
        iconWidget: const Icon(
          Icons.forum_outlined,
          size: 48,
          color: AppColors.silverGrey,
        ),
        title: AppTranslations.startConversation,
        subtitle: AppTranslations.guestRelationsWillReply,
        titleColor: AppColors.inkBlack,
        subtitleColor: AppColors.ashGrey,
      );
    }
    // While an older page loads, a spinner sits above the oldest message.
    final older = controller.loadingOlder.value ? 1 : 0;
    return RefreshIndicator(
      onRefresh: controller.refreshThread,
      child: ListView.builder(
        controller: controller.threadScrollController,
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.symmetric(vertical: 6),
        itemCount: controller.messages.length + older,
        itemBuilder: (context, index) {
          if (index < older) {
            return const Padding(
              padding: EdgeInsets.only(bottom: 12),
              child: Center(child: LogoLoadingIndicator(size: 28)),
            );
          }
          final i = index - older;
          return Padding(
            padding: EdgeInsets.only(
              bottom: i == controller.messages.length - 1 ? 0 : 12,
            ),
            child: CustomChatBubble(
              message: controller.messages[i],
              agentInitial: controller.agentInitial,
            ),
          );
        },
      ),
    );
  }
}
