part of 'booking_flow_controller.dart';

extension BookingRoomActions on BookingFlowController {
  Future<void> searchRooms() async {
    if (!_requireAccount()) return;
    if (!hasDates) {
      CustomSnackbars.showInfo(message: AppTranslations.selectYourDatesFirst);
      return;
    }
    // Room already chosen on Home → skip Choose-Room, go straight to add-ons,
    // after checking that room is free on the picked dates (the Choose-Room
    // list does this for every room; this path would otherwise skip it).
    final preselected = selectedRoom.value;
    if (roomPreselected.value && preselected != null) {
      roomsAvailable.remove(preselected.uuid);
      await _loadAvailability([preselected]);
      if (isClosed) return;
      if (!isBookable(preselected)) {
        CustomSnackbars.showInfo(message: AppTranslations.roomSoldOut);
        return;
      }
      Get.toNamed(Routes.addOns);
      return;
    }
    Get.toNamed(Routes.chooseRoom);
    loadRooms();
  }

  /// Loads the Choose-Room list from `GET /public/room-types`, mapped to the
  /// booking option, then checks each one against the selected dates. The
  /// Choose-Room list repaints when the rooms land and again when availability
  /// does, so the cards appear immediately rather than waiting on N checks.
  Future<void> loadRooms() async {
    // New dates mean every earlier count is stale.
    roomsAvailable.clear();
    await loadItems(_cancelToken);
  }

  /// Fills [roomsAvailable] from `GET /public/availability`, one request per room
  /// type — the endpoint takes a single `room_type_uuid` and there is no bulk
  /// form, so they are fired together rather than in sequence.
  ///
  /// Silent and best-effort: this is a courtesy pre-check so the guest is not
  /// offered a room that is gone. `POST /reservations` re-checks server-side and
  /// returns `no_availability`, which is the authoritative answer and already
  /// has an error branch, so a failure here degrades to "unknown" rather than
  /// blocking the step.
  Future<void> _loadAvailability(List<RoomOption> pageRooms) async {
    if (!hasDates) return;
    final checkIn = _fmtDate(rangeStart.value!);
    final checkOut = _fmtDate(rangeEnd.value!);
    final bookable = pageRooms.where((room) => room.uuid.isNotEmpty).toList();
    if (bookable.isEmpty) return;

    final responses = await Future.wait(
      bookable.map(
        (room) => ApiService.find.get<Map<String, dynamic>>(
          path: '/public/availability',
          queryParameters: {
            'room_type_uuid': room.uuid,
            'check_in': checkIn,
            'check_out': checkOut,
          },
          showErrorDialog: false,
        ),
      ),
    );
    if (isClosed) return;
    final counts = <String, int>{};
    for (final (index, res) in responses.indexed) {
      if (!res.hasData) continue;
      final count = (res.data!['rooms_available'] as num?)?.toInt();
      // `available` is the boolean form of the same answer; prefer the count so
      // the card can say how many are left.
      if (count != null) {
        counts[bookable[index].uuid] = count;
      } else if (res.data!['available'] == false) {
        counts[bookable[index].uuid] = 0;
      }
    }
    // Merged, not replaced: each page adds its own rooms' counts.
    roomsAvailable.addAll(counts);
  }

  // ── Step 2 — Choose Your Room + Room Details sheet ──────────────────────
  void setRoomImage(int index) => roomImageIndex.value = index;

  void openRoomDetails(RoomOption room) {
    roomImageIndex.value = 0;
    CustomBottomSheet.show<void>(
      // The content scrolls itself and carries its own close button.
      scrollable: false,
      showClose: false,
      child: RoomDetailsSheet(room: room),
    );
  }

  /// Entry from the Home room list: open the full-screen details page.
  void openRoomDetailsScreen(RoomOption room) {
    roomImageIndex.value = 0;
    Get.toNamed(Routes.roomDetails, arguments: room);
  }

  /// From a listing tap (Home/Discover): fetch the real room-type detail
  /// (`GET /public/room-types/{uuid}`) and open it.
  Future<void> openRoomListing(String uuid) async {
    if (uuid.isEmpty || openingRoom.value) return;
    // No `showLoading: true`: tapping a room card must not throw a modal
    // loading dialog over Home. The re-entrancy guard replaces what the modal
    // was incidentally providing — blocking a second tap mid-fetch, which
    // would otherwise push the details route twice.
    openingRoom.value = true;
    final res = await ApiService.find.get<Map<String, dynamic>>(
      path: '/public/room-types/$uuid',
      showErrorDialog: false,
    );
    if (isClosed) return;
    openingRoom.value = false;
    if (res.hasData) {
      openRoomDetailsScreen(
        RoomOption.fromRoomType(RoomType.fromJson(res.data!)),
      );
    } else {
      CustomSnackbars.showError(message: AppTranslations.roomLoadFailed);
    }
  }

  /// "Select This Room" from the full-screen details page — start a fresh
  /// booking with this room preselected (carrying its real `room_type_uuid`).
  /// Resets first (like every booking entry) so a prior attempt's guest/card/
  /// add-on data never carries over.
  void beginBookingWithRoom(RoomOption room) {
    if (!_requireAccount()) return;
    // "Plan Your Stay" is the Book tab in the Main shell (no standalone route),
    // so pop back to the shell and switch to it (index 2). The switch itself
    // resets the draft, so the room is set only after it — setting it first
    // wiped it again and sent the guest to Choose-Room for a room already
    // chosen.
    Get.until((r) => r.isFirst);
    reset();
    Get.find<MainController>().changeTab(2);
    selectedRoom.value = room;
    roomPreselected.value = true;
  }

  void selectRoom(RoomOption room) {
    // Refused here rather than at `POST /reservations` three steps later, where
    // the guest would have entered their details and card first.
    if (!isBookable(room)) {
      CustomSnackbars.showInfo(message: AppTranslations.roomSoldOut);
      return;
    }
    selectedRoom.value = room;
    Get.toNamed(Routes.addOns);
  }
}
