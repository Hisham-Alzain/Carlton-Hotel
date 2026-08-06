# Task 14: Home pre-arrival state

**Files:**
- Modify: `lib/views/home/home_view.dart`
- Test: `test/home_reservation_state_test.dart` (extend)

**Interfaces:**
- Consumes: `CheckInService.isPreArrival`, `.completed`, `.completedCount`, `.totalSteps`, `.progress`, `.reservation`; `showArrivalTimeSheet()`; `Routes.checkIn`.
- Produces: `HomeView.preArrivalSections` (exposed for the test), `_PreArrivalStaySection`, `_PreArrivalChecklistSection`, `_AirportTransferSection`.

- [ ] **Step 1: Write the failing test**

Append to `test/home_reservation_state_test.dart`:

```dart
// ── Pre-arrival section selection ──
// Guards the three-way selector. Reverting it would send a pre-arrival guest
// to the in-house dashboard, which shows a bill they have not incurred.
group('home section selection', () {
  setUp(() {
    Get.reset();
    Get.put(CheckInService());
  });

  tearDown(Get.reset);

  test('pre-arrival guest gets the pre-arrival section list', () {
    expect(CheckInService.find.isPreArrival.value, isTrue);
    expect(
      HomeView.sectionsFor(hasReservation: true, isPreArrival: true),
      same(HomeView.preArrivalSections),
    );
  });

  test('checked-in guest falls back to the reservation list', () {
    expect(
      HomeView.sectionsFor(hasReservation: true, isPreArrival: false),
      same(HomeView.reservationSections),
    );
  });

  test('no reservation still gets the explore list', () {
    expect(
      HomeView.sectionsFor(hasReservation: false, isPreArrival: false),
      same(HomeView.exploreSections),
    );
  });
});
```

Add the imports `package:carlton/services/check_in_service.dart`,
`package:carlton/views/home/home_view.dart` and `package:get/get.dart`.

- [ ] **Step 2: Run test to verify it fails**

Run: `flutter test test/home_reservation_state_test.dart`
Expected: FAIL — `The getter 'preArrivalSections' isn't defined for the class 'HomeView'`

- [ ] **Step 3: Rename the two existing lists and add the third**

In `home_view.dart`, rename `_reservationSections` → `reservationSections` and `_exploreSections` → `exploreSections` (they must be public for the test to reference them), then add:

```dart
  /// Pre-arrival sections (Figma `75:133`). Half of this list is existing
  /// widgets — only the top three are new.
  static const preArrivalSections = <Widget>[
    _PreArrivalStaySection(),
    _PreArrivalChecklistSection(),
    _AirportTransferSection(),
    _AiConciergeSection(),
    _DiningCarousel(),
    _ExperiencesCarousel(),
  ];

  /// Pure selector, extracted so it is testable without pumping a widget.
  ///
  /// MUST keep returning the same const list instances: `Element.updateChild`
  /// short-circuits the whole subtree when the identical const list comes back,
  /// which is what stops an unrelated profile edit from rebuilding every
  /// carousel. Do not replace these with computed lists.
  static List<Widget> sectionsFor({
    required bool hasReservation,
    required bool isPreArrival,
  }) {
    if (!hasReservation) return exploreSections;
    return isPreArrival ? preArrivalSections : reservationSections;
  }
```

- [ ] **Step 4: Use the selector in build**

Replace the ternary inside the existing `Obx` with:

```dart
        final sections = HomeView.sectionsFor(
          hasReservation: controller.hasReservation,
          isPreArrival: CheckInService.find.isPreArrival.value,
        );
```

- [ ] **Step 5: Write the three new sections**

Append to `home_view.dart`:

```dart
/// Dark teal hero for a booked-but-not-checked-in stay (Figma `75:133`).
class _PreArrivalStaySection extends StatelessWidget {
  const _PreArrivalStaySection();

  @override
  Widget build(BuildContext context) {
    final service = CheckInService.find;
    return Obx(() {
      final reservation = service.reservation.value;
      return Card(
        color: AppColors.primary,
        elevation: 0,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(15),
        ),
        child: Padding(
          padding: const EdgeInsets.all(20),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            spacing: 15,
            children: [
              Row(
                spacing: 10,
                children: [
                  _Chip(
                    label: AppTranslations.roomChip(reservation.roomNumber),
                    background: AppColors.antiqueGold,
                    foreground: AppColors.primary,
                  ),
                  _Chip(
                    label: AppTranslations.preCheckInAvailable,
                    background: AppColors.cream20,
                    foreground: AppColors.cream,
                  ),
                ],
              ),
              Text(
                reservation.suiteName,
                style: Get.textTheme.headlineSmall
                    ?.copyWith(color: AppColors.cream),
              ),
              Text(
                reservation.stayRangeLabel,
                style: Get.textTheme.bodySmall
                    ?.copyWith(color: AppColors.cream60),
              ),
              Row(
                spacing: 10,
                children: [
                  Expanded(
                    child: _Tile(
                      label: AppTranslations.checkInLabel,
                      value: reservation.checkInDate,
                      hint: reservation.checkInTime,
                    ),
                  ),
                  Expanded(
                    child: _Tile(
                      label: AppTranslations.checkOutLabel,
                      value: reservation.checkOutDate,
                      hint: reservation.checkOutTime,
                    ),
                  ),
                ],
              ),
              Row(
                spacing: 10,
                children: [
                  Expanded(
                    child: _Tile(
                      label: AppTranslations.roomLabel,
                      value: 'Suite ${reservation.roomNumber}',
                      hint: reservation.suiteName,
                    ),
                  ),
                  Expanded(
                    child: _Tile(
                      label: AppTranslations.bookingRefLabel,
                      value: reservation.bookingRef,
                      hint: '',
                    ),
                  ),
                ],
              ),
              Row(
                children: [
                  Text(
                    AppTranslations.preArrivalProgress,
                    style: Get.textTheme.bodySmall
                        ?.copyWith(color: AppColors.cream60),
                  ),
                  const Spacer(),
                  Text(
                    AppTranslations.completeFraction(
                      service.completedCount,
                      service.totalSteps,
                    ),
                    style: Get.textTheme.bodySmall
                        ?.copyWith(color: AppColors.antiqueGold),
                  ),
                ],
              ),
              LinearProgressIndicator(
                value: service.progress,
                color: AppColors.antiqueGold,
                backgroundColor: AppColors.cream20,
              ),
              CustomFilledButton(
                height: 50,
                width: double.infinity,
                onPressed: () => Get.toNamed(Routes.checkIn),
                backgroundColor: AppColors.ivoryCream,
                foregroundColor: AppColors.primary,
                child: Text(AppTranslations.checkInNow),
              ),
            ],
          ),
        ),
      );
    });
  }
}

class _Chip extends StatelessWidget {
  final String label;
  final Color background;
  final Color foreground;

  const _Chip({
    required this.label,
    required this.background,
    required this.foreground,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
      decoration: BoxDecoration(
        color: background,
        borderRadius: BorderRadius.circular(20),
      ),
      child: Text(
        label,
        style: Get.textTheme.labelSmall
            ?.copyWith(color: foreground, fontWeight: FontWeight.w700),
      ),
    );
  }
}

class _Tile extends StatelessWidget {
  final String label;
  final String value;
  final String hint;

  const _Tile({
    required this.label,
    required this.value,
    required this.hint,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(15),
      decoration: BoxDecoration(
        color: AppColors.cream08,
        borderRadius: BorderRadius.circular(10),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        spacing: 5,
        children: [
          Text(
            label.toUpperCase(),
            style: Get.textTheme.labelSmall
                ?.copyWith(color: AppColors.cream60),
          ),
          Text(
            value,
            style: Get.textTheme.titleMedium
                ?.copyWith(color: AppColors.cream),
          ),
          if (hint.isNotEmpty)
            Text(
              hint,
              style: Get.textTheme.bodySmall
                  ?.copyWith(color: AppColors.cream60),
            ),
        ],
      ),
    );
  }
}

/// Four-row checklist, ticking live from CheckInService (Figma `75:133`).
class _PreArrivalChecklistSection extends StatelessWidget {
  const _PreArrivalChecklistSection();

  @override
  Widget build(BuildContext context) {
    final service = CheckInService.find;
    return Card(
      color: Colors.white,
      elevation: 0,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(15),
      ),
      child: Padding(
        padding: const EdgeInsets.all(20),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          spacing: 15,
          children: [
            Text(
              AppTranslations.preArrivalChecklist,
              style: Get.textTheme.titleMedium
                  ?.copyWith(color: AppColors.primary),
            ),
            Obx(
              () => _ChecklistRow(
                icon: 'assets/icons/check.svg',
                title: AppTranslations.confirmContactDetails,
                subtitle: AppTranslations.completedLabel,
                done: service.isStepComplete(PreArrivalStep.contactDetails),
                onTap: null,
              ),
            ),
            Obx(
              () => _ChecklistRow(
                icon: 'assets/icons/chk_upload.svg',
                title: AppTranslations.uploadIdOrPassport,
                subtitle: AppTranslations.requiredForCheckIn,
                done: service.isStepComplete(PreArrivalStep.identity),
                onTap: () => Get.toNamed(Routes.checkIn),
              ),
            ),
            Obx(
              () => _ChecklistRow(
                icon: 'assets/icons/clock.svg',
                title: AppTranslations.setArrivalTime,
                subtitle: AppTranslations.tapToAddEta,
                done: service.isStepComplete(PreArrivalStep.arrivalTime),
                onTap: showArrivalTimeSheet,
              ),
            ),
            Obx(
              () => _ChecklistRow(
                icon: 'assets/icons/act_request.svg',
                title: AppTranslations.preArrivalSpecialRequests,
                subtitle: AppTranslations.optionalPreferences,
                done:
                    service.isStepComplete(PreArrivalStep.specialRequests),
                onTap: () => Get.toNamed(Routes.checkIn),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _ChecklistRow extends StatelessWidget {
  final String icon;
  final String title;
  final String subtitle;
  final bool done;
  final VoidCallback? onTap;

  const _ChecklistRow({
    required this.icon,
    required this.title,
    required this.subtitle,
    required this.done,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      child: Row(
        spacing: 15,
        children: [
          Container(
            padding: const EdgeInsets.all(10),
            decoration: BoxDecoration(
              color: AppColors.antiqueGold08,
              borderRadius: BorderRadius.circular(10),
            ),
            child: SvgPicture.asset(icon, width: 18),
          ),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              spacing: 5,
              children: [
                Text(
                  title,
                  style: Get.textTheme.titleSmall?.copyWith(
                    color:
                        done ? AppColors.successGreen : AppColors.primary,
                  ),
                ),
                Text(
                  subtitle,
                  style: Get.textTheme.bodySmall
                      ?.copyWith(color: AppColors.taupeBrown),
                ),
              ],
            ),
          ),
          done
              ? const Icon(Icons.check_circle,
                  color: AppColors.successGreen, size: 24)
              : const Icon(Icons.circle_outlined,
                  color: AppColors.stoneTaupe, size: 24),
        ],
      ),
    );
  }
}

/// Airport transfer promo (Figma `75:133`).
class _AirportTransferSection extends GetView<HomeController> {
  const _AirportTransferSection();

  @override
  Widget build(BuildContext context) {
    return Card(
      color: Colors.white,
      elevation: 0,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(15),
      ),
      child: Padding(
        padding: const EdgeInsets.all(20),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          spacing: 15,
          children: [
            Text(
              AppTranslations.airportTransfer,
              style: Get.textTheme.titleMedium
                  ?.copyWith(color: AppColors.primary),
            ),
            Text(
              AppTranslations.airportTransferBody,
              style: Get.textTheme.bodySmall
                  ?.copyWith(color: AppColors.taupeBrown),
            ),
            CustomFilledButton(
              height: 50,
              width: double.infinity,
              onPressed: controller.goToServices,
              backgroundColor: AppColors.lagoonTeal,
              child: Text(AppTranslations.requestAirportTransfer),
            ),
          ],
        ),
      ),
    );
  }
}
```

Add the imports these sections need: `check_in_service.dart`, `pre_arrival_step.dart`, `arrival_time_sheet.dart`, `custom_filled_button.dart`, `flutter_svg`, `app_translations.dart`, `routes.dart`, `app_colors.dart`.

- [ ] **Step 6: Fix the renamed references**

Run: `grep -n "_reservationSections\|_exploreSections" lib/views/home/home_view.dart`
Expected: no output. If any remain, update them to the public names.

- [ ] **Step 7: Run test to verify it passes**

Run: `flutter test test/home_reservation_state_test.dart`
Expected: PASS — existing folio tests plus 3 new selection tests

- [ ] **Step 8: Analyze**

Run: `flutter analyze`
Expected: 0 issues

- [ ] **Step 9: Commit** *(only with authorization)*

```bash
git add lib/views/home/home_view.dart test/home_reservation_state_test.dart
git commit -m "feat(home): add pre-arrival state with live checklist and progress"
```

---

