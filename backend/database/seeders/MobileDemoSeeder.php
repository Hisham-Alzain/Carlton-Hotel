<?php

namespace Database\Seeders;

use App\Actions\Review\RecalculateRatingAction;
use App\Enums\BedType;
use App\Enums\ModifierType;
use App\Enums\RoomView;
use App\Models\Amenity;
use App\Models\DiningVenue;
use App\Models\Guest;
use App\Models\HomeSlider;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\PromoCode;
use App\Models\Promotion;
use App\Models\RestaurantTable;
use App\Models\Review;
use App\Models\Room;
use App\Models\RoomType;
use Database\Seeders\Support\CopiesMobileAssetImages;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;

/**
 * The content the Flutter demo screens hardcode, seeded into the API with the
 * app's own artwork.
 *
 * Source of truth: `mobile/lib/constants/demo_data.dart` (the room options,
 * restaurants, menu, hero copy and promo the screens render) and
 * `mobile/assets/images` (the photographs). Wiring the app to the real API
 * should therefore be a matter of deleting `demo_data.dart`, not of retyping
 * its contents here — keep the two in step when either moves.
 *
 * Idempotent throughout: keyed on `name->en` / slug / code, so re-running
 * updates rows in place and re-copies the gallery rather than duplicating it.
 *
 * Runs last in DatabaseSeeder because it deliberately overwrites the generic
 * menu ServiceCatalogSeeder gives every venue, and seeds its own reviews so the
 * room and restaurant cards render the ratings the design shows.
 */
class MobileDemoSeeder extends Seeder
{
    use CopiesMobileAssetImages;

    /**
     * Amenities the demo room options reference that AmenitySeeder's base
     * catalog does not carry. The rest are reused by slug.
     */
    private const EXTRA_AMENITIES = [
        ['slug' => 'butler-service',  'icon' => 'butler', 'en' => 'Butler Service',  'ar' => 'خدمة الخادم الشخصي'],
        ['slug' => 'private-terrace', 'icon' => 'view',   'en' => 'Private Terrace', 'ar' => 'تراس خاص'],
    ];

    private const ROOM_DESCRIPTION_EN =
        'An exceptional suite that blends traditional Syrian elegance with modern luxury. '
        . 'Floor-to-ceiling windows frame dramatic city views while the private balcony '
        . 'overlooks the historic Old City skyline. Features a marble jacuzzi, hand-crafted '
        . 'furnishings, and complimentary butler service.';

    private const ROOM_DESCRIPTION_AR =
        'جناح استثنائي يمزج بين الأناقة السورية التقليدية والفخامة العصرية. نوافذ ممتدة من '
        . 'الأرض حتى السقف تُطل على المدينة، وشرفة خاصة تشرف على أفق المدينة القديمة التاريخية. '
        . 'يضم جاكوزي رخامي وأثاثاً مصنوعاً يدوياً وخدمة خادم شخصي مجانية.';

    /**
     * Mirrors `DemoData.roomOptions` — the rich model the booking flow's room
     * details screen renders. The sparser Home `DemoData.rooms` list describes
     * the same three suites, so only this one is seeded.
     */
    private const ROOM_TYPES = [
        [
            'en' => 'Grand Damascus Suite', 'ar' => 'جناح دمشق الكبير',
            'view' => RoomView::CITY, 'size' => 85, 'price' => 280, 'sort' => 0,
            'rooms' => [['number' => '812', 'floor' => 8], ['number' => '504', 'floor' => 5], ['number' => '806', 'floor' => 8]],
            'images' => ['room_classic_courtyard.jpg', 'room_deluxe_city.jpg', 'room_premier_terrace.jpg'],
            'amenities' => ['city-view-balcony', 'jacuzzi', 'work-desk', 'smart-tv', 'tea-coffee-station', 'in-room-safe'],
            'highlights' => ['jacuzzi', 'city-view-balcony', 'butler-service', 'tea-coffee-station'],
        ],
        [
            'en' => 'Deluxe City View Suite', 'ar' => 'جناح ديلوكس بإطلالة على المدينة',
            'view' => RoomView::CITY, 'size' => 48, 'price' => 240, 'sort' => 1,
            'rooms' => [['number' => '601', 'floor' => 6], ['number' => '602', 'floor' => 6]],
            'images' => ['room_deluxe_city.jpg', 'room_classic_courtyard.jpg'],
            'amenities' => ['city-view-balcony', 'work-desk', 'smart-tv', 'tea-coffee-station'],
            'highlights' => ['city-view-balcony', 'work-desk', 'smart-tv', 'tea-coffee-station'],
        ],
        [
            // The design calls the view "Terrace"; RoomView has no such case, so
            // the outlook stays CITY and the `private-terrace` amenity carries it.
            'en' => 'Premier Terrace Suite', 'ar' => 'جناح التراس الأول',
            'view' => RoomView::CITY, 'size' => 52, 'price' => 320, 'sort' => 2,
            'rooms' => [['number' => '901', 'floor' => 9], ['number' => '902', 'floor' => 9]],
            'images' => ['room_premier_terrace.jpg', 'room_deluxe_city.jpg'],
            'amenities' => ['private-terrace', 'jacuzzi', 'work-desk', 'smart-tv'],
            'highlights' => ['jacuzzi', 'private-terrace', 'butler-service', 'tea-coffee-station'],
        ],
    ];

    /** Mirrors `DemoData.restaurants`; the About copy is `DemoData.restaurantAbout`. */
    private const VENUES = [
        [
            'en' => 'Al-Sham Restaurant', 'ar' => 'مطعم الشام', 'sort' => 0,
            'image' => 'restaurant_alsham.jpg',
            // DemoData.restaurantGallery — shown on the venue's info tab.
            'gallery' => ['gallery1.png', 'gallery2.png', 'gallery3.png', 'gallery4.png'],
        ],
        [
            'en' => 'Al-Qamar Restaurant', 'ar' => 'مطعم القمر', 'sort' => 1,
            'image' => 'restaurant_alqamar.jpg', 'gallery' => [],
        ],
        [
            'en' => 'ELENA Restaurant', 'ar' => 'مطعم إيلينا', 'sort' => 2,
            'image' => 'restaurant_elena.jpg', 'gallery' => [],
        ],
    ];

    private const VENUE_CUISINE_EN  = 'Syrian · Mediterranean';
    private const VENUE_CUISINE_AR  = 'سوري · متوسطي';
    private const VENUE_HOURS       = '7:00 AM – 11:00 PM';
    private const VENUE_LOCATION_EN = 'Ground Floor, Carlton Hotel';
    private const VENUE_LOCATION_AR = 'الطابق الأرضي، فندق كارلتون';

    private const VENUE_ABOUT_EN =
        'Experience the finest Syrian and Mediterranean cuisine in an atmosphere of refined '
        . 'elegance. Traditional Damascene flavors meet modern culinary techniques, served in a '
        . 'beautifully appointed dining room with hand-painted ceilings and soft candlelight.';

    private const VENUE_ABOUT_AR =
        'استمتع بأرقى المأكولات السورية والمتوسطية في أجواء من الأناقة الراقية. تلتقي نكهات دمشق '
        . 'التقليدية بتقنيات الطهي الحديثة، وتُقدَّم في قاعة طعام أنيقة بأسقف مرسومة يدوياً وإضاءة '
        . 'شموع هادئة.';

    /** Mirrors `DemoData.restaurantMenu`; every venue opens with the same card. */
    private const MENU = [
        ['slug' => 'breakfast', 'en' => 'Breakfast', 'ar' => 'فطور', 'items' => [
            ['en' => 'Ful Medames', 'ar' => 'فول مدمس', 'price' => 5, 'vegan' => true, 'image' => 'food_ful.png',
             'den' => 'Slow-cooked fava beans, olive oil, lemon, cumin, fresh herbs',
             'dar' => 'فول مطهو ببطء، زيت زيتون، ليمون، كمون، أعشاب طازجة'],
            ['en' => 'Continental Spread', 'ar' => 'إفطار كونتيننتال', 'price' => 15, 'vegan' => false, 'image' => 'food_spread.png',
             'den' => 'Pastries, seasonal fruits, yoghurt, honey, Arabic coffee',
             'dar' => 'معجنات، فواكه موسمية، لبن، عسل، قهوة عربية'],
            ['en' => 'Garden Fresh Omelette', 'ar' => 'أومليت الخضار الطازجة', 'price' => 8, 'vegan' => false, 'image' => 'food_omelette.png',
             'den' => 'Eggs, bell peppers, vegetables, cheese, served with toast',
             'dar' => 'بيض، فلفل ملون، خضار، جبن، يُقدَّم مع الخبز المحمّص'],
        ]],
        ['slug' => 'starters', 'en' => 'Starters', 'ar' => 'مقبلات', 'items' => [
            ['en' => 'Hummus Beiruti', 'ar' => 'حمص بيروتي', 'price' => 6, 'vegan' => true, 'image' => 'food_ful.png',
             'den' => 'Chickpea purée, tahini, garlic and parsley.',
             'dar' => 'معجون الحمص، طحينة، ثوم وبقدونس.'],
            ['en' => 'Fattoush', 'ar' => 'فتوش', 'price' => 7, 'vegan' => false, 'image' => 'food_omelette.png',
             'den' => 'Crisp greens, sumac and toasted pita.',
             'dar' => 'خضار مقرمشة، سمّاق وخبز محمّص.'],
        ]],
        ['slug' => 'main', 'en' => 'Mains', 'ar' => 'أطباق رئيسية', 'items' => [
            ['en' => 'Lamb Shish', 'ar' => 'شيش لحم', 'price' => 22, 'vegan' => false, 'image' => 'food_spread.png',
             'den' => 'Chargrilled lamb skewers, rice and grilled tomato.',
             'dar' => 'أسياخ لحم غنم مشوية، أرز وطماطم مشوية.'],
            ['en' => 'Sea Bass Sayadieh', 'ar' => 'صيادية سمك القاروص', 'price' => 26, 'vegan' => false, 'image' => 'food_omelette.png',
             'den' => 'Spiced rice, caramelised onions and pine nuts.',
             'dar' => 'أرز بالبهارات، بصل مكرمل وصنوبر.'],
        ]],
        ['slug' => 'dessert', 'en' => 'Desserts', 'ar' => 'حلويات', 'items' => [
            ['en' => 'Knafeh Nabulsieh', 'ar' => 'كنافة نابلسية', 'price' => 9, 'vegan' => false, 'image' => 'food_spread.png',
             'den' => 'Warm cheese pastry, semolina and rose syrup.',
             'dar' => 'معجنات الجبن الدافئة، سميد وشراب الورد.'],
        ]],
        ['slug' => 'beverages', 'en' => 'Beverages', 'ar' => 'مشروبات', 'items' => [
            ['en' => 'Mint Lemonade', 'ar' => 'ليموناضة بالنعناع', 'price' => 4, 'vegan' => true, 'image' => 'food_ful.png',
             'den' => 'Fresh lemon, mint and a touch of honey.',
             'dar' => 'ليمون طازج، نعناع ولمسة من العسل.'],
        ]],
    ];

    /** The three homepage heroes, with the stills the Flutter app ships. */
    private const SLIDERS = [
        ['image' => 'hero_home.png', 'sort' => 0,
         'hen' => 'Where every moment is composed', 'har' => 'حيث تُنسَّق كل لحظة',
         'den' => 'A landmark of luxury in the heart of Damascus', 'dar' => 'معلَم من الفخامة في قلب دمشق'],
        ['image' => 'hero_experience.png', 'sort' => 1,
         'hen' => 'A Quiet Luxury Experience', 'har' => 'تجربة فخامة هادئة',
         'den' => 'Explore authentic experiences, crafted just for you.', 'dar' => 'اكتشف تجارب أصيلة، صُمِّمت خصيصاً لك.'],
        ['image' => 'hero_dining.png', 'sort' => 2,
         'hen' => 'Refined flavors, timeless elegance', 'har' => 'نكهات راقية وأناقة خالدة',
         'den' => 'A refined dining experience, timeless hospitality.', 'dar' => 'تجربة طعام راقية وضيافة خالدة.'],
    ];

    public function run(RecalculateRatingAction $recalculate): void
    {
        $amenities = $this->amenities();
        $roomTypes = $this->roomTypes($amenities);
        $venues    = $this->diningVenues();

        $this->menus($venues);
        $this->homeSliders();
        $this->promotion();
        $this->promoCode();

        $this->rate($roomTypes->merge($venues), $recalculate);
    }

    /**
     * Ensure every amenity the demo rooms reference exists, and return them
     * keyed by slug. AmenitySeeder owns the base six; only the two it lacks are
     * created here, the rest are read back.
     *
     * @return \Illuminate\Support\Collection<string, Amenity>
     */
    private function amenities(): \Illuminate\Support\Collection
    {
        $nextSort = (int) Amenity::max('sort_order') + 1;

        foreach (self::EXTRA_AMENITIES as $i => $a) {
            Amenity::updateOrCreate(
                ['slug' => $a['slug']],
                [
                    'name'       => ['en' => $a['en'], 'ar' => $a['ar']],
                    'icon'       => $a['icon'],
                    'is_active'  => true,
                    'sort_order' => $nextSort + $i,
                ],
            );
        }

        return Amenity::all()->keyBy('slug');
    }

    /**
     * @param  \Illuminate\Support\Collection<string, Amenity> $amenities
     * @return \Illuminate\Support\Collection<int, RoomType>
     */
    private function roomTypes(\Illuminate\Support\Collection $amenities): \Illuminate\Support\Collection
    {
        $seeded = collect();

        foreach (self::ROOM_TYPES as $t) {
            $roomType = RoomType::updateOrCreate(
                ['name->en' => $t['en']],
                [
                    'name'        => ['en' => $t['en'], 'ar' => $t['ar']],
                    'description' => ['en' => self::ROOM_DESCRIPTION_EN, 'ar' => self::ROOM_DESCRIPTION_AR],
                    // Legacy free-text column, kept in step with the pivot below.
                    'amenities'   => collect($t['amenities'])
                        ->map(fn (string $slug) => $amenities[$slug]->getTranslation('name', 'en'))
                        ->all(),
                    'view_type'   => $t['view'],
                    'bed_types'   => [BedType::KING->value],
                    // The design shows "2 guests"; max allows the demo booking's
                    // 2 adults + 1 child to fit the same suite.
                    'base_occupancy'     => 2,
                    'max_occupancy'      => 3,
                    'size_sqm'           => $t['size'],
                    'base_price_usd'     => $t['price'],
                    // "Free cancellation until 48 hours before check-in."
                    'cancellation_hours' => 48,
                    'is_active'          => true,
                    'sort_order'         => $t['sort'],
                ],
            );

            $this->syncAmenities($roomType, $amenities, $t['amenities'], $t['highlights']);
            $this->attachAssetImages($roomType, $t['images']);
            $this->rooms($roomType, $t['rooms']);

            $seeded->push($roomType);
        }

        return $seeded;
    }

    /**
     * @param \Illuminate\Support\Collection<string, Amenity> $amenities
     * @param array<int, string>                             $slugs
     * @param array<int, string>                             $highlightSlugs
     */
    private function syncAmenities(
        RoomType $roomType,
        \Illuminate\Support\Collection $amenities,
        array $slugs,
        array $highlightSlugs,
    ): void {
        // Highlights are shown on the room card and may name an amenity the
        // full list omits (e.g. Butler Service) — the union is what gets attached.
        $all = collect($highlightSlugs)->merge($slugs)->unique()->values();

        $roomType->amenityList()->sync(
            $all->mapWithKeys(fn (string $slug, int $i) => [
                $amenities[$slug]->id => [
                    'is_highlight' => in_array($slug, $highlightSlugs, true),
                    'sort_order'   => $i,
                ],
            ])->all(),
        );
    }

    /** @param array<int, array{number: string, floor: int}> $rooms */
    private function rooms(RoomType $roomType, array $rooms): void
    {
        foreach ($rooms as $r) {
            $room = Room::updateOrCreate(
                ['number' => $r['number']],
                [
                    'room_type_id' => $roomType->id,
                    'floor'        => $r['floor'],
                    'status'       => 'available',
                    'is_active'    => true,
                ],
            );

            // Room 812 is the in-stay room every "current stay" screen shows.
            if ($r['number'] === '812') {
                $this->attachAssetImages($room, ['stay_room.png']);
            }
        }
    }

    /** @return \Illuminate\Support\Collection<int, DiningVenue> */
    private function diningVenues(): \Illuminate\Support\Collection
    {
        $seeded = collect();

        foreach (self::VENUES as $v) {
            $venue = DiningVenue::updateOrCreate(
                ['name->en' => $v['en']],
                [
                    'name'         => ['en' => $v['en'], 'ar' => $v['ar']],
                    'description'  => ['en' => self::VENUE_ABOUT_EN, 'ar' => self::VENUE_ABOUT_AR],
                    'cuisine_type' => ['en' => self::VENUE_CUISINE_EN, 'ar' => self::VENUE_CUISINE_AR],
                    'location'     => ['en' => self::VENUE_LOCATION_EN, 'ar' => self::VENUE_LOCATION_AR],
                    'hours'        => ['en' => self::VENUE_HOURS, 'ar' => self::VENUE_HOURS],
                    'is_active'    => true,
                    'sort_order'   => $v['sort'],
                ],
            );

            $this->attachAssetImages($venue, array_merge([$v['image']], $v['gallery']));
            $this->tables($venue);

            $seeded->push($venue);
        }

        return $seeded;
    }

    /**
     * Bookable tables for the "Reserve a Table" screen. ServiceCatalogSeeder
     * only covers the venues that existed when it ran, so these venues — created
     * afterwards — would otherwise have nothing to reserve. Capacities span the
     * 1–8 guests the reservation stepper offers.
     */
    private function tables(DiningVenue $venue): void
    {
        $initial = strtoupper(substr($venue->getTranslation('name', 'en'), 0, 1));

        foreach ([2, 2, 4, 4, 6, 8] as $i => $capacity) {
            RestaurantTable::updateOrCreate(
                ['dining_venue_id' => $venue->id, 'table_number' => $initial . '-' . ($i + 1)],
                ['capacity' => $capacity, 'is_active' => true],
            );
        }
    }

    /**
     * Overwrite each venue's menu with the demo card. ServiceCatalogSeeder has
     * already given every active venue a generic menu under the same slugs, so
     * these must update in place — the (venue, slug) pair is unique.
     *
     * @param \Illuminate\Support\Collection<int, DiningVenue> $venues
     */
    private function menus(\Illuminate\Support\Collection $venues): void
    {
        foreach ($venues as $venue) {
            foreach (self::MENU as $i => $c) {
                $category = MenuCategory::updateOrCreate(
                    ['dining_venue_id' => $venue->id, 'slug' => $c['slug']],
                    [
                        'name'       => ['en' => $c['en'], 'ar' => $c['ar']],
                        'sort_order' => $i,
                        'is_active'  => true,
                    ],
                );

                // Anything the generic seeder left under this category is not in
                // the demo card — drop it so the menu matches the design exactly.
                $keep = collect($c['items'])->pluck('en');
                $category->items()
                    ->get()
                    ->reject(fn (MenuItem $item) => $keep->contains($item->getTranslation('name', 'en')))
                    ->each(function (MenuItem $item): void {
                        $this->purgeImages($item);
                        $item->delete();
                    });

                foreach ($c['items'] as $item) {
                    $menuItem = MenuItem::updateOrCreate(
                        ['menu_category_id' => $category->id, 'name->en' => $item['en']],
                        [
                            'name'        => ['en' => $item['en'], 'ar' => $item['ar']],
                            'description' => ['en' => $item['den'], 'ar' => $item['dar']],
                            'price_usd'   => $item['price'],
                            'is_vegan'    => $item['vegan'],
                            'is_active'   => true,
                        ],
                    );

                    $this->attachAssetImages($menuItem, [$item['image']]);
                }
            }
        }
    }

    private function homeSliders(): void
    {
        foreach (self::SLIDERS as $s) {
            $slider = HomeSlider::updateOrCreate(
                ['header_text->en' => $s['hen']],
                [
                    'header_text'      => ['en' => $s['hen'], 'ar' => $s['har']],
                    'location'         => ['en' => 'Damascus · Syria', 'ar' => 'دمشق · سوريا'],
                    'description_text' => ['en' => $s['den'], 'ar' => $s['dar']],
                    'is_active'        => true,
                    'sort_order'       => $s['sort'],
                ],
            );

            $this->attachAssetImages($slider, [$s['image']]);
        }
    }

    /** The single "Special Offers" card on the homepage. */
    private function promotion(): void
    {
        $title = '20% Off — Book 30 Days Early';

        $promotion = Promotion::updateOrCreate(
            ['title->en' => $title],
            [
                'title'       => ['en' => $title, 'ar' => 'خصم 20٪ — احجز قبل 30 يوماً'],
                'description' => [
                    'en' => 'Secure your suite in advance and enjoy a significant saving on your stay.',
                    'ar' => 'احجز جناحك مسبقاً واستمتع بتوفير كبير على إقامتك.',
                ],
                'secondary_description' => [
                    'en' => 'Applies to every suite, subject to availability.',
                    'ar' => 'ينطبق على جميع الأجنحة، حسب التوفر.',
                ],
                'terms' => [
                    'en' => 'Valid on stays booked at least 30 days ahead. Cannot be combined with other offers.',
                    'ar' => 'صالح على الحجوزات قبل 30 يوماً على الأقل. لا يمكن دمجه مع عروض أخرى.',
                ],
                'valid_from'  => now()->toDateString(),
                'valid_until' => now()->addMonths(6)->toDateString(),
                'is_active'   => true,
                'sort_order'  => 0,
            ],
        );

        $this->attachAssetImages($promotion, ['backgroundimg2.png']);
    }

    /** The code the checkout screen shows applied — "Promo CARLTON10 (−10%)". */
    private function promoCode(): void
    {
        PromoCode::updateOrCreate(
            ['code' => 'CARLTON10'],
            [
                'type'       => ModifierType::PERCENTAGE,
                'value'      => 10,
                'expires_at' => now()->addYear(),
                'max_uses'   => null,
                'is_active'  => true,
            ],
        );
    }

    /**
     * Give the seeded content the near-perfect rating the cards show. Ten
     * reviewers at 5 stars with one at 4 average to 4.9 — the exact figure in
     * the design. The review count itself is derived, so it reads 10 rather
     * than the design's 142.
     *
     * @param \Illuminate\Support\Collection<int, Model> $subjects
     */
    private function rate(\Illuminate\Support\Collection $subjects, RecalculateRatingAction $recalculate): void
    {
        $guests = Guest::query()->take(10)->get();

        if ($guests->isEmpty()) {
            $this->command?->warn('  ! no guests to review with — ratings left untouched');

            return;
        }

        $last = $guests->count() - 1;

        foreach ($subjects as $subject) {
            foreach ($guests as $i => $guest) {
                Review::updateOrCreate(
                    [
                        'guest_id'        => $guest->id,
                        'reviewable_type' => $subject->getMorphClass(),
                        'reviewable_id'   => $subject->getKey(),
                    ],
                    [
                        'rating'           => $i === $last ? 4 : 5,
                        'comment'          => 'Exceptional from arrival to checkout — the detail here is a class apart.',
                        'is_verified_stay' => $i % 2 === 0,
                        'is_published'     => true,
                    ],
                );
            }

            $recalculate->handle($subject);
        }
    }
}
