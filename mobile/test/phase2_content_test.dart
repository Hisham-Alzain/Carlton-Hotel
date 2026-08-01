import 'package:carlton/models/dining_venue.dart';
import 'package:carlton/models/menu.dart';
import 'package:carlton/models/room_type.dart';
import 'package:flutter_test/flutter_test.dart';

/// Phase 2 — guards the public-content DTO parsing that backs Home + Discover.
/// These are pure JSON→DTO factories (no `SettingsService`/`GetStorage`), so the
/// tests stay hermetic. `Bilingual.value` locale resolution is verified on-device.
void main() {
  group('RoomType.fromJson', () {
    final json = <String, dynamic>{
      'uuid': 'room-1',
      'name': {'en': 'Grand Suite', 'ar': 'جناح كبير'},
      'description': {'en': 'Sea view', 'ar': 'إطلالة بحرية'},
      'base_price_usd': 580, // arrives as a number
      'size_sqm': 65,
      'view_type': 'sea',
      'bed_types': ['King', 'Twin'],
      'rating': 4.8,
      'rating_count': 142,
      'cancellation_hours': 48,
      'highlights': [
        {
          'uuid': 'a1',
          'slug': 'wifi',
          'name': {'en': 'Wi-Fi', 'ar': 'واي فاي'},
        },
      ],
      // No explicit `banner` → lowest sort_order image wins.
      'images': [
        {'uuid': 'i2', 'url': 'b.jpg', 'sort_order': 2},
        {'uuid': 'i1', 'url': 'a.jpg', 'sort_order': 1},
      ],
    };

    test('parses scalars, coercing numeric price/size to strings', () {
      final room = RoomType.fromJson(json);
      expect(room.uuid, 'room-1');
      expect(room.name.en, 'Grand Suite');
      expect(room.name.ar, 'جناح كبير');
      expect(room.basePriceUsd, '580');
      expect(room.sizeSqm, '65');
      expect(room.rating, 4.8);
      expect(room.ratingCount, 142);
      expect(room.cancellationHours, 48);
    });

    test('parses bed types and highlight amenities into typed lists', () {
      final room = RoomType.fromJson(json);
      expect(room.bedTypes, ['King', 'Twin']);
      expect(room.highlights, hasLength(1));
      expect(room.highlights.first.name.en, 'Wi-Fi');
    });

    test('banner falls back to the lowest sort_order image', () {
      final room = RoomType.fromJson(json);
      expect(room.banner?.url, 'a.jpg'); // sort_order 1, not the first listed
    });
  });

  group('DiningVenue.fromJson', () {
    test('parses venue fields and derives the banner from images', () {
      final venue = DiningVenue.fromJson(<String, dynamic>{
        'uuid': 'venue-1',
        'name': {'en': 'La Mer', 'ar': 'لا مير'},
        'description': {'en': 'Fine dining', 'ar': ''},
        // The live API returns these as bilingual objects, not plain strings.
        'cuisine_type': {'en': 'French', 'ar': 'فرنسي'},
        'hours': {'en': '18:00 – 23:00', 'ar': '18:00 – 23:00'},
        'location': {'en': 'Level 3', 'ar': 'الطابق 3'},
        'rating': 4.6,
        'rating_count': 88,
        'images': [
          {'uuid': 'i1', 'url': 'hero.jpg', 'sort_order': 0},
        ],
      });
      expect(venue.uuid, 'venue-1');
      expect(venue.name.en, 'La Mer');
      expect(venue.cuisineType.en, 'French');
      expect(venue.hours.en, '18:00 – 23:00');
      expect(venue.location.en, 'Level 3');
      expect(venue.rating, 4.6);
      expect(venue.ratingCount, 88);
      expect(venue.banner?.url, 'hero.jpg');
    });

    test('tolerates missing optional fields + plain-string cuisine', () {
      final venue = DiningVenue.fromJson(<String, dynamic>{
        'uuid': 'venue-2',
        'name': {'en': 'Cafe', 'ar': 'مقهى'},
        'description': 'Casual',
        'cuisine_type': 'Bistro', // a bare string still parses (both variants)
      });
      expect(venue.cuisineType.en, 'Bistro');
      expect(venue.hours.en, ''); // absent → empty bilingual
      expect(venue.banner, isNull);
      expect(venue.ratingCount, 0);
    });
  });

  group('Menu DTO', () {
    test('MenuCategory.fromJson parses slug + sort order', () {
      final cat = MenuCategory.fromJson(<String, dynamic>{
        'uuid': 'c1',
        'slug': 'starters',
        'name': {'en': 'Starters', 'ar': 'مقبلات'},
        'sort_order': 3,
      });
      expect(cat.slug, 'starters');
      expect(cat.sortOrder, 3);
      expect(cat.name.en, 'Starters');
    });

    test('MenuItem.fromJson carries category slug, price and vegan flag', () {
      final item = MenuItem.fromJson(<String, dynamic>{
        'uuid': 'd1',
        'type': 'starters', // its category slug — how the tab filters
        'name': {'en': 'Hummus', 'ar': 'حمص'},
        'description': {'en': 'Chickpea purée', 'ar': ''},
        'price_usd': 6,
        'is_vegan': true,
        'photo': 'hummus.jpg',
      });
      expect(item.type, 'starters');
      expect(item.priceUsd, '6');
      expect(item.isVegan, isTrue);
      expect(item.photo, 'hummus.jpg');
    });

    test('MenuItem.fromJson defaults vegan flag to false when absent', () {
      final item = MenuItem.fromJson(<String, dynamic>{
        'uuid': 'd2',
        'type': 'mains',
        'name': {'en': 'Steak', 'ar': ''},
        'description': {'en': '', 'ar': ''},
      });
      expect(item.isVegan, isFalse);
      expect(item.priceUsd, isNull);
    });
  });
}
