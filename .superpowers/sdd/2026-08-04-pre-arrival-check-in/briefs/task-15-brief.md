# Task 15: Full verification

**Files:** none — verification only.

- [ ] **Step 1: Analyze**

Run: `flutter analyze`
Expected: **0 issues.** Any issue is a blocker; the project baseline is zero.

- [ ] **Step 2: Run the full suite**

Run: `flutter test`
Expected: all tests pass. Record the **actual** count — the suite was 11 files before this work and should now be 19.

- [ ] **Step 3: Confirm no `var()` survived in the icons**

Run: `grep -rl "var(--" assets/icons/ || echo "clean"`
Expected: `clean`. Any hit means that icon renders as blank space at runtime.

- [ ] **Step 4: Confirm no new dependencies were added**

Run: `git diff --stat pubspec.yaml pubspec.lock`
Expected: no changes to either file.

- [ ] **Step 5: Confirm no raw strings leaked into the new views**

Run: `grep -rnE "Text\('[A-Za-z]" lib/views/check_in lib/components/check_in`
Expected: no output. Every user-facing string must come from `AppTranslations`.

- [ ] **Step 6: Manual device check**

Run: `flutter run`

Walk the flow: Home shows the teal hero at **1/4** → *Check In Now* → Identity shows a disabled CTA → *Scan ID* → framing → *Scan* → scanning → success → *Continue* → Identity now green with the CTA enabled → *Continue to Preferences* → toggles and dropdowns respond → *Continue* → Room Key → *Add Digital Key* → activating → activated → *Complete Check-In* → Home returns to its in-house reservation state. Then re-run and confirm the checklist ticks and the progress bar move as steps complete.

Also run once in Arabic to confirm RTL layout, since the project supports `ar`.

- [ ] **Step 7: Report**

State the actual `flutter analyze` and `flutter test` results. Do not describe the work as done without both numbers.

---

