import 'package:carlton/models/chat_message.dart';
import 'package:carlton/models/conversation.dart';
import 'package:carlton/services/notifications_service.dart';
import 'package:flutter_test/flutter_test.dart';

/// Phase 6 — guards the chat DTO mapping + the device-token payload after the
/// Customer Service tab was wired to the real staff-chat API and the CartX
/// push-token shape was replaced. Hermetic: no HTTP, no GetStorage, no Firebase
/// (only pure fromJson + a pure payload builder). Each assertion would fail if
/// the corresponding wiring were reverted.
void main() {
  group('ChatMessage.fromJson', () {
    test('sender_type maps staff→agent, guest→user, unknown→agent', () {
      expect(
        ChatMessage.fromJson(<String, dynamic>{'sender_type': 'staff'}).sender,
        ChatSender.agent,
      );
      expect(
        ChatMessage.fromJson(<String, dynamic>{'sender_type': 'guest'}).sender,
        ChatSender.user,
      );
      // Anything that isn't the guest falls back to the agent side.
      expect(
        ChatMessage.fromJson(<String, dynamic>{'sender_type': 'bot'}).sender,
        ChatSender.agent,
      );
      expect(
        ChatMessage.fromJson(<String, dynamic>{}).sender,
        ChatSender.agent,
      );
    });

    test('body maps to text', () {
      final m = ChatMessage.fromJson(<String, dynamic>{
        'uuid': 'm1',
        'sender_type': 'guest',
        'body': 'Hello there',
        'created_at': '2026-07-20T15:24:00',
      });
      expect(m.uuid, 'm1');
      expect(m.text, 'Hello there');
      expect(m.attachmentUrl, isNull);
    });

    test(
      'attachment-only message: null body → empty text, attachmentUrl set',
      () {
        final m = ChatMessage.fromJson(<String, dynamic>{
          'sender_type': 'guest',
          'body': null,
          'attachment_url': 'chat/att-1.jpg',
          'created_at': '2026-07-20T15:24:00',
        });
        expect(m.text, '');
        expect(m.attachmentUrl, 'chat/att-1.jpg');
      },
    );

    test('created_at ISO parses to createdAt and a non-empty clock label', () {
      final m = ChatMessage.fromJson(<String, dynamic>{
        'sender_type': 'staff',
        'body': 'hi',
        'created_at': '2026-07-20T15:24:00',
      });
      expect(m.createdAt.year, 2026);
      expect(m.createdAt.month, 7);
      expect(m.createdAt.day, 20);
      expect(m.time, matches(RegExp(r'\d{1,2}:\d{2}\s?(AM|PM)')));
    });

    test('missing/invalid created_at falls back to now without throwing', () {
      final before = DateTime.now().subtract(const Duration(seconds: 5));
      final m = ChatMessage.fromJson(<String, dynamic>{
        'sender_type': 'guest',
        'body': 'no timestamp',
      });
      expect(m.createdAt.isAfter(before), isTrue);
      expect(m.time, isNotEmpty);
    });

    test('listFromJson maps a list of message maps oldest-first', () {
      final list = ChatMessage.listFromJson(<dynamic>[
        {'sender_type': 'guest', 'body': 'first'},
        {'sender_type': 'staff', 'body': 'second'},
      ]);
      expect(list, hasLength(2));
      expect(list.first.text, 'first');
      expect(list.first.sender, ChatSender.user);
      expect(list.last.sender, ChatSender.agent);
    });
  });

  group('Conversation.fromJson', () {
    test('parses uuid', () {
      final c = Conversation.fromJson(<String, dynamic>{
        'uuid': 'c-123',
        'status': 'open',
        'last_message_at': '2026-07-20T15:24:00',
        'unread_count': 3,
      });
      expect(c.uuid, 'c-123');
      expect(c.status, 'open');
      expect(c.unreadCount, 3);
      expect(c.lastMessageAt, isNotNull);
    });

    test('tolerates a payload with only uuid (undocumented shape)', () {
      final c = Conversation.fromJson(<String, dynamic>{'uuid': 'c-1'});
      expect(c.uuid, 'c-1');
      expect(c.status, isNull);
      expect(c.lastMessageAt, isNull);
      expect(c.unreadCount, 0);
    });
  });

  group('deviceTokenPayload', () {
    test(
      'builds {token, platform} for each platform (not the CartX shape)',
      () {
        for (final platform in ['ios', 'android', 'web']) {
          final body = deviceTokenPayload('fcm-abc', platform: platform);
          expect(body['token'], 'fcm-abc');
          expect(body['platform'], platform);
          // Reverting to the old CartX {device_token} key must fail here.
          expect(body.containsKey('device_token'), isFalse);
          expect(body.keys.toSet(), {'token', 'platform'});
        }
      },
    );
  });
}
