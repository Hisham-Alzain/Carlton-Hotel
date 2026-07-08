import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

extension TimeExtension on int {
  String toDigitalTime() {
    int totalSeconds = floor();
    int hours = totalSeconds ~/ 3600;
    int minutes = (totalSeconds % 3600) ~/ 60;
    int secs = totalSeconds % 60;

    if (hours > 0) {
      return '${hours.toString().padLeft(2, '0')}:'
          '${minutes.toString().padLeft(2, '0')}:'
          '${secs.toString().padLeft(2, '0')}';
    } else {
      return '${minutes.toString().padLeft(2, '0')}:'
          '${secs.toString().padLeft(2, '0')}';
    }
  }

  String toReadableDuration(BuildContext context) {
    // 1. Get the current language code from the application context
    final locale = Localizations.localeOf(context).languageCode;
    final isArabic = locale == 'ar';

    int totalSeconds = this;
    int hours = totalSeconds ~/ 3600;
    int minutes = (totalSeconds % 3600) ~/ 60;

    // 2. Use NumberFormat to convert numbers to localized scripts (e.g., 2 -> ٢)
    final numberFormatter = NumberFormat.decimalPattern(locale);
    final String formattedHours = numberFormatter.format(hours);
    final String formattedMinutes = numberFormatter.format(minutes);

    // 3. Define localized labels for Hours and Minutes
    final String hourLabel = isArabic ? 'س' : 'H';
    final String minuteLabel = isArabic ? 'د' : 'M';

    // 4. Return the formatted string depending on the directionality
    if (hours > 0) {
      if (isArabic) {
        // Arabic is Right-to-Left (RTL). Example outcome: ٢ س ١٤ د
        return '$formattedHours $hourLabel $formattedMinutes $minuteLabel';
      } else {
        // English is Left-to-Right (LTR). Example outcome: 2H 14M
        return '$formattedHours$hourLabel $formattedMinutes$minuteLabel';
      }
    } else {
      if (isArabic) {
        return '$formattedMinutes $minuteLabel';
      } else {
        return '$formattedMinutes$minuteLabel';
      }
    }
  }
}
