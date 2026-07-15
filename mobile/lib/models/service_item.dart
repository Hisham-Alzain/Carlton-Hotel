class ServiceItem {
  final String title;
  final String subtitle;

  /// Decorative photo that bleeds off the tile's bottom-right corner. Sizes
  /// and opacity are taken 1:1 from the Figma tiles.
  final String imagePath;
  final double imageWidth;
  final double imageHeight;
  final double imageOpacity;

  const ServiceItem({
    required this.title,
    required this.subtitle,
    required this.imagePath,
    required this.imageWidth,
    required this.imageHeight,
    required this.imageOpacity,
  });
}
