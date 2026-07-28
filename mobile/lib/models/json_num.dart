/// Tolerant JSON number parsing for the content DTOs.
///
/// The Laravel API serialises **DECIMAL** columns as strings (`"4.30"`,
/// `"280.00"`, `"18.00"`) while integers come back as JSON numbers. A plain
/// `json['x'] as num?` therefore throws `type 'String' is not a subtype of
/// 'num?'` on every decimal field. These helpers accept a num, a numeric
/// String, or null.
library;

double? asDouble(dynamic v) {
  if (v == null) return null;
  if (v is num) return v.toDouble();
  return double.tryParse(v.toString());
}

int? asInt(dynamic v) {
  if (v == null) return null;
  if (v is num) return v.toInt();
  return int.tryParse(v.toString());
}
