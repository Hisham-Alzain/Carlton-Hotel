enum ChatSender { agent, user }

/// One message in the Customer Service conversation (Figma "Customer Service").
class ChatMessage {
  final ChatSender sender;
  final String text;
  final String time;

  const ChatMessage({
    required this.sender,
    required this.text,
    required this.time,
  });
}
