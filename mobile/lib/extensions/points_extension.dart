import 'package:carlton/services/settings_service.dart';
import 'package:intl/intl.dart';

/// The single place a points figure becomes text.
///
/// Mirrors [PriceExtensions] deliberately: points are grouped in the guest's
/// locale (`1,250` in English, `١٬٢٥٠` in Arabic) rather than interpolated raw,
/// which is what a four-digit balance would otherwise render as.
extension PointsExtensions on int {
  String formatPoints() => NumberFormat.decimalPattern(
    SettingsService.find.locale.value.toString(),
  ).format(this);

  /// Same, carrying an explicit sign — the ledger needs `+120` / `-500` where
  /// the balance needs a bare `1,250`.
  String formatSignedPoints() =>
      this < 0 ? '-${abs().formatPoints()}' : '+${formatPoints()}';
}
