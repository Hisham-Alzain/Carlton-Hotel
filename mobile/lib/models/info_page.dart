import 'package:carlton/models/localized.dart';

/// A CMS long-form page from `GET /public/pages/{slug}` — the Legal screen's
/// content. Fetched by slug, so the slugs the app asks for are declared in one
/// place: [InfoPageSlug].
class InfoPage {
  final String slug;
  final Localized title;
  final Localized content;

  const InfoPage({
    required this.slug,
    this.title = Localized.empty,
    this.content = Localized.empty,
  });

  factory InfoPage.fromJson(Map<String, dynamic> json) => InfoPage(
    slug: json['slug'] as String? ?? '',
    title: Localized.fromJson(json['title']),
    content: Localized.fromJson(json['content']),
  );
}

/// The slugs the app requests. These must exist in the CMS `pages` table —
/// they are seeded by `CmsContentSeeder::pages()`. A slug the CMS does not have
/// 404s, which the Legal screen reports rather than showing a blank page.
abstract class InfoPageSlug {
  static const terms = 'terms-and-conditions';
  static const privacy = 'privacy-policy';
  static const about = 'about-us';

  /// Rendered in this order on the Legal screen.
  static const all = <String>[terms, privacy, about];
}
