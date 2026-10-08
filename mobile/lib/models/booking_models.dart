// View models for the My Stays + booking flow: string labels pre-formatted to
// match Figma copy. Controllers map the API DTOs (`models/stay.dart`,
// `models/room_type.dart`, …) into these at the controller boundary.

import 'package:carlton/l10n/app_translations.dart';
import 'package:carlton/customWidgets/custom_country_code_picker.dart';
import 'package:carlton/models/amenity.dart';
import 'package:carlton/models/room_type.dart';
import 'package:flutter/material.dart' show IconData, Icons;

part 'payment_models.dart';

part 'room_option.dart';

enum StayStatus { active, upcoming, past }

/// One line in a receipt breakdown.
typedef ReceiptLine = ({String label, String amount});

class ReceiptData {
  final String roomName;
  final String dateLabel;
  final String resCode;
  final List<ReceiptLine> lines;
  final String total;
  final String paymentInfo;

  const ReceiptData({
    required this.roomName,
    required this.dateLabel,
    required this.resCode,
    required this.lines,
    required this.total,
    required this.paymentInfo,
  });
}

/// The charge breakdown for one priced stay, already formatted for display.
/// Assembled by `BookingFlowController.priceSummary` from the `/public/quote`
/// result so the breakdown widget takes one model instead of seven strings.
class BookingPriceSummary {
  final String roomName;

  /// The quote's night count, which can differ from the picked range once the
  /// server has priced the stay.
  final int nights;
  final String subtotal;

  /// [promoCode] is only rendered when [hasDiscount] — an unapplied code in the
  /// field must not show up as a charged line.
  final bool hasDiscount;
  final String promoCode;
  final String discount;

  final String total;

  const BookingPriceSummary({
    required this.roomName,
    required this.nights,
    required this.subtotal,
    required this.hasDiscount,
    required this.promoCode,
    required this.discount,
    required this.total,
  });
}

/// A stay in any of the three My Stays tabs. Fields are optional because each
/// status renders a different card (see StaysView tab bodies).
class Stay {
  final String id;

  /// The reservation `uuid` (Phase 4) — drives cancel
  /// (`DELETE /reservations/{uuid}`) and receipt (`GET /stays/{uuid}/receipt`).
  /// Stamped during controller-boundary mapping.
  final String uuid;

  /// Whether `DELETE /reservations/{uuid}` will succeed (upcoming stays only).
  final bool isCancellable;

  /// Whether a folio/receipt exists for this stay (past stays only). Gates the
  /// receipt fetch — a cancelled stay has none.
  final bool hasReceipt;

  /// A past stay that was cancelled rather than checked out (`status:
  /// cancelled` on `GET /stays/past`), so its card does not say Completed.
  final bool isCancelled;

  final String roomName;
  final StayStatus status;

  // Past + upcoming
  final String? subtitle; // "Carlton Hotel Damascus · Room 504"
  final String? imagePath;

  // Past
  final String? dateRangeLabel; // "Jul 8 – Jul 10 · 2 nights"
  final String? totalCharged; // "$596"
  final ReceiptData? receipt;

  // Upcoming
  final String? checkInLabel; // "Sep 5, 2026"
  final String? checkOutLabel; // "Sep 8, 2026"
  final String? resCode; // "CRS-504-2891"
  final String? pricePerNight; // "$240/night"
  final int? nextCheckInDays; // 52

  /// "Paid $20 with 2,000 points" — what rewards the booking used, from the
  /// reservation's `loyalty` block. Null when it used none.
  final String? rewardsNote;

  // Active
  final String? checkedInSince; // "3:00 PM"
  final int? nightsRemaining;

  const Stay({
    required this.id,
    required this.roomName,
    required this.status,
    this.uuid = '',
    this.isCancellable = false,
    this.hasReceipt = false,
    this.isCancelled = false,
    this.subtitle,
    this.imagePath,
    this.dateRangeLabel,
    this.totalCharged,
    this.receipt,
    this.checkInLabel,
    this.checkOutLabel,
    this.resCode,
    this.pricePerNight,
    this.nextCheckInDays,
    this.rewardsNote,
    this.checkedInSince,
    this.nightsRemaining,
  });
}
