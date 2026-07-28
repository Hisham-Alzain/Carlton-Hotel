import 'package:intl/intl.dart';

enum ChatSender { agent, user }

/// One message in the guest ↔ staff support conversation
/// (`GET /conversations/{uuid}/messages`, `POST /conversations`).
///
/// Keeps the view-facing trio ([sender]/[text]/[time]) the Figma bubble reads,
/// and adds the wire fields the API carries ([uuid]/[attachmentUrl]/[createdAt]).
/// The message shape carries only `sender_type` — no staff name/avatar — so the
/// agent identity in the header stays demo (see [ChatSender.agent]).
class ChatMessage {
  final String uuid;
  final ChatSender sender;
  final String text;

  /// Pre-formatted `h:mm a` clock label derived from [createdAt].
  final String time;

  /// Image attachment URL (storage-relative or absolute), null for text-only.
  final String? attachmentUrl;
  final DateTime createdAt;

  const ChatMessage({
    this.uuid = '',
    required this.sender,
    required this.text,
    required this.time,
    this.attachmentUrl,
    required this.createdAt,
  });

  static final DateFormat _clock = DateFormat('h:mm a');

  factory ChatMessage.fromJson(Map<String, dynamic> json) {
    final created =
        DateTime.tryParse(json['created_at'] as String? ?? '') ??
        DateTime.now();
    // Anything that isn't the guest is rendered as the agent (unknown → agent).
    final sender = (json['sender_type'] as String?) == 'guest'
        ? ChatSender.user
        : ChatSender.agent;
    return ChatMessage(
      uuid: json['uuid'] as String? ?? '',
      sender: sender,
      text: (json['body'] as String?) ?? '',
      attachmentUrl: json['attachment_url'] as String?,
      createdAt: created,
      time: _clock.format(created.toLocal()),
    );
  }

  /// Parses a `GET /conversations/{uuid}/messages` page (envelope already
  /// unwrapped to the items list), oldest-first.
  static List<ChatMessage> listFromJson(dynamic data) => data is List
      ? data
            .whereType<Map<String, dynamic>>()
            .map(ChatMessage.fromJson)
            .toList()
      : const [];
}
