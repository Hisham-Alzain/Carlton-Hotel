import 'dart:io';

import 'package:carlton/l10n/app_translations.dart';
import 'package:carlton/customWidgets/custom_snackbar.dart';
import 'package:carlton/models/chat_message.dart';
import 'package:carlton/models/conversation.dart';
import 'package:carlton/models/localized.dart';
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
/// One long-lived thread → a plain first-page fetch, not
/// [PaginatedControllerMixin]: messages are oldest-first top-to-bottom, the
/// view opens at the newest, and there are no live updates (staff replies show
/// on refresh).
///
/// The guest-facing chat API carries no staff name — `MessageResource` sends
/// only `sender_type` — so the header identifies the *hotel* rather than
/// inventing an agent: [agentName] and [agentPhone] come from
/// `GET /public/settings`, and the role is translated copy.
class AiConciergeController extends GetxController {
  /// Empty on purpose: the guest chatbot endpoint (`POST /chatbot/message`) does
  /// not exist server-side, so there is nothing for a suggestion chip to send.
  /// Prompts return here when the endpoint does.
  static const List<String> suggestions = <String>[];

  /// Translated prompts for the Customer Service tab. These are real: tapping
  /// one posts it to `POST /conversations` like any typed message, so they are
  /// UI copy (hence l10n) rather than sample data.
  static List<String> get quickReplies => <String>[
    AppTranslations.quickReplyHousekeeping,
    AppTranslations.quickReplyLateCheckout,
    AppTranslations.quickReplyDiningAdvice,
    AppTranslations.quickReplyAirportTransfer,
  ];

  // — Hotel contact details (`GET /public/settings`) ————————
  /// The hotel's own name, shown in the chat header in place of a staff name.
  /// Falls back to the translated brand name until the fetch lands.
  final RxString agentName = AppTranslations.hotelName.obs;

  /// Reception's number, dialled by [callAgent]. Empty until loaded — the call
  /// button then does nothing rather than dialling a made-up number.
  final RxString agentPhone = ''.obs;

  /// First letter of [agentName], for the header avatar and every inbound
  /// bubble. Empty name yields an empty chip rather than a stray glyph.
  String get agentInitial =>
      agentName.value.isEmpty ? '' : agentName.value.characters.first;

  static const int _maxAttachmentBytes = 5 * 1024 * 1024; // 5MB (server cap)

  final messageController = TextEditingController();
  final CancelToken _cancel = CancelToken();

  /// 0 = Carlton AI Concierge, 1 = Customer Service.
  final RxInt tabIndex = 0.obs;
  final RxBool canSend = false.obs;

  // ── Customer Service thread state ──────────────────────────────────────────
  /// The staff conversation, oldest-first. Empty until loaded / never messaged.
  final RxList<ChatMessage> messages = <ChatMessage>[].obs;
  final RxBool loadingThread = false.obs;
  final RxBool threadError = false.obs;
  final RxBool sending = false.obs;

  /// Null when the guest has no conversation yet — the first POST auto-opens one.
  String? conversationUuid;

  /// A picked image awaiting send (null = text-only).
  final Rxn<File> pendingAttachment = Rxn<File>();

  /// Chat opens at the newest message; jumped to the bottom on load/send.
  final ScrollController threadScrollController = ScrollController();

  @override
  void onInit() {
    super.onInit();
    messageController.addListener(_onTextChanged);
    _loadThread();
    _loadContactDetails();
  }

  void _onTextChanged() {
    final hasText = messageController.text.trim().isNotEmpty;
    if (hasText != canSend.value) canSend.value = hasText;
  }

  void switchTab(int index) {
    if (tabIndex.value == index) return;
    tabIndex.value = index;
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
    loadingThread.value = true;
    threadError.value = false;

    final res = await ApiService.find.get<List<dynamic>>(
      path: '/conversations',
      showErrorDialog: false,
      cancelToken: _cancel,
    );
    if (isClosed || res.isCancelled) return;
    if (res.statusCode != 200) {
      loadingThread.value = false;
      threadError.value = true;
      return;
    }

    final conversations = Conversation.listFromJson(res.data);
    if (conversations.isEmpty) {
      // Guest hasn't messaged yet — show an empty thread; skip the messages
      // fetch. The first POST /conversations opens the conversation.
      conversationUuid = null;
      messages.clear();
      loadingThread.value = false;
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
      loadingThread.value = false;
      threadError.value = true;
      return;
    }

    // assignAll, not clear()+addAll(): on an RxList each of those notifies
    // separately, so the thread would rebuild twice (once empty) per load.
    messages.assignAll(ChatMessage.listFromJson(msgRes.data));
    loadingThread.value = false;
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
      CustomSnackbars.showError(message: AppTranslations.imageTooLarge);
      return;
    }
    pendingAttachment.value = File(path);
  }

  void removeAttachment() {
    if (pendingAttachment.value == null) return;
    pendingAttachment.value = null;
  }

  void send() {
    if (tabIndex.value == 0) {
      // P11 chatbot not built server-side; intentionally unwired.
      CustomSnackbars.showInfo(message: AppTranslations.conciergeComingSoon);
      messageController.clear();
      return;
    }
    _sendToStaff(messageController.text.trim(), pendingAttachment.value);
  }

  /// Tapping a quick-reply chip POSTs that phrase to staff (not a local echo),
  /// so the team actually receives it.
  void quickReply(String text) => _sendToStaff(text, null);

  Future<void> _sendToStaff(String text, File? attachment) async {
    if (sending.value) return;
    if (text.isEmpty && attachment == null) return;
    sending.value = true;

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
      sending.value = false;
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
    pendingAttachment.value = null;
    sending.value = false;
    _scrollToBottom();
  }

  static String _mimeForPath(String path) {
    final lower = path.toLowerCase();
    if (lower.endsWith('.png')) return 'image/png';
    if (lower.endsWith('.webp')) return 'image/webp';
    if (lower.endsWith('.gif')) return 'image/gif';
    return 'image/jpeg';
  }

  /// Reads the hotel's public contact block. Unlike every other public index
  /// this endpoint returns a grouped map rather than `{items, meta}` — see
  /// `SiteSettingController::index` — so it is parsed by group here.
  ///
  /// Best-effort and silent: the chat itself works without it, and a failure
  /// leaves the translated brand name in the header and the call button inert.
  Future<void> _loadContactDetails() async {
    final res = await ApiService.find.get<Map<String, dynamic>>(
      path: '/public/settings',
      showErrorDialog: false,
    );
    if (isClosed || !res.ok || res.data == null) return;
    final contact = res.data!['contact'];
    if (contact is Map) {
      // Phone is a plain string in this payload, not a locale map.
      final phone = contact['phone'];
      if (phone is String && phone.trim().isNotEmpty) {
        agentPhone.value = phone.trim();
      }
    }
    final seo = res.data!['seo'];
    if (seo is Map) {
      final title = Localized.fromJson(seo['site_title']).value;
      if (title.isNotEmpty) agentName.value = title;
    }
  }

  Future<void> callAgent() async {
    final number = agentPhone.value;
    // Nothing to dial: the settings fetch has not landed, or the hotel has
    // published no number. Better to say so than to launch a blank dialler.
    if (number.isEmpty) {
      CustomSnackbars.showInfo(message: AppTranslations.callUnavailable);
      return;
    }
    final uri = Uri(scheme: 'tel', path: number);
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
