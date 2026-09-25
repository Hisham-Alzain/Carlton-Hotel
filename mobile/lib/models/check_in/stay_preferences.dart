/// Preferences tab payload (Figma `2237:4912`). Ids match the `PreferenceOption`
/// ids already in PreferenceOptions.bedOptions / pillowOptions / mattressOptions.
class StayPreferences {
  final String bedTypeId;
  final String pillowId;
  final String mattressId;
  final bool smokingRoom;
  final bool earlyCheckIn;
  final bool lateCheckOut;
  final bool extraPillows;
  final String notes;

  const StayPreferences({
    required this.bedTypeId,
    required this.pillowId,
    required this.mattressId,
    required this.smokingRoom,
    required this.earlyCheckIn,
    required this.lateCheckOut,
    required this.extraPillows,
    required this.notes,
  });

  StayPreferences copyWith({
    String? bedTypeId,
    String? pillowId,
    String? mattressId,
    bool? smokingRoom,
    bool? earlyCheckIn,
    bool? lateCheckOut,
    bool? extraPillows,
    String? notes,
  }) {
    return StayPreferences(
      bedTypeId: bedTypeId ?? this.bedTypeId,
      pillowId: pillowId ?? this.pillowId,
      mattressId: mattressId ?? this.mattressId,
      smokingRoom: smokingRoom ?? this.smokingRoom,
      earlyCheckIn: earlyCheckIn ?? this.earlyCheckIn,
      lateCheckOut: lateCheckOut ?? this.lateCheckOut,
      extraPillows: extraPillows ?? this.extraPillows,
      notes: notes ?? this.notes,
    );
  }
}
