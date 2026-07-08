import 'dart:typed_data';

class PickedFile {
  final String name;
  final Uint8List? bytes; // used on web
  final String? path; // used on mobile

  PickedFile({required this.name, this.bytes, this.path});

  bool get isWeb => bytes != null;

  PickedFile.empty() : name = '', bytes = null, path = null;

  @override
  String toString() {
    return name;
  }
}
