/// Identity tab state. `captured` is the scanner's success screen before the
/// guest confirms; tapping Continue there promotes it to `verified`. Only
/// `verified` enables the Identity tab CTA.
enum IdentityStatus { notStarted, captured, verified }

/// Digital key button states (Figma `2237:5322`).
enum DigitalKeyStatus { idle, activating, activated }

/// Why `CheckInService.completeCheckIn` ended the way it did.
///
/// A plain bool used to collapse [incomplete] and [failed] into one "false",
/// which made the wizard's Complete button do nothing at all when a step was
/// missing: no request, so no ApiService error dialog, so no feedback. These
/// two need different UI, so they are different values.
enum CheckInOutcome {
  /// Reservation is now `checked_in`.
  success,

  /// A required pre-arrival step is still outstanding — nothing was sent.
  /// The caller owns the message; ApiService cannot have shown one.
  incomplete,

  /// The request was made and refused. ApiService has already reported it.
  failed,
}

/// Scanner stages (Figma `2237:4757`, `2237:4807`, `2237:4861`).
///
/// `framing` and `scanning` are the two Figma capture states; the other three
/// exist because a real camera can fail. [initializing] covers the gap between
/// opening the route and the preview texture going live, and [unavailable] is
/// every terminal failure (permission refused, no camera on the device, plugin
/// error) — the view renders a recovery card there rather than a dead preview.
enum ScanStage { initializing, framing, scanning, success, unavailable }
