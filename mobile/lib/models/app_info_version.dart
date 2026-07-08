class AppVersionInfo {
  final String latestVersion;
  final int minSupportedBuild;
  final bool forceUpdate;
  final bool updateAvailable;
  final String storeUrl;
  final String title;
  final String message;

  AppVersionInfo({
    required this.latestVersion,
    required this.minSupportedBuild,
    required this.forceUpdate,
    required this.updateAvailable,
    required this.storeUrl,
    required this.title,
    required this.message,
  });

  factory AppVersionInfo.fromJson(Map<String, dynamic> json) {
    return AppVersionInfo(
      latestVersion: json['latestVersion'] as String? ?? '',
      minSupportedBuild: (json['minSupportedBuild'] as num?)?.toInt() ?? 0,
      forceUpdate: json['forceUpdate'] as bool? ?? false,
      updateAvailable: json['updateAvailable'] as bool? ?? false,
      storeUrl: json['storeUrl'] as String? ?? '',
      title: json['title'] as String? ?? '',
      message: json['message'] as String? ?? '',
    );
  }
}
