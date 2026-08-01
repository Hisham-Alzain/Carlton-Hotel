import 'dart:io';
import 'package:carlton/components/chat/custom_agent_header.dart';
import 'package:carlton/components/chat/custom_chat_bubble.dart';
import 'package:carlton/constants/demo_data.dart';
import 'package:carlton/controllers/home/ai_concierge_controller.dart';
import 'package:carlton/components/custom_chat_text_field.dart';
import 'package:carlton/components/custom_circle_icon_button.dart';
import 'package:carlton/customWidgets/custom_containers.dart';
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
                expanded: true,
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
              _buildInputBar(controller),
            ],
          ),
        ),
      ),
    );
  }

  /// AI tab → plain text field. Customer Service tab → attachment button +
  /// field, with a pending-image chip above and send gated while sending.
  Widget _buildInputBar(AiConciergeController controller) {
    final isChat = controller.tabIndex == 1;
    final canSend = isChat
        ? (controller.canSend || controller.pendingAttachment != null) &&
              !controller.sending
        : controller.canSend;

    final field = CustomChatTextField(
      controller: controller.messageController,
      canSend: canSend,
      hintText: isChat ? 'Send a message' : 'Ask me anything...',
      onSendTap: controller.send,
    );

    if (!isChat) return field;

    return Column(
      mainAxisSize: MainAxisSize.min,
      spacing: 8,
      children: [
        if (controller.pendingAttachment != null)
          _AttachmentPreview(
            file: controller.pendingAttachment!,
            onRemove: controller.removeAttachment,
          ),
        Row(
          spacing: 8,
          children: [
            _AttachmentButton(
              onTap: controller.sending ? null : controller.pickAttachment,
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

/// Customer Service conversation: agent header + quick replies + message thread
/// with loading / empty / error states (real staff chat).
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
        Expanded(child: _thread()),
      ],
    );
  }

  Widget _thread() {
    if (controller.loadingThread) {
      return const Center(
        child: CircularProgressIndicator(color: AppColors.primary),
      );
    }
    if (controller.threadError) {
      return _ThreadError(onRetry: controller.refreshThread);
    }
    if (controller.messages.isEmpty) {
      return const _ThreadEmpty();
    }
    return RefreshIndicator(
      onRefresh: controller.refreshThread,
      child: ListView.builder(
        controller: controller.threadScrollController,
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.symmetric(vertical: 6),
        itemCount: controller.messages.length,
        itemBuilder: (context, i) => Padding(
          padding: EdgeInsets.only(
            bottom: i == controller.messages.length - 1 ? 0 : 12,
          ),
          child: CustomChatBubble(
            message: controller.messages[i],
            agentInitial: DemoData.csAgentInitial,
          ),
        ),
      ),
    );
  }
}

/// Gentle empty state before the guest's first message (REST auto-opens the
/// conversation on first send).
class _ThreadEmpty extends StatelessWidget {
  const _ThreadEmpty();

  @override
  Widget build(BuildContext context) {
    final TextTheme textStyle = Get.textTheme;
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          spacing: 10,
          children: [
            const Icon(
              Icons.forum_outlined,
              size: 48,
              color: AppColors.silverGrey,
            ),
            Text(
              'Start a conversation with our team',
              textAlign: TextAlign.center,
              style: textStyle.titleSmall?.copyWith(
                color: AppColors.inkBlack,
                fontWeight: FontWeight.w600,
              ),
            ),
            Text(
              'Send a message and Guest Relations will reply here.',
              textAlign: TextAlign.center,
              style: textStyle.labelMedium?.copyWith(color: AppColors.ashGrey),
            ),
          ],
        ),
      ),
    );
  }
}

/// Thread load failed → a retry affordance (no live mirror, so this is the
/// recovery path).
class _ThreadError extends StatelessWidget {
  final Future<void> Function() onRetry;

  const _ThreadError({required this.onRetry});

  @override
  Widget build(BuildContext context) {
    final TextTheme textStyle = Get.textTheme;
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          spacing: 10,
          children: [
            const Icon(
              Icons.wifi_off_rounded,
              size: 48,
              color: AppColors.silverGrey,
            ),
            Text(
              "Couldn't load your messages",
              textAlign: TextAlign.center,
              style: textStyle.titleSmall?.copyWith(
                color: AppColors.inkBlack,
                fontWeight: FontWeight.w600,
              ),
            ),
            TextButton(onPressed: onRetry, child: const Text('Retry')),
          ],
        ),
      ),
    );
  }
}

/// Circular pick-image button shown left of the chat field.
class _AttachmentButton extends StatelessWidget {
  final VoidCallback? onTap;

  const _AttachmentButton({required this.onTap});

  @override
  Widget build(BuildContext context) {
    return Material(
      color: AppColors.whisperGrey,
      shape: const CircleBorder(),
      child: InkWell(
        onTap: onTap,
        customBorder: const CircleBorder(),
        child: SizedBox(
          width: 48,
          height: 48,
          child: Icon(
            Icons.add_photo_alternate_outlined,
            size: 24,
            color: onTap == null ? AppColors.silverGrey : AppColors.primary,
          ),
        ),
      ),
    );
  }
}

/// Thumbnail + remove chip for the image awaiting send.
class _AttachmentPreview extends StatelessWidget {
  final File file;
  final VoidCallback onRemove;

  const _AttachmentPreview({required this.file, required this.onRemove});

  @override
  Widget build(BuildContext context) {
    return Align(
      alignment: Alignment.centerLeft,
      child: Stack(
        clipBehavior: Clip.none,
        children: [
          ClipRRect(
            borderRadius: BorderRadius.circular(12),
            child: Image.file(file, width: 64, height: 64, fit: BoxFit.cover),
          ),
          Positioned(
            top: -6,
            right: -6,
            child: GestureDetector(
              onTap: onRemove,
              child: Container(
                padding: const EdgeInsets.all(2),
                decoration: const BoxDecoration(
                  shape: BoxShape.circle,
                  color: AppColors.inkBlack,
                ),
                child: const Icon(
                  Icons.close,
                  size: 14,
                  color: AppColors.white,
                ),
              ),
            ),
          ),
        ],
      ),
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
            child: PillContainer(
              height: 34,
              backgroundColor: AppColors.white,
              radius: 30,
              padding: const EdgeInsets.symmetric(horizontal: 16),
              border: Border.all(color: AppColors.black10),
              child: Center(
                widthFactor: 1,
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
      ),
    );
  }
}
