part of 'ai_concierge_controller.dart';

extension ConciergeSend on AiConciergeController {
  /// Pick a single image (≤5MB) to attach to the next message.
  Future<void> pickAttachment() async {
    final picked = await FilePicker.pickFile(type: FileType.image);
    if (picked == null) return;
    final path = picked.path;
    if (path == null) return;
    final size = picked.lengthSync() ?? await picked.length();
    if (isClosed) return;
    if (size > AiConciergeController._maxAttachmentBytes) {
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
            mime: AiConciergeController._mimeForPath(attachment.path),
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
    if (!res.ok) {
      sending.value = false;
      if (res.error != null) ApiService.find.dialogs.showError(res.error!);
      return;
    }

    final data = res.data;
    if (data is Map<String, dynamic>) {
      messages.add(ChatMessage.fromJson(data));
    }
    // The first message opens the conversation, but the created message does
    // not carry its uuid: re-read the conversation list so the next fetch
    // (refresh, older pages) has a real thread to ask for.
    if (conversationUuid == null) await _loadThread();
    messageController.clear();
    pendingAttachment.value = null;
    sending.value = false;
    _scrollToBottom();
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
}
