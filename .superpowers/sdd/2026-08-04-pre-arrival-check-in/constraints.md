## Global Constraints

Every task's requirements implicitly include this section.

- **Working directory is `mobile/`.** All commands run from there.
- **DO NOT COMMIT OR PUSH** without explicit user authorization. Commit steps are written for when that authorization is given; until then, stop at the step before and report.
- **Reactive style: `Rx` + `Obx`, not `GetBuilder` + `update()`.** `mobile/CLAUDE.md` still says this codebase uses `GetBuilder`; that line is stale. `HomeController` (428 lines) and `home_view.dart` (377 lines) — the most recently refactored code, and the code this feature integrates with — use `RxList`, `RxBool`, `Rx<T>` and `Obx`. Follow the code, not the doc.
- **`CheckInService` registers in `main.dart`, not a binding.** The spec said "InitialBinding"; the actual project pattern for a cross-screen service is `Get.put(..., permanent: true)` in `main.dart`, exactly as `BookingFlowController` does (`main.dart:28`). Follow `main.dart`.
- **All user-facing strings go through l10n.** Add the key to both `lib/l10n/local.dart` (`en` **and** `ar` maps) and `lib/l10n/app_translations.dart` (typed getter), then reference `AppTranslations.xxx` in views. Never write a raw `'key'.tr` or a bare literal in a widget.
- **Zero new colours.** Every colour comes from `lib/theme/app_colors.dart`. The six this feature needs: `primary` `#08414D`, `lagoonTeal` `#2F7D8E`, `antiqueGold` `#B8975A`, `cream` `#F0EBE2`, `successGreen` `#4CAF50`, `forestGreen` `#19541C`.
- **Zero new pub dependencies.** No camera, no OCR, no permission packages.
- **Typography: `Get.textTheme`.** Building a `TextStyle` from scratch is the violation; `Get.textTheme.titleMedium?.copyWith(fontFamily: 'DM Sans')` is house style.
- **Spacing: multiples of 5, default 10.** Use the `spacing:` parameter on `Row`/`Column` rather than `SizedBox` separators.
- **No `ApiService` calls in any new code.** The one exception is the pre-existing `/booking/pre-arrival-documents` route, reached unchanged from the scanner's Upload control.
- **Copy fixes (do not reproduce the Figma typos):** `Complete Check-In` (not "Ceck"), `Mattress Type` (not "Matress"), `Experiences` (not "Eperiences").
- **Verification gates:** `flutter analyze` must report **0 issues** (project baseline) and `flutter test` must pass. Report actual counts, never "should work".

---
