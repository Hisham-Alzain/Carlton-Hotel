/// One icon + label pair in a [CustomListingCard]'s meta area (e.g. "45 m²",
/// "King Bed", opening hours, location). [iconPath] is a tintable SVG asset.
class CardMeta {
  final String iconPath;
  final String text;

  const CardMeta(this.iconPath, this.text);
}
