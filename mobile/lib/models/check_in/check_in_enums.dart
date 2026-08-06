/// Identity tab state. `captured` is the scanner's success screen before the
/// guest confirms; tapping Continue there promotes it to `verified`. Only
/// `verified` enables the Identity tab CTA.
enum IdentityStatus { notStarted, captured, verified }

/// Digital key button states (Figma `75:1218`).
enum DigitalKeyStatus { idle, activating, activated }

/// Scanner stages (Figma `75:653`, `75:703`, `75:757`).
///
/// `framing` and `scanning` are the two Figma capture states; the other three
/// exist because a real camera can fail. [initializing] covers the gap between
/// opening the route and the preview texture going live, and [unavailable] is
/// every terminal failure (permission refused, no camera on the device, plugin
/// error) — the view renders a recovery card there rather than a dead preview.
enum ScanStage { initializing, framing, scanning, success, unavailable }
