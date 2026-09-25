import 'package:carlton/l10n/app_translations.dart';
import 'package:carlton/models/service_catalog_item.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';

/// Display status for a service-request row. Enhanced enum carrying the pill
/// tint + glyph; mapped from the backend's raw `status` string via [fromApi]
/// so the cards stay untouched.
enum ServiceRequestStatus {
  requested(
    iconPath: 'assets/icons/clock.svg',
    textColor: AppColors.antiqueGold,
    bgColor: AppColors.antiqueGold09,
    iconBgColor: AppColors.antiqueGold08,
  ),
  inProgress(
    iconPath: 'assets/icons/clock.svg',
    textColor: AppColors.antiqueGold,
    bgColor: AppColors.antiqueGold09,
    iconBgColor: AppColors.antiqueGold08,
  ),
  confirmed(
    iconPath: 'assets/icons/clock.svg',
    textColor: AppColors.successGreen,
    bgColor: AppColors.successGreen09,
    iconBgColor: AppColors.successGreen08,
  ),
  completed(
    iconPath: 'assets/icons/check.svg',
    textColor: AppColors.successGreen,
    bgColor: AppColors.successGreen09,
    iconBgColor: AppColors.successGreen08,
  ),
  cancelled(
    iconPath: 'assets/icons/warning.svg',
    textColor: AppColors.dimGrey,
    bgColor: AppColors.whisperGrey,
    iconBgColor: AppColors.black06,
  );

  const ServiceRequestStatus({
    required this.iconPath,
    required this.textColor,
    required this.bgColor,
    required this.iconBgColor,
  });

  /// Resolved per read, not stored as a `const` enum field — `.tr` is a
  /// runtime lookup and a const field would pin the launch locale.
  String get label => switch (this) {
    ServiceRequestStatus.requested => AppTranslations.statusRequested,
    ServiceRequestStatus.inProgress => AppTranslations.statusInProgress,
    ServiceRequestStatus.confirmed => AppTranslations.statusConfirmed,
    ServiceRequestStatus.completed => AppTranslations.statusCompleted,
    ServiceRequestStatus.cancelled => AppTranslations.statusCancelled,
  };

  /// Ships untinted, so whoever renders it must apply [textColor] via
  /// `colorFilter`. All three clock states share one `clock.svg`; the previous
  /// `orangeclock.svg` / `greenclock.svg` pair was the same glyph twice, baked
  /// gold and green — the colour those files encoded is [textColor], which was
  /// already right here.
  final String iconPath;
  final Color textColor;
  final Color bgColor;
  final Color iconBgColor;

  /// Maps the backend `status` (`new`/`in_progress`/`completed`/`cancelled`)
  /// to a display status. Unknown/missing values fall back to [requested] —
  /// a fresh, pending row.
  static ServiceRequestStatus fromApi(String raw) {
    switch (raw) {
      case 'new':
        return ServiceRequestStatus.requested;
      case 'in_progress':
        return ServiceRequestStatus.inProgress;
      case 'completed':
        return ServiceRequestStatus.completed;
      case 'cancelled':
        return ServiceRequestStatus.cancelled;
      default:
        return ServiceRequestStatus.requested;
    }
  }
}

/// An in-room service request (`POST`/`GET /service-requests`). Holds the real
/// DTO fields, but keeps the display surface the cards read
/// ([status]/[title]/[detail]/[iconAsset]) as getters so
/// `CustomActiveRequestsCard` needs no changes.
class ServiceRequest {
  final String uuid;
  final String? type;
  final String? department;

  /// Raw backend status; the display [status] is derived from it.
  final String statusCode;
  final String priority;
  final String? notes;
  final String? createdAt;
  final String? categoryCode;

  /// The catalog item behind the request (name/price/expected_minutes), or
  /// null for legacy free-string requests.
  final ServiceCatalogOption? serviceItem;

  /// Optional leading thumbnail — carried by demo/home rows; null for API rows
  /// (they render the status glyph instead).
  final String? iconAsset;

  const ServiceRequest({
    required this.uuid,
    this.type,
    this.department,
    this.statusCode = 'new',
    this.priority = 'normal',
    this.notes,
    this.createdAt,
    this.categoryCode,
    this.serviceItem,
    this.iconAsset,
  });

  factory ServiceRequest.fromJson(Map<String, dynamic> json) => ServiceRequest(
    uuid: json['uuid'] as String? ?? '',
    type: json['type'] as String?,
    department: json['department'] as String?,
    statusCode: json['status'] as String? ?? 'new',
    priority: json['priority'] as String? ?? 'normal',
    notes: json['notes'] as String?,
    createdAt: json['created_at'] as String?,
    categoryCode: json['category_code'] as String?,
    serviceItem: json['service_item'] is Map<String, dynamic>
        ? ServiceCatalogOption.fromJson(
            json['service_item'] as Map<String, dynamic>,
          )
        : null,
  );

  static List<ServiceRequest> listFromJson(List<dynamic> list) => list
      .whereType<Map<String, dynamic>>()
      .map(ServiceRequest.fromJson)
      .toList();

  /// Display status pill — old name kept so the cards are untouched.
  ServiceRequestStatus get status => ServiceRequestStatus.fromApi(statusCode);

  /// Row title: the catalog item's localized name, or the humanized [type].
  String get title => serviceItem?.name.value ?? _humanize(type);

  /// Row subtitle: expected turnaround if known, else the department, else
  /// the priority.
  String get detail {
    final mins = serviceItem?.expectedMinutes;
    if (mins != null) return '~$mins min';
    final dept = department;
    if (dept != null && dept.isNotEmpty) return _humanize(dept);
    return _humanize(priority);
  }

  /// Turns a snake/space delimited code (`room_service`) into a title-cased
  /// label (`Room Service`).
  static String _humanize(String? raw) {
    if (raw == null || raw.isEmpty) return AppTranslations.request;
    return raw
        .split(RegExp(r'[_\s]+'))
        .where((w) => w.isNotEmpty)
        .map((w) => w[0].toUpperCase() + w.substring(1))
        .join(' ');
  }
}
