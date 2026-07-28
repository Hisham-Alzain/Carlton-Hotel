import 'package:carlton/customWidgets/custom_image.dart';
import 'package:flutter/material.dart';

/// The restaurant gallery (Figma info tab): a 2×2 masonry — a wide + narrow
/// tile on the first row, mirrored narrow + wide on the second. Images are
/// venue photo URLs (`CustomImage` handles both network URLs and assets).
class CustomGalleryGrid extends StatelessWidget {
  final List<String> images;
  final double rowHeight;

  const CustomGalleryGrid({
    required this.images,
    this.rowHeight = 150,
    super.key,
  });

  String _img(int i) => images[i % images.length];

  Widget _tile(String path, int flex) => Expanded(
    flex: flex,
    child: ClipRRect(
      borderRadius: BorderRadius.circular(12),
      child: CustomImage(source: path, height: rowHeight, fit: BoxFit.cover),
    ),
  );

  @override
  Widget build(BuildContext context) {
    return Column(
      spacing: 10,
      children: [
        Row(spacing: 10, children: [_tile(_img(0), 2), _tile(_img(1), 1)]),
        Row(spacing: 10, children: [_tile(_img(2), 1), _tile(_img(3), 2)]),
      ],
    );
  }
}
