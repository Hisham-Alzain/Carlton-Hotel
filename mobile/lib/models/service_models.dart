/// A single requestable option inside a [ServiceDetailCategory] (e.g. "Carlton
/// Breakfast" under Room Service).
class ServiceOption {
  final String iconPath;
  final String title;
  final String description;
  final String eta;

  const ServiceOption({
    required this.iconPath,
    required this.title,
    required this.description,
    required this.eta,
  });

  /// [eta] without its "ETA: " prefix, for prose ("… within 45 min").
  String get etaLabel => eta.replaceFirst('ETA: ', '');
}

/// A services-hub category expanded into its list of requestable options
/// (the Services hub tile -> category detail screen flow).
class ServiceDetailCategory {
  final String key;
  final String name;
  final String subtitle;
  final String imagePath;
  final List<ServiceOption> options;

  const ServiceDetailCategory({
    required this.key,
    required this.name,
    required this.subtitle,
    required this.imagePath,
    required this.options,
  });
}
