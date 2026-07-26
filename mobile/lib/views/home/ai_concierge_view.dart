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
    final TextTheme textStyle = Get.textTheme;

    return CustomScaffold(
      appBar: AppBar(
        iconTheme: IconThemeData(color: AppColors.primary),
        actions: [
          const CustomCircleIconButton(iconPath: 'assets/icons/chats.svg'),
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
                    iconPath: 'assets/icons/badge_logo.svg',
                    label: AppTranslations.aiTabLabel,
                  ),
                  SegmentItem(
                    iconPath: 'assets/icons/chat.svg',
                    label: AppTranslations.customerServiceTabLabel,
                  ),
                ],
              ),
              Expanded(
                child: SingleChildScrollView(
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
                                onPressed: () =>
                                    controller.useSuggestion(suggestion),
                              ),
                            )
                            .toList(),
                      ),
                    ],
                  ),
                ),
              ),
              CustomChatTextField(
                controller: controller.messageController,
                canSend: controller.canSend,
                onSendTap: controller.send,
              ),
            ],
          ),
        ),
      ),
    );
  }
}
