/// Bundled media the app ships with. These are real product assets, not
/// placeholder content — they have no backend equivalent and are not expected
/// to gain one.
abstract class AppAssets {
  /// The hotel promo clip (the Figma hero's video fill). If it fails to load,
  /// the hero keeps its poster image ([heroHomeImagePath]).
  static const heroVideoAssetPath = 'assets/videos/carlton_promo.mp4';

  /// Hero stills extracted from the Figma homepage (frames of the promo video).
  /// The top hero shows [heroHomeImagePath] as a poster until the video is
  /// ready.
  static const heroHomeImagePath = 'assets/images/hero_home.png';
  static const heroDiningImagePath = 'assets/images/hero_dining.png';
  static const heroExperienceImagePath = 'assets/images/hero_experience.png';
}
