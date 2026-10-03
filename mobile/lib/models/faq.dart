import 'package:carlton/models/localized.dart';

/// One question/answer pair from `GET /public/faqs`.
///
/// [category] groups the list on the Help screen. It is a plain string, not a
/// [Localized] map — the resource sends `$this->category` unwrapped — so it is
/// used as a grouping key and rendered as-is.
class Faq {
  final String uuid;
  final String category;
  final Localized question;
  final Localized answer;

  const Faq({
    required this.uuid,
    this.category = '',
    this.question = Localized.empty,
    this.answer = Localized.empty,
  });

  factory Faq.fromJson(Map<String, dynamic> json) => Faq(
    uuid: json['uuid'] as String? ?? '',
    // Defensive: the CMS stores some categories as translation maps, so take
    // the active locale's value when one arrives instead of printing a map.
    category: json['category'] is Map
        ? Localized.fromJson(json['category']).value
        : json['category'] as String? ?? '',
    question: Localized.fromJson(json['question']),
    answer: Localized.fromJson(json['answer']),
  );

  static List<Faq> listFromJson(dynamic json) => json is List
      ? json.whereType<Map<String, dynamic>>().map(Faq.fromJson).toList()
      : const [];
}
