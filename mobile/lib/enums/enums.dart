enum MiddlewareCases { invalidToken, validToken, noToken }

enum LocationFailure { serviceDisabled, permissionDenied, permissionForever }

enum AppDialogType { success, error, warning, info, confirmation, destructive }

enum SnackbarType { success, error, warning, info }

enum SignInMethod { phone, email }

/// Trailing control style for a `CustomSelectableCard` — a checkbox (Add-Ons,
/// multi-select) or a radio (Payment methods, single-select).
enum SelectableControl { checkbox, radio }

/// Which home state the Services screen renders, derived from the session:
/// a guest with no booking (browse + sign-in prompt), a signed-in user with
/// no current reservation (Explore & Book), or an active stay (stay card).
enum ServicesHomeState { guestBrowse, exploreAndBook, activeStay }

/// Which body Home renders, resolved from the guest's current reservation by
/// `HomeController.resolveHomeState` — the single owner of this decision.
///
/// [preCheckIn] covers the whole booked-but-not-yet-checked-in span. There is
/// no arrival-time window: a guest with a booking that is not yet checked in
/// can always start check-in (see `HomeController.startCheckIn`).
enum HomeViewState { defaultHome, activeBooking, preCheckIn }
