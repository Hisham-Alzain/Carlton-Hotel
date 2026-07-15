<?php

namespace Database\Seeders;

use App\Models\DiningVenue;
use App\Models\EventSpace;
use App\Models\Facility;
use App\Models\Page;
use App\Models\Promotion;
use App\Models\Room;
use App\Models\RoomType;
use Database\Seeders\Support\GeneratesPlaceholderMedia;
use Illuminate\Database\Seeder;

class CmsContentSeeder extends Seeder
{
    use GeneratesPlaceholderMedia;

    public function run(): void
    {
        $this->roomTypesAndRooms();
        $this->facilities();
        $this->diningVenues();
        $this->eventSpaces();
        $this->pages();
        $this->promotions();
    }

    private function roomTypesAndRooms(): void
    {
        $types = [
            ['en' => 'Standard Queen', 'ar' => 'غرفة كوين ستاندرد', 'occ' => [2, 2], 'size' => 24, 'price' => 90,
                'desc' => 'A comfortable queen room with city views, perfect for solo travelers and couples.', 'floors' => [1, 2]],
            ['en' => 'Deluxe King', 'ar' => 'غرفة ديلوكس كينغ', 'occ' => [2, 3], 'size' => 32, 'price' => 150,
                'desc' => 'Spacious king room with a seating area and premium amenities.', 'floors' => [3, 4]],
            ['en' => 'Executive Suite', 'ar' => 'جناح تنفيذي', 'occ' => [2, 4], 'size' => 55, 'price' => 280,
                'desc' => 'A separate living area, executive lounge access, and panoramic views.', 'floors' => [5, 6]],
            ['en' => 'Family Room', 'ar' => 'غرفة عائلية', 'occ' => [4, 6], 'size' => 48, 'price' => 220,
                'desc' => 'Two queen beds and extra space, ideal for families.', 'floors' => [8, 9]],
            ['en' => 'Presidential Suite', 'ar' => 'الجناح الرئاسي', 'occ' => [2, 4], 'size' => 95, 'price' => 550,
                'desc' => 'The hotel\'s finest suite — private terrace, dining room, and butler service.', 'floors' => [10]],
        ];

        foreach ($types as $i => $t) {
            $roomType = RoomType::create([
                'name' => ['en' => $t['en'], 'ar' => $t['ar']],
                'description' => ['en' => $t['desc'], 'ar' => $t['desc']],
                'amenities' => ['WiFi', 'Air Conditioning', 'Mini Bar', 'Flat-screen TV', 'Safe'],
                'base_occupancy' => $t['occ'][0],
                'max_occupancy' => $t['occ'][1],
                'size_sqm' => $t['size'],
                'base_price_usd' => $t['price'],
                'is_active' => true,
                'sort_order' => $i,
            ]);

            $this->attachPhotos($roomType, ["{$t['en']} — Bed", "{$t['en']} — Bathroom", "{$t['en']} — View"]);

            $roomNumber = 1;
            foreach ($t['floors'] as $floor) {
                foreach (range(1, 3) as $unit) {
                    Room::create([
                        'room_type_id' => $roomType->id,
                        'number' => "{$floor}0" . $roomNumber,
                        'floor' => $floor,
                        'status' => 'available',
                        'is_active' => true,
                    ]);
                    $roomNumber++;
                }
                $roomNumber = 1;
            }
        }

        // One inactive room type — proves public endpoints correctly hide it.
        RoomType::factory()->inactive()->create([
            'name' => ['en' => 'Retired Annex Room', 'ar' => 'غرفة الملحق (متوقفة)'],
        ]);
    }

    private function facilities(): void
    {
        $facilities = [
            ['en' => 'Fitness Center', 'ar' => 'مركز اللياقة البدنية', 'hours' => '6:00 AM – 10:00 PM'],
            ['en' => 'Spa & Wellness', 'ar' => 'سبا وعافية', 'hours' => '9:00 AM – 9:00 PM'],
            ['en' => 'Outdoor Pool', 'ar' => 'مسبح خارجي', 'hours' => '7:00 AM – 8:00 PM'],
            ['en' => 'Business Center', 'ar' => 'مركز الأعمال', 'hours' => '24 hours'],
            ['en' => 'Kids Club', 'ar' => 'نادي الأطفال', 'hours' => '10:00 AM – 6:00 PM'],
        ];

        foreach ($facilities as $i => $f) {
            $facility = Facility::create([
                'name' => ['en' => $f['en'], 'ar' => $f['ar']],
                'description' => ['en' => "Enjoy our {$f['en']}, open to all guests.", 'ar' => 'مرفق متاح لجميع النزلاء.'],
                'location' => ['en' => 'Ground Floor', 'ar' => 'الطابق الأرضي'],
                'hours' => ['en' => $f['hours'], 'ar' => $f['hours']],
                'is_active' => true,
                'sort_order' => $i,
            ]);
            $this->attachPhotos($facility, ["{$f['en']} — Overview"]);
        }
    }

    private function diningVenues(): void
    {
        $venues = [
            ['en' => 'Al Sham Restaurant', 'ar' => 'مطعم الشام', 'cuisine' => 'Levantine', 'hours' => '7:00 AM – 11:00 PM'],
            ['en' => 'Damascus Rooftop Lounge', 'ar' => 'روف دمشق', 'cuisine' => 'International', 'hours' => '5:00 PM – 1:00 AM'],
            ['en' => 'Poolside Café', 'ar' => 'مقهى المسبح', 'cuisine' => 'Light bites & grill', 'hours' => '10:00 AM – 6:00 PM'],
        ];

        foreach ($venues as $i => $v) {
            $venue = DiningVenue::create([
                'name' => ['en' => $v['en'], 'ar' => $v['ar']],
                'description' => ['en' => "{$v['en']} — {$v['cuisine']} cuisine in the heart of the hotel.", 'ar' => 'تجربة طعام مميزة.'],
                'cuisine_type' => ['en' => $v['cuisine'], 'ar' => $v['cuisine']],
                'location' => ['en' => 'Level 1', 'ar' => 'الطابق الأول'],
                'hours' => ['en' => $v['hours'], 'ar' => $v['hours']],
                'is_active' => true,
                'sort_order' => $i,
            ]);
            $this->attachPhotos($venue, ["{$v['en']} — Dining Room", "{$v['en']} — Signature Dish"]);
        }
    }

    private function eventSpaces(): void
    {
        $spaces = [
            ['en' => 'Grand Ballroom', 'ar' => 'القاعة الكبرى', 'capacity' => 500],
            ['en' => 'Emerald Conference Room', 'ar' => 'قاعة الزمرد', 'capacity' => 80],
            ['en' => 'Garden Terrace', 'ar' => 'تراس الحديقة', 'capacity' => 150],
        ];

        foreach ($spaces as $i => $s) {
            $space = EventSpace::create([
                'name' => ['en' => $s['en'], 'ar' => $s['ar']],
                'description' => ['en' => "{$s['en']} — ideal for weddings, conferences, and galas.", 'ar' => 'مساحة فعاليات مثالية.'],
                'capacity' => $s['capacity'],
                'location' => ['en' => 'Conference Level', 'ar' => 'طابق المؤتمرات'],
                'amenities' => ['en' => 'Projector, WiFi, Catering, A/V equipment', 'ar' => 'جهاز عرض، واي فاي، تقديم طعام'],
                'is_active' => true,
                'sort_order' => $i,
            ]);
            $this->attachPhotos($space, ["{$s['en']} — Setup"]);
        }
    }

    private function pages(): void
    {
        $pages = [
            ['slug' => 'about-us', 'title' => 'About Carlton Hotel', 'content' => 'Carlton Hotel has welcomed guests to Damascus since 1998, blending timeless hospitality with modern comfort.'],
            ['slug' => 'terms-and-conditions', 'title' => 'Terms & Conditions', 'content' => 'By booking with Carlton Hotel you agree to our cancellation, payment, and stay policies outlined here.'],
            ['slug' => 'privacy-policy', 'title' => 'Privacy Policy', 'content' => 'Carlton Hotel respects your privacy. This page describes what data we collect and how it is used.'],
            ['slug' => 'faq', 'title' => 'Frequently Asked Questions', 'content' => 'Answers to the most common questions about booking, check-in, and hotel amenities.'],
        ];

        foreach ($pages as $i => $p) {
            Page::create([
                'slug' => $p['slug'],
                'title' => ['en' => $p['title'], 'ar' => $p['title']],
                'content' => ['en' => $p['content'], 'ar' => $p['content']],
                'is_active' => true,
                'sort_order' => $i,
            ]);
        }
    }

    private function promotions(): void
    {
        $promos = [
            ['en' => 'Early Bird Booking', 'ar' => 'حجز مبكر', 'desc' => 'Book 30 days ahead and save 15% on any room type.'],
            ['en' => 'Honeymoon Package', 'ar' => 'باقة شهر العسل', 'desc' => 'Complimentary suite upgrade and a bottle of wine for newlyweds.'],
            ['en' => 'Long Stay Discount', 'ar' => 'خصم الإقامة الطويلة', 'desc' => 'Stay 7 nights or more and save 20%.'],
        ];

        foreach ($promos as $i => $p) {
            $promo = Promotion::create([
                'title' => ['en' => $p['en'], 'ar' => $p['ar']],
                'description' => ['en' => $p['desc'], 'ar' => $p['desc']],
                'terms' => ['en' => 'Subject to availability. Cannot be combined with other offers.', 'ar' => 'حسب التوفر.'],
                'valid_from' => now()->toDateString(),
                'valid_until' => now()->addMonths(3)->toDateString(),
                'is_active' => true,
                'sort_order' => $i,
            ]);
            $this->attachPhotos($promo, ["{$p['en']} — Banner"]);
        }
    }
}
