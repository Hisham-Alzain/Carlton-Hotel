/// Bundled media the app ships with. These are real product assets, not
/// placeholder content — they have no backend equivalent and are not expected
/// to gain one.
abstract class AppAssets {
  /// The hotel promo clip (the Figma hero's video fill). The source file is not
  /// exportable via the Figma API — drop the original MP4 at this path and it
  /// plays automatically; until then [heroVideoUrl] is used, and failing that
  /// the poster image.
  static const heroVideoAssetPath = 'assets/videos/carlton_promo.mp4';

  /// Stand-in hero clip used until the real MP4 is dropped in above.
  static const heroVideoUrl =
      'https://flutter.github.io/assets-for-api-docs/assets/videos/butterfly.mp4';

  /// Hero stills extracted from the Figma homepage (frames of the promo video).
  /// The top hero shows [heroHomeImagePath] as a poster until the video is
  /// ready.
  static const heroHomeImagePath = 'assets/images/hero_home.png';
  static const heroDiningImagePath = 'assets/images/hero_dining.png';
  static const heroExperienceImagePath = 'assets/images/hero_experience.png';
}
