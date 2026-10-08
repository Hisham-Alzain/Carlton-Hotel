part of 'ai_concierge_controller.dart';

extension ConciergeThread on AiConciergeController {
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
    if (!res.ok) {
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
    // Page 1 is the oldest; its meta says where the newest page is.
    final first = await _fetchMessages(1);
    if (isClosed) return;
    if (first == null) {
      loadingThread.value = false;
      threadError.value = true;
      return;
    }
    var loaded = first.items;
    var oldest = 1;
    final lastPage = first.pagination.lastPage;
    if (lastPage > 1) {
      final newest = await _fetchMessages(lastPage);
      if (isClosed) return;
      if (newest == null) {
        loadingThread.value = false;
        threadError.value = true;
        return;
      }
      loaded = newest.items;
      oldest = lastPage;
      // A nearly empty last page would not fill the screen, so the scroll
      // that loads older pages could never happen: take the one before too.
      if (newest.items.length < first.pagination.perPage) {
        final previous = lastPage - 1 == 1
            ? first.items
            : (await _fetchMessages(lastPage - 1))?.items;
        if (isClosed) return;
        if (previous != null) {
          loaded = [...previous, ...loaded];
          oldest = lastPage - 1;
        }
      }
    }
    _oldestPage = oldest;

    // assignAll, not clear()+addAll(): on an RxList each of those notifies
    // separately, so the thread would rebuild twice (once empty) per load.
    messages.assignAll(loaded);
    loadingThread.value = false;
    _scrollToBottom();
  }

  void _onThreadScroll() {
    if (!threadScrollController.hasClients) return;
    if (threadScrollController.position.pixels <= 80) loadOlder();
  }

  /// Prepends the next older page, keeping the guest's place: the distance
  /// from the bottom is held, so the messages they were reading do not jump.
  Future<void> loadOlder() async {
    if (!hasOlder || loadingOlder.value || loadingThread.value) return;
    loadingOlder.value = true;
    final page = await _fetchMessages(_oldestPage - 1);
    if (isClosed) return;
    loadingOlder.value = false;
    if (page == null) return;
    final fromBottom = threadScrollController.hasClients
        ? threadScrollController.position.maxScrollExtent -
              threadScrollController.position.pixels
        : 0.0;
    _oldestPage -= 1;
    messages.insertAll(0, page.items);
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (!threadScrollController.hasClients) return;
      threadScrollController.jumpTo(
        threadScrollController.position.maxScrollExtent - fromBottom,
      );
    });
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
}
