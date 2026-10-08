import 'dart:io';
import 'package:carlton/l10n/app_translations.dart';
import 'package:carlton/customWidgets/custom_snackbar.dart';
import 'package:carlton/models/chat_message.dart';
import 'package:carlton/models/conversation.dart';
import 'package:carlton/models/localized.dart';
import 'package:carlton/models/pagination.dart';
import 'package:carlton/models/api/api_response.dart';
import 'package:carlton/services/api/api_service.dart';
import 'package:dio/dio.dart';
import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart' hide MultipartFile, FormData, Response;
import 'package:url_launcher/url_launcher.dart';

part 'ai_concierge_send.dart';

part 'ai_concierge_thread.dart';

/// Drives the two-tab chat screen. Tab 0 (Carlton AI Concierge) is an
/// intentional coming-soon stub — the P11 chatbot (`POST /chatbot/message`) is
/// not built server-side for guests, so send() there just shows an info
/// snackbar. Tab 1 (Customer Service) is the real staff chat, wired to
/// `GET /conversations`, `GET /conversations/{uuid}/messages` and
/// `POST /conversations`.
///
/// One long-lived thread, paged backwards rather than through
/// [PaginatedControllerMixin]: the API pages oldest-first, the view opens at the
/// newest, so the thread loads the *last* page first and older pages as the
/// guest scrolls up. No live updates (staff replies show on refresh).
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

  /// The oldest message page on screen. Pages below it are older and load
  /// when the guest scrolls to the top.
  int _oldestPage = 1;
  final RxBool loadingOlder = false.obs;
  bool get hasOlder => _oldestPage > 1;

  @override
  void onInit() {
    super.onInit();
    messageController.addListener(_onTextChanged);
    threadScrollController.addListener(_onThreadScroll);
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

  /// One page of the open conversation, or null on failure.
  Future<({List<ChatMessage> items, Pagination pagination})?> _fetchMessages(
    int page,
  ) async {
    final res = await ApiService.find.get<List<dynamic>>(
      path: '/conversations/$conversationUuid/messages',
      queryParameters: {'page': page},
      showErrorDialog: false,
      cancelToken: _cancel,
    );
    if (res.isCancelled || !res.ok) return null;
    return (
      items: ChatMessage.listFromJson(res.data),
      pagination: res.meta ?? Pagination(),
    );
  }

  static String _mimeForPath(String path) {
    final lower = path.toLowerCase();
    if (lower.endsWith('.png')) return 'image/png';
    if (lower.endsWith('.webp')) return 'image/webp';
    if (lower.endsWith('.gif')) return 'image/gif';
    return 'image/jpeg';
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
