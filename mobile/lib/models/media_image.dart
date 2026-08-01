/// An uploaded image with its ordering + filename metadata. Reused by every
/// content DTO that carries a photo gallery.
class MediaImage {
  final String uuid;
  final String url;
  final String fileName;
  final int sortOrder;

  const MediaImage({
    required this.uuid,
    required this.url,
    required this.fileName,
    required this.sortOrder,
  });

  factory MediaImage.fromJson(Map<String, dynamic> json) {
    return MediaImage(
      uuid: json['uuid'] as String? ?? '',
      url: json['url'] as String? ?? '',
      fileName: json['file_name'] as String? ?? '',
      sortOrder: (json['sort_order'] as num?)?.toInt() ?? 0,
    );
  }

  static List<MediaImage> listFromJson(dynamic json) {
    if (json is! List) return const [];
    return json
        .whereType<Map<String, dynamic>>()
        .map(MediaImage.fromJson)
        .toList();
  }

  /// The banner (lowest `sort_order`), or null when the gallery is empty.
  static MediaImage? banner(List<MediaImage> images) {
    if (images.isEmpty) return null;
    return images.reduce((a, b) => a.sortOrder <= b.sortOrder ? a : b);
  }
}
