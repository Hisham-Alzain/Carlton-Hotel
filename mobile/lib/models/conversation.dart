/// The single guest support conversation (`GET /conversations`). In practice a
/// guest has exactly one; the list may also be **empty** (guest never messaged),
/// in which case the controller skips the messages fetch and lets the first
/// `POST /conversations` auto-open one.
///
/// Only [uuid] is documented/load-bearing (the controller reads it to fetch
/// messages). [status]/[lastMessageAt]/[unreadCount] are undocumented guesses
/// parsed defensively — never branch logic on them.
class Conversation {
  final String uuid;
  final String? status;
  final DateTime? lastMessageAt;
  final int unreadCount;

  const Conversation({
    this.uuid = '',
    this.status,
    this.lastMessageAt,
    this.unreadCount = 0,
  });

  static DateTime? _date(dynamic v) =>
      v is String && v.isNotEmpty ? DateTime.tryParse(v) : null;

  factory Conversation.fromJson(Map<String, dynamic> json) => Conversation(
    uuid: json['uuid'] as String? ?? '',
    status: json['status'] as String?,
    lastMessageAt: _date(json['last_message_at']),
    unreadCount: (json['unread_count'] as num?)?.toInt() ?? 0,
  );

  /// Parses a `GET /conversations` page (envelope already unwrapped to items).
  static List<Conversation> listFromJson(dynamic data) => data is List
      ? data
            .whereType<Map<String, dynamic>>()
            .map(Conversation.fromJson)
            .toList()
      : const [];
}
