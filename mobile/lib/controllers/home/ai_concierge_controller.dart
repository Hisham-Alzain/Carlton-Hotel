import 'package:carlton/constants/demo_data.dart';
import 'package:carlton/customWidgets/custom_snackbar.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';

/// Demo-only: the Carlton AI Concierge chat. Figma AI1 (2080:751) and AI2
/// (2080:682) are one screen in two input states — AI2 is the empty state
/// (placeholder + disabled send), AI1 is the typed state (text + active send).
/// The [canSend] flag drives that switch reactively as the user types.
class AiConciergeController extends GetxController {
  static const suggestions = DemoData.aiSuggestions;

  final messageController = TextEditingController();

  /// 0 = Carlton AI Concierge, 1 = Customer Service.
  int tabIndex = 0;
  bool canSend = false;

  @override
  void onInit() {
    super.onInit();
    messageController.addListener(_onTextChanged);
  }

  void _onTextChanged() {
    final hasText = messageController.text.trim().isNotEmpty;
    if (hasText != canSend) {
      canSend = hasText;
      update();
    }
  }

  void switchTab(int index) {
    if (tabIndex == index) return;
    tabIndex = index;
    update();
  }

  void useSuggestion(String text) {
    messageController.text = text;
    messageController.selection = TextSelection.fromPosition(
      TextPosition(offset: text.length),
    );
  }

  void send() {
    if (!canSend) return;
    // Demo-only: no AI backend yet.
    CustomSnackbars.showInfo(message: 'AI Concierge is coming soon');
    messageController.clear();
  }

  @override
  void onClose() {
    messageController.removeListener(_onTextChanged);
    messageController.dispose();
    super.onClose();
  }
}
