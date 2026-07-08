import 'package:carlton/services/settings_service.dart';
import 'package:intl/intl.dart';
import 'package:get/get.dart';

extension PriceExtensions on double {
  String formatPrice({int? decimalDigits}) {
    final settings = Get.find<SettingsService>();

    final locale = settings.locale.value.toString();
    final currency = settings.currency.value;

    return NumberFormat.currency(
      locale: locale,
      symbol: currency.symbol,
      decimalDigits: decimalDigits ?? 2,
    ).format(this);
  }
}
