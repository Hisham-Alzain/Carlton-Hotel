import 'package:carlton/controllers/reviews/review_controller.dart';
import 'package:carlton/models/review.dart';
import 'package:flutter_test/flutter_test.dart';

/// Phase 7 — Reviews. Hermetic: no HTTP, no GetStorage, no SettingsService.
/// Each assertion would fail if the corresponding wiring were reverted.
void main() {
  group('ReviewController.isSaved (the 200-vs-201 regression guard)', () {
    test('treats BOTH 201 (first submit) and 200 (edit) as success', () {
      // Fails the moment someone reverts submitReview to a bare
      // `statusCode == 201`, silently dropping the edit path.
      expect(ReviewController.isSaved(201), isTrue);
      expect(ReviewController.isSaved(200), isTrue);
    });

    test('every non-success status is a failure', () {
      expect(ReviewController.isSaved(422), isFalse);
      expect(ReviewController.isSaved(500), isFalse);
      expect(ReviewController.isSaved(null), isFalse);
    });
  });

  group('ReviewTargetType.apiPath (the {type} URL segment)', () {
    test('maps each target to its exact path segment', () {
      expect(ReviewTargetType.roomType.apiPath, 'room_type');
      expect(ReviewTargetType.diningVenue.apiPath, 'dining_venue');
    });
  });

  group('Review.fromJson', () {
    test('parses rating/comment/is_verified_stay + author name', () {
      final r = Review.fromJson(<String, dynamic>{
        'uuid': 'rev-1',
        'rating': 4,
        'comment': 'Lovely evening',
        'is_verified_stay': true,
        'created_at': '2026-07-20T10:00:00Z',
        'author': {'first_name': 'Mona', 'last_name': 'Saleh'},
      });
      expect(r.rating, 4);
      expect(r.comment, 'Lovely evening');
      expect(r.isVerifiedStay, isTrue);
      expect(r.authorName, 'Mona Saleh');
    });

    test('tolerates a null comment and a missing author (defaults empty)', () {
      final r = Review.fromJson(<String, dynamic>{
        'uuid': 'rev-2',
        'rating': 5,
      });
      expect(r.comment, isNull);
      expect(r.isVerifiedStay, isFalse);
      expect(r.authorFirstName, '');
      expect(r.authorName, '');
    });

    test('authorName joins only the non-empty name parts', () {
      final r = Review.fromJson(<String, dynamic>{
        'uuid': 'rev-3',
        'rating': 3,
        'author': {'first_name': 'Sam', 'last_name': ''},
      });
      expect(r.authorName, 'Sam');
    });
  });
}
