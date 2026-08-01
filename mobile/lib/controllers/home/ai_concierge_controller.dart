import 'dart:io';

import 'package:carlton/constants/demo_data.dart';
import 'package:carlton/customWidgets/custom_snackbar.dart';
import 'package:carlton/models/chat_message.dart';
import 'package:carlton/models/conversation.dart';
import 'package:carlton/models/api/api_response.dart';
import 'package:carlton/services/api/api_service.dart';
import 'package:dio/dio.dart';
import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart' hide MultipartFile, FormData, Response;
import 'package:url_launcher/url_launcher.dart';

/// Drives the two-tab chat screen. Tab 0 (Carlton AI Concierge) is an
/// intentional coming-soon stub — the P11 chatbot (`POST /chatbot/message`) is
/// not built server-side for guests, so send() there just shows an info
/// snackbar. Tab 1 (Customer Service) is the real staff chat, wired to
/// `GET /conversations`, `GET /conversations/{uuid}/messages` and
/// `POST /conversations`.
///
/// One long-lived thread → a plain first-page fetch (GetBuilder + [update]),
/// not [PaginatedControllerMixin]: messages are oldest-first top-to-bottom, the
/// view opens at the newest, and there are no live updates (staff replies show
/// on refresh). Agent identity in the header stays demo (the API carries only
/// `sender_type`, no staff name).
class AiConciergeController extends GetxController {
  static const suggestions = DemoData.aiSuggestions;

  static const int _maxAttachmentBytes = 5 * 1024 * 1024; // 5MB (server cap)

  final messageController = TextEditingController();
  final CancelToken _cancel = CancelToken();

  /// 0 = Carlton AI Concierge, 1 = Customer Service.
  int tabIndex = 0;
  bool canSend = false;

  // ── Customer Service thread state ──────────────────────────────────────────
  /// The staff conversation, oldest-first. Empty until loaded / never messaged.
  final List<ChatMessage> messages = [];
  bool loadingThread = false;
  bool threadError = false;
  bool sending = false;

  /// Null when the guest has no conversation yet — the first POST auto-opens one.
  String? conversationUuid;

  /// A picked image awaiting send (null = text-only).
  File? pendingAttachment;

  /// Chat opens at the newest message; jumped to the bottom on load/send.
  final ScrollController threadScrollController = ScrollController();

  @override
  void onInit() {
    super.onInit();
    messageController.addListener(_onTextChanged);
    _loadThread();
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

  // ══════════════════════════════════════════════════════════════════════════
  // Customer Service (tab 1) — real staff chat
  // ══════════════════════════════════════════════════════════════════════════

  /// GET the guest's conversation, then its message history. Tolerates an empty
  /// conversation list (never messaged) by leaving [messages] empty.
  Future<void> _loadThread() async {
    loadingThread = true;
    threadError = false;
    update();

    final res = await ApiService.find.get<List<dynamic>>(
      path: '/conversations',
      showErrorDialog: false,
      cancelToken: _cancel,
    );
    if (isClosed || res.isCancelled) return;
    if (res.statusCode != 200) {
      loadingThread = false;
      threadError = true;
      update();
      return;
    }

    final conversations = Conversation.listFromJson(res.data);
    if (conversations.isEmpty) {
      // Guest hasn't messaged yet — show an empty thread; skip the messages
      // fetch. The first POST /conversations opens the conversation.
      conversationUuid = null;
      messages.clear();
      loadingThread = false;
      update();
      return;
    }

    conversationUuid = conversations.first.uuid;
    final msgRes = await ApiService.find.get<List<dynamic>>(
      path: '/conversations/$conversationUuid/messages',
      showErrorDialog: false,
      cancelToken: _cancel,
    );
    if (isClosed || msgRes.isCancelled) return;
    if (msgRes.statusCode != 200) {
      loadingThread = false;
      threadError = true;
      update();
      return;
    }

    messages
      ..clear()
      ..addAll(ChatMessage.listFromJson(msgRes.data));
    loadingThread = false;
    update();
    _scrollToBottom();
  }

  /// Jump to the newest message after the next frame paints the list.
  void _scrollToBottom() {
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (threadScrollController.hasClients) {
        threadScrollController.jumpTo(
          threadScrollController.position.maxScrollExtent,
        );
      }
    });
  }

  /// Retry / pull-to-refresh — REST is the only source (no live Firestore
  /// mirror wired), so staff replies surface here on demand.
  Future<void> refreshThread() => _loadThread();

  /// Pick a single image (≤5MB) to attach to the next message.
  Future<void> pickAttachment() async {
    final result = await FilePicker.pickFiles(type: FileType.image);
    if (result == null || result.files.isEmpty) return;
    final picked = result.files.first;
    final path = picked.path;
    if (path == null) return;
    if (picked.size > _maxAttachmentBytes) {
      CustomSnackbars.showError(message: 'Image must be 5MB or smaller');
      return;
    }
    pendingAttachment = File(path);
    update();
  }

  void removeAttachment() {
    if (pendingAttachment == null) return;
    pendingAttachment = null;
    update();
  }

  void send() {
    if (tabIndex == 0) {
      // P11 chatbot not built server-side; intentionally unwired.
      CustomSnackbars.showInfo(message: 'AI Concierge is coming soon');
      messageController.clear();
      return;
    }
    _sendToStaff(messageController.text.trim(), pendingAttachment);
  }

  /// Tapping a quick-reply chip POSTs that phrase to staff (not a local echo),
  /// so the team actually receives it.
  void quickReply(String text) => _sendToStaff(text, null);

  Future<void> _sendToStaff(String text, File? attachment) async {
    if (sending) return;
    if (text.isEmpty && attachment == null) return;
    sending = true;
    update();

    final ApiResponse res;
    if (attachment != null) {
      res = await ApiService.find.postWithFiles<Map<String, dynamic>>(
        path: '/conversations',
        fields: text.isEmpty ? null : {'body': text},
        files: {
          'attachment': (
            files: [attachment],
            mime: _mimeForPath(attachment.path),
          ),
        },
        showDialog: false,
        cancelToken: _cancel,
      );
    } else {
      res = await ApiService.find.post<Map<String, dynamic>>(
        path: '/conversations',
        data: {'body': text},
        showErrorDialog: false,
        cancelToken: _cancel,
      );
    }
    if (isClosed || res.isCancelled) return;

    // POST status is undocumented — accept both 200 and 201 as success.
    if (res.statusCode != 200 && res.statusCode != 201) {
      sending = false;
      update();
      if (res.error != null) ApiService.find.dialogs.showError(res.error!);
      return;
    }

    final data = res.data;
    if (data is Map<String, dynamic>) {
      messages.add(ChatMessage.fromJson(data));
      // The created Message may echo its conversation_uuid — capture it so a
      // later refresh has the thread even before re-listing conversations.
      conversationUuid ??= data['conversation_uuid'] as String?;
    }
    messageController.clear();
    pendingAttachment = null;
    sending = false;
    update();
    _scrollToBottom();
  }

  static String _mimeForPath(String path) {
    final lower = path.toLowerCase();
    if (lower.endsWith('.png')) return 'image/png';
    if (lower.endsWith('.webp')) return 'image/webp';
    if (lower.endsWith('.gif')) return 'image/gif';
    return 'image/jpeg';
  }

  Future<void> callAgent() async {
    final uri = Uri(scheme: 'tel', path: DemoData.csPhone);
    if (await canLaunchUrl(uri)) await launchUrl(uri);
  }

  @override
  void onClose() {
    _cancel.cancel();
    messageController.removeListener(_onTextChanged);
    messageController.dispose();
    threadScrollController.dispose();
    super.onClose();
  }
}
