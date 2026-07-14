import 'package:carlton/models/reservation.dart';
import 'package:carlton/services/get_storage_service.dart';

/// Demo-only: persists fake "signed in" / "has a reservation" flags via the
/// app's existing [StorageService] so relaunching the app can skip straight
/// past the questions already answered. A real backend would store an actual
/// auth token instead of a bool.
class SessionService {
  static const _signedInKey = 'session_signed_in';
  static const _hasReservationKey = 'session_has_reservation';
  static const _pendingCodeKey = 'session_pending_reservation_code';
  static const _pendingLastNameKey = 'session_pending_reservation_last_name';

  // ── Auth ────────────────────────────────────────────────────────────────
  static bool get isSignedIn => StorageService.getBool(_signedInKey) ?? false;

  static Future<void> markSignedIn() =>
      StorageService.setBool(_signedInKey, true);

  static Future<void> signOut() => StorageService.remove(_signedInKey);

  // ── Reservation ─────────────────────────────────────────────────────────
  static bool get hasReservation =>
      StorageService.getBool(_hasReservationKey) ?? false;

  static Future<void> markHasReservation() =>
      StorageService.setBool(_hasReservationKey, true);

  /// A reservation found before sign-in that belongs to an existing account —
  /// stashed here while the guest authenticates, then attached to their
  /// account (and cleared) once they land back on Services signed in.
  static Future<void> setPendingReservation(Reservation reservation) async {
    await StorageService.setString(_pendingCodeKey, reservation.code);
    await StorageService.setString(_pendingLastNameKey, reservation.lastName);
  }

  static Reservation? get pendingReservation {
    final code = StorageService.getString(_pendingCodeKey);
    final lastName = StorageService.getString(_pendingLastNameKey);
    if (code == null || lastName == null) return null;
    return Reservation(code: code, lastName: lastName);
  }

  static Future<void> clearPendingReservation() async {
    await StorageService.remove(_pendingCodeKey);
    await StorageService.remove(_pendingLastNameKey);
  }
}
