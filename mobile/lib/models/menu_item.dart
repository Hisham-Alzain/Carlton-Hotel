/// One dish on a restaurant menu (Figma restaurant "menu" tab).
class MenuItem {
  final String name;
  final String description;
  final String price;
  final String imagePath;

  /// Optional dietary tag e.g. "vegan"; null when the dish has none.
  final String? tag;

  const MenuItem({
    required this.name,
    required this.description,
    required this.price,
    required this.imagePath,
    this.tag,
  });
}

/// A named group of [MenuItem]s (Breakfast, Starters, Mains, …).
class MenuCategory {
  final String name;
  final List<MenuItem> items;

  const MenuCategory({required this.name, required this.items});
}
