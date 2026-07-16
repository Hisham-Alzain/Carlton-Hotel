import 'package:carlton/controllers/home/ai_concierge_controller.dart';
import 'package:carlton/components/custom_chat_input_bar.dart';
import 'package:carlton/components/custom_chip.dart';
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
      body: SafeArea(
        child: GetBuilder<AiConciergeController>(
          builder: (_) => Column(
            children: [
              _topBar(),
              CustomSegmentedButton.track(
                selectedIndex: controller.tabIndex,
                onChanged: controller.switchTab,
                segments: [
                  SegmentItem(
                    iconPath: 'assets/icons/logo.svg',
                    label: AppTranslations.aiTabLabel,
                  ),
                  SegmentItem(
                    iconData: Icons.headset_mic_outlined,
                    label: AppTranslations.customerServiceTabLabel,
                  ),
                ],
              ),
              Expanded(
                child: SingleChildScrollView(
                  padding: const EdgeInsets.fromLTRB(20, 40, 20, 16),
                  child: Column(
                    spacing: 39,
                    children: [_welcome(), _suggestions()],
                  ),
                ),
              ),
              Padding(
                padding: const EdgeInsets.only(bottom: 12),
                child: CustomChatInputBar(
                  controller: controller.messageController,
                  canSend: controller.canSend,
                  onSend: controller.send,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _topBar() {
    return Padding(
      // Bottom 16 is the gap to the segmented toggle below.
      padding: const EdgeInsets.fromLTRB(20, 14, 20, 16),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          GestureDetector(
            onTap: Get.back,
            behavior: HitTestBehavior.opaque,
            child: const Icon(Icons.arrow_back, size: 26),
          ),
          const CustomCircleIconButton(
            icon: Icon(
              Icons.chat_bubble_outline,
              size: 22,
              color: AppColors.primary,
            ),
          ),
        ],
      ),
    );
  }

  Widget _welcome() {
    return Column(
      spacing: 16,
      children: [
        Padding(
          padding: const EdgeInsets.only(bottom: 23),
          child: Image.asset(
            'assets/images/aimg.png',
            width: 153,
            fit: BoxFit.contain,
          ),
        ),
        Text(
          AppTranslations.howMayIAssist,
          textAlign: TextAlign.center,
          style: const TextStyle(
            fontSize: 23,
            fontWeight: FontWeight.w500,
            color: Colors.black,
          ),
        ),
        Text(
          AppTranslations.helpDescription,
          textAlign: TextAlign.center,
          style: const TextStyle(fontSize: 12, color: AppColors.textSecondary),
        ),
      ],
    );
  }

  Widget _suggestions() {
    return Wrap(
      spacing: 6,
      runSpacing: 13,
      alignment: WrapAlignment.center,
      children: [
        for (final suggestion in AiConciergeController.suggestions)
          CustomChip.action(
            label: suggestion,
            onTap: () => controller.useSuggestion(suggestion),
          ),
      ],
    );
  }
}
