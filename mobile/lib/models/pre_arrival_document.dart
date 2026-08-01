/// One uploaded identity document as returned by `POST /pre-arrival/documents`
/// (the 201 body is an array of these). No file URL is returned to the guest.
class PreArrivalDocument {
  final String uuid;
  final String type;

  const PreArrivalDocument({required this.uuid, required this.type});

  factory PreArrivalDocument.fromJson(Map<String, dynamic> json) =>
      PreArrivalDocument(
        uuid: json['uuid'] as String? ?? '',
        type: json['type'] as String? ?? '',
      );

  static List<PreArrivalDocument> listFromJson(List<dynamic> list) => list
      .whereType<Map<String, dynamic>>()
      .map(PreArrivalDocument.fromJson)
      .toList();
}

/// One picked file paired with its declared [type], fed to the multipart
/// builder. Pure value type (no I/O), so the picker → upload seam is
/// hermetically testable.
class PreArrivalDocumentUpload {
  /// Free-form document type, e.g. `passport`, `id_card`, `visa`.
  final String type;
  final String filePath;

  /// The file's MIME type (`image/jpeg`, `image/png`, `application/pdf`).
  final String mime;

  const PreArrivalDocumentUpload({
    required this.type,
    required this.filePath,
    required this.mime,
  });

  PreArrivalDocumentUpload copyWith({String? type}) => PreArrivalDocumentUpload(
    type: type ?? this.type,
    filePath: filePath,
    mime: mime,
  );

  /// The file name portion of [filePath] (handles both `/` and `\`).
  String get fileName => filePath.split(RegExp(r'[\\/]')).last;
}
