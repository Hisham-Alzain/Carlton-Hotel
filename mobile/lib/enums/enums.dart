enum MiddlewareCases { invalidToken, validToken, noToken }

enum LocationFailure { serviceDisabled, permissionDenied, permissionForever }

enum AppDialogType { success, error, warning, info, confirmation, destructive }

enum SnackbarType { success, error, warning, info }

enum SignInMethod { phone, email }

/// Which home state the Services screen renders, derived from the session:
/// a guest with no booking (browse + sign-in prompt), a signed-in user with
/// no current reservation (Explore & Book), or an active stay (stay card).
enum ServicesHomeState { guestBrowse, exploreAndBook, activeStay }
