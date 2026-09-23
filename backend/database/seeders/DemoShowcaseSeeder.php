<?php

namespace Database\Seeders;

use App\Actions\Review\RecalculateRatingAction;
use App\Enums\BookableType;
use App\Enums\CheckInApprovalStatus;
use App\Enums\ConversationStatus;
use App\Enums\Department;
use App\Enums\DevicePlatform;
use App\Enums\EventInquiryStatus;
use App\Enums\EventType;
use App\Enums\FolioStatus;
use App\Enums\ModifierType;
use App\Enums\NotificationType;
use App\Enums\PaymentMethod;
use App\Enums\PricingScope;
use App\Enums\ReservationSource;
use App\Enums\ReservationStatus;
use App\Enums\RoomStatus;
use App\Enums\ServiceBookingStatus;
use App\Enums\ServiceRequestPriority;
use App\Enums\ServiceRequestStatus;
use App\Enums\TicketCategory;
use App\Enums\TicketSource;
use App\Enums\TicketStatus;
use App\Models\CheckInApproval;
use App\Models\Conversation;
use App\Models\DeviceToken;
use App\Models\DiningVenue;
use App\Models\EventInquiry;
use App\Models\EventSpace;
use App\Models\Experience;
use App\Models\Facility;
use App\Models\Faq;
use App\Models\Folio;
use App\Models\FolioItem;
use App\Models\GalleryItem;
use App\Models\Guest;
use App\Models\GuestDocument;
use App\Models\GuestNotification;
use App\Models\HomeSlider;
use App\Models\JournalPost;
use App\Models\MenuItem;
use App\Models\Message;
use App\Models\Payment;
use App\Models\PoolCabana;
use App\Models\PricingRule;
use App\Models\PromoCode;
use App\Models\Promotion;
use App\Models\Refund;
use App\Models\Reservation;
use App\Models\ReservationRoom;
use App\Models\RestaurantTable;
use App\Models\Review;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\ServiceBooking;
use App\Models\ServiceItem;
use App\Models\ServiceRequest;
use App\Models\SpaService;
use App\Models\Testimonial;
use App\Models\Ticket;
use App\Models\Transfer;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\Support\AttachesDemoPhotos;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * High-volume demo data for exercising the mobile app, the public website and
 * the staff dashboard end to end.
 *
 * Runs after every other seeder and layers on top of their records:
 *
 *   - real photographs replace the GD placeholders on every CMS entity
 *     (see AttachesDemoPhotos for the sources);
 *   - ~70 extra guests, and reservations from a year back to six months ahead
 *     in every ReservationStatus. A room allocator makes sure no room is ever
 *     double-booked, so the availability search stays truthful;
 *   - every workflow the dashboard queues is seeded in every state: service
 *     requests, service bookings, check-in approvals, chats, tickets, event
 *     inquiries, reviews awaiting moderation, folios, payments and refunds;
 *   - CMS edge states: expired/upcoming/inactive promotions, exhausted and
 *     paused promo codes, holiday pricing, and records in the recycle bin.
 *
 * Long demos: in-house stays run up to six weeks past the seed date and
 * bookings reach six months ahead, so the data stays coherent for weeks. Two
 * soft holds get a 30-day expiry so `pending_verification` stays visible in
 * the dashboard; a third expires within minutes, so the
 * `booking:release-holds` job has something real to release.
 *
 * Deterministic: a fixed-seed Randomizer drives every choice, so two fresh
 * seeds produce the same data (dates shift with the seed day, not the shape).
 * The volume part is not re-runnable on top of itself — run it on a fresh
 * database (`migrate:fresh --seed`); it skips itself if it finds its guests.
 */
class DemoShowcaseSeeder extends Seeder
{
    use AttachesDemoPhotos;

    private const GUEST_EMAIL_DOMAIN = 'guests.carlton.test';

    private Randomizer $rng;

    private CarbonImmutable $today;

    /** room_id => list of [check_in, check_out) date strings holding it */
    private array $occupancy = [];

    /** room_type_id => active room ids */
    private array $roomsByType = [];

    /** @var Collection<int, RoomType> active, bookable room types keyed by id */
    private Collection $roomTypes;

    /** @var array<string, User> staff by persona key */
    private array $staff = [];

    /** @var array<int, string> booking codes already taken */
    private array $bookingCodes = [];

    public function run(RecalculateRatingAction $recalculate): void
    {
        $this->rng   = new Randomizer(new Mt19937(20260923));
        $this->today = CarbonImmutable::today();

        $this->command?->info('Demo showcase: photos');
        $this->photos();

        if (Guest::where('email', 'like', '%@' . self::GUEST_EMAIL_DOMAIN)->exists()) {
            $this->command?->warn('  Demo showcase volume already seeded — skipping. Use migrate:fresh --seed to rebuild.');

            return;
        }

        $this->command?->info('Demo showcase: staff, catalog edge states');
        $this->staff();
        $this->promoAndPricingEdgeStates();

        $this->command?->info('Demo showcase: guests and reservations');
        $this->loadInventory();
        $guests = $this->guests();
        $reservations = $this->reservations($guests);
        $this->roomStatuses();

        $this->command?->info('Demo showcase: in-stay activity, folios, payments');
        foreach ($reservations as $reservation) {
            $this->serviceRequests($reservation);
            $this->serviceBookings($reservation);
        }
        foreach ($reservations as $reservation) {
            $this->preArrival($reservation);
            $this->folioAndPayments($reservation);
        }

        $this->command?->info('Demo showcase: chat, tickets, events, reviews, notifications');
        $conversations = $this->conversations($guests);
        $this->tickets($guests, $conversations);
        $inquiries = $this->eventInquiries($guests);
        $this->reviews($reservations, $recalculate);
        $this->notifications($reservations, $inquiries);
        $this->deviceTokens($guests);

        $this->command?->info('Demo showcase: recycle bin');
        $this->recycleBin();

        $this->command?->info(sprintf(
            '  Seeded %d guests, %d reservations (%s).',
            $guests->count(),
            $reservations->count(),
            $reservations->countBy(fn (Reservation $r) => $r->status->value)
                ->map(fn ($n, $s) => "{$s}: {$n}")->implode(', '),
        ));
    }

    // ---------------------------------------------------------------------
    // Photos
    // ---------------------------------------------------------------------

    private const ROOM_TYPE_PHOTOS = [
        'Standard Queen'     => ['web:room-twin-city.jpg', 'web:bath-marble-twin.jpg'],
        'Deluxe King'        => ['web:room-king-city.jpg', 'web:bath-marble-king.jpg', 'web:suite-living-bedroom.jpg'],
        'Executive Suite'    => ['web:suite-living-bedroom.jpg', 'web:junior-suite-living.jpg', 'web:bath-marble-king.jpg'],
        'Family Room'        => ['web:room-twin-city.jpg', 'web:junior-suite-living.jpg', 'web:bath-marble-twin.jpg'],
        'Presidential Suite' => ['web:suite-grand-living.jpg', 'web:suite-lounge-chandelier.jpg', 'web:room-king-city.jpg', 'web:bath-marble-king.jpg'],
        'Retired Annex Room' => ['web:room-king-city.jpg'],
        // MobileDemoSeeder already gives these their app artwork; the web
        // photos are appended so the room-detail gallery has depth.
        'Grand Damascus Suite'   => ['web:suite-grand-living.jpg', 'web:bath-marble-king.jpg'],
        'Deluxe City View Suite' => ['web:room-king-city.jpg', 'web:bath-marble-twin.jpg'],
        'Premier Terrace Suite'  => ['web:suite-lounge-chandelier.jpg', 'web:bath-marble-king.jpg'],
    ];

    private const VENUE_PHOTOS = [
        'Barada Brasserie'        => ['web:club-lounge-buffet.jpg', 'web:dining-brunch.jpg', 'mobile:hero_dining.png'],
        'Damascus Rooftop Lounge' => ['web:club-lounge-rooftop.jpg', 'web:club-lounge-glass.jpg', 'web:dining-bar-interior.jpg'],
        'Poolside Café'           => ['web:dining-brunch.jpg', 'web:club-lounge-glass.jpg'],
        // Replaces the gallery1–4.png the mobile app no longer ships.
        'Al-Sham Restaurant'      => ['web:club-lounge-buffet.jpg', 'web:dining-tomahawk.jpg', 'web:dining-brunch.jpg', 'web:dining-wine-bar.jpg'],
        'Al-Qamar Restaurant'     => ['web:dining-wine-bar.jpg', 'mobile:hero_dining.png'],
        'ELENA Restaurant'        => ['web:dining-bar-interior.jpg', 'web:dining-tomahawk.jpg'],
    ];

    /** Keyword in the English title => photos. First match wins. */
    private const EXPERIENCE_PHOTOS = [
        'Spice'       => ['web:damascus-souq-hamidiyah.jpg'],
        "Chef's"      => ['web:dining-tomahawk.jpg', 'mobile:hero_dining.png'],
        'Bab Sharqi'  => ['web:damascus-bab-sharqi.png'],
        'Shopping'    => ['web:damascus-souq-hamidiyah.jpg'],
        'Cinema'      => ['web:suite-grand-living.jpg'],
        'Umayyad'     => ['web:damascus-umayyad-mosque.png', 'web:damascus-bab-sharqi.png'],
        'Hamidiyah'   => ['web:damascus-souq-hamidiyah.jpg'],
        'Qasioun'     => ['web:damascus-qasioun.png'],
        'Azm Palace'  => ['web:damascus-bab-sharqi.png', 'web:damascus-umayyad-mosque.png'],
        'Calligraphy' => ['web:damascus-calligraphy.png'],
        'Meze'        => ['web:dining-brunch.jpg', 'mobile:hero_dining.png'],
        'Rooftop'     => ['web:club-lounge-rooftop.jpg'],
    ];

    private const FACILITY_PHOTOS = [
        'Fitness Center'  => ['web:club-lounge-glass.jpg'],
        'Spa & Wellness'  => ['web:bath-marble-king.jpg', 'web:bath-marble-twin.jpg'],
        'Outdoor Pool'    => ['web:club-lounge-rooftop.jpg'],
        'Business Center' => ['web:club-lounge-glass.jpg', 'web:club-lounge-buffet.jpg'],
        'Kids Club'       => ['web:junior-suite-living.jpg'],
    ];

    /** Gallery captions were written for these exact photos. */
    private const GALLERY_PHOTOS = [
        'Grand Suite panoramic'       => 'web:suite-grand-living.jpg',
        'modern chandelier'           => 'web:suite-lounge-chandelier.jpg',
        'Deluxe king bedroom'         => 'web:room-king-city.jpg',
        'Classic twin bedroom'        => 'web:room-twin-city.jpg',
        'Premier suite sitting room'  => 'web:suite-living-bedroom.jpg',
        'Freestanding marble bath'    => 'web:bath-marble-king.jpg',
        'Marble double vanity'        => 'web:bath-marble-twin.jpg',
        'Club lounge'                 => 'web:club-lounge-glass.jpg',
        'mashrabiyya'                 => 'web:club-lounge-buffet.jpg',
        'Rooftop lounge'              => 'web:club-lounge-rooftop.jpg',
        'Tomahawk'                    => 'web:dining-tomahawk.jpg',
        'brunch'                      => 'web:dining-brunch.jpg',
        'Artisan Syrian cuisine'      => 'mobile:hero_dining.png',
        'ornate chandelier'           => 'mobile:hero_home.png',
        'courtyard fountain'          => 'mobile:hero_experience.png',
        'Damascus skylight'           => 'mobile:hero_home.png',
        'Ancient architecture'        => 'web:damascus-bab-sharqi.png',
        'Hamidiyah'                   => 'web:damascus-souq-hamidiyah.jpg',
        'minaret'                     => 'web:damascus-umayyad-mosque.png',
    ];

    private const HOME_SLIDER_PHOTOS = [
        'mobile:hero_home.png', 'web:club-lounge-rooftop.jpg', 'mobile:hero_experience.png',
        'web:suite-grand-living.jpg', 'mobile:hero_dining.png',
    ];

    private const JOURNAL_PHOTOS = [
        'Lighting'  => ['mobile:hero_home.png'],
        'Façade'    => ['mobile:hero_experience.png', 'web:damascus-bab-sharqi.png'],
        'Garden'    => ['web:club-lounge-buffet.jpg'],
        'Arrivals'  => ['mobile:hero_experience.png'],
        'Suites'    => ['web:suite-living-bedroom.jpg'],
    ];

    private const PROMOTION_PHOTOS = [
        'Early Bird'  => ['web:room-king-city.jpg'],
        'Honeymoon'   => ['web:suite-living-bedroom.jpg', 'web:bath-marble-king.jpg'],
        'Long Stay'   => ['web:junior-suite-living.jpg'],
        '30 Days'     => ['web:suite-lounge-chandelier.jpg'],
    ];

    private const EVENT_SPACE_PHOTOS = [
        'Grand Ballroom'          => ['mobile:hero_home.png', 'web:club-lounge-buffet.jpg'],
        'Emerald Conference Room' => ['web:club-lounge-glass.jpg'],
        'Garden Terrace'          => ['web:club-lounge-rooftop.jpg'],
    ];

    private function photos(): void
    {
        foreach (RoomType::withTrashed()->get() as $roomType) {
            $photos = self::ROOM_TYPE_PHOTOS[$roomType->getTranslation('name', 'en')] ?? null;
            if ($photos) {
                $this->replacePlaceholders($roomType, $photos, appendWhenReal: true);
            }
        }

        foreach (DiningVenue::withTrashed()->get() as $venue) {
            $photos = self::VENUE_PHOTOS[$venue->getTranslation('name', 'en')] ?? null;
            if ($photos) {
                $this->replacePlaceholders($venue, $photos, appendWhenReal: true);
            }
        }

        $this->byKeyword(Experience::withTrashed()->get(), 'title', self::EXPERIENCE_PHOTOS);
        $this->byKeyword(JournalPost::withTrashed()->get(), 'title', self::JOURNAL_PHOTOS);
        $this->byKeyword(Promotion::withTrashed()->get(), 'title', self::PROMOTION_PHOTOS);

        foreach (Facility::withTrashed()->get() as $facility) {
            $photos = self::FACILITY_PHOTOS[$facility->getTranslation('name', 'en')] ?? null;
            if ($photos) {
                $this->replacePlaceholders($facility, $photos);
            }
        }

        foreach (EventSpace::withTrashed()->get() as $space) {
            $photos = self::EVENT_SPACE_PHOTOS[$space->getTranslation('name', 'en')] ?? null;
            if ($photos) {
                $this->replacePlaceholders($space, $photos);
            }
        }

        foreach (GalleryItem::withTrashed()->get() as $item) {
            $caption = $item->getTranslation('caption', 'en');
            foreach (self::GALLERY_PHOTOS as $keyword => $photo) {
                if (Str::contains($caption, $keyword, ignoreCase: true)) {
                    $this->replacePlaceholders($item, [$photo]);
                    break;
                }
            }
        }

        foreach (HomeSlider::withTrashed()->orderBy('sort_order')->get()->values() as $i => $slider) {
            $this->replacePlaceholders($slider, [self::HOME_SLIDER_PHOTOS[$i % count(self::HOME_SLIDER_PHOTOS)]]);
        }

        $this->menuPhotos();
    }

    /** @param array<string, array<int, string>> $map */
    private function byKeyword(Collection $models, string $field, array $map): void
    {
        foreach ($models as $model) {
            $title = $model->getTranslation($field, 'en');
            foreach ($map as $keyword => $photos) {
                if (Str::contains($title, $keyword, ignoreCase: true)) {
                    $this->replacePlaceholders($model, $photos);
                    break;
                }
            }
        }
    }

    /**
     * Only dishes a photo genuinely shows get one: breakfast plates get the
     * brunch spread, grilled mains the plated lamb. Desserts, drinks and
     * starters stay imageless rather than wearing someone else's dish.
     */
    private function menuPhotos(): void
    {
        foreach (MenuItem::with('category')->get() as $item) {
            if ($item->images()->get()->reject(fn ($m) => $this->isPlaceholder($m))->isNotEmpty()) {
                continue;
            }

            $name = $item->getTranslation('name', 'en');
            $photo = match (true) {
                $item->category?->slug === 'breakfast'                    => 'web:dining-brunch.jpg',
                Str::contains($name, ['Lamb', 'Kebab'], ignoreCase: true) => 'mobile:hero_dining.png',
                Str::contains($name, 'Shawarma', ignoreCase: true)        => 'web:dining-tomahawk.jpg',
                default                                                   => null,
            };

            if ($photo) {
                $this->replacePlaceholders($item, [$photo], appendWhenReal: true);
            }
        }
    }

    // ---------------------------------------------------------------------
    // Staff & catalog edge states
    // ---------------------------------------------------------------------

    private function staff(): void
    {
        foreach (['reception', 'kitchen', 'housekeeping', 'concierge', 'events'] as $role) {
            $this->staff[$role] = User::where('email', "{$role}@carlton.demo")->firstOrFail();
        }
        $this->staff['super'] = User::where('email', 'super@carlton.demo')->firstOrFail();

        $extra = [
            ['key' => 'reception2',    'name' => 'Rawan Haddad',  'email' => 'reception2@carlton.demo',    'role' => 'reception',       'active' => true],
            ['key' => 'housekeeping2', 'name' => 'Hani Mansour',  'email' => 'housekeeping2@carlton.demo', 'role' => 'housekeeping',    'active' => true],
            ['key' => 'kitchen2',      'name' => 'Kinda Sabbagh', 'email' => 'kitchen2@carlton.demo',      'role' => 'kitchen',         'active' => true],
            ['key' => 'content2',      'name' => 'Lina Content',  'email' => 'manager@carlton.demo',       'role' => 'content_manager', 'active' => true],
            // Deactivated account: login must be refused even with the right password.
            ['key' => 'former',        'name' => 'Faris Former',  'email' => 'former@carlton.demo',        'role' => 'reception',       'active' => false],
        ];

        foreach ($extra as $e) {
            $user = User::factory()->staff()->create(['name' => $e['name'], 'email' => $e['email'], 'is_active' => $e['active']]);
            $user->assignRole($e['role']);
            $this->staff[$e['key']] = $user;
        }
    }

    private function staffFor(Department $department): User
    {
        $pool = match ($department) {
            Department::KITCHEN                         => ['kitchen', 'kitchen2'],
            Department::HOUSEKEEPING, Department::MAINTENANCE => ['housekeeping', 'housekeeping2'],
            Department::CONCIERGE                       => ['concierge'],
            Department::RECEPTION                       => ['reception', 'reception2'],
            Department::EVENTS, Department::SALES       => ['events'],
        };

        return $this->staff[$this->pick($pool)];
    }

    private function promoAndPricingEdgeStates(): void
    {
        $codes = [
            ['code' => 'FAMILY50',  'type' => ModifierType::FLAT,       'value' => 50, 'expires_at' => $this->today->addMonths(5), 'max_uses' => 40,   'used_count' => 0,  'is_active' => true],
            ['code' => 'LONGSTAY15','type' => ModifierType::PERCENTAGE, 'value' => 15, 'expires_at' => null,                       'max_uses' => null, 'used_count' => 0,  'is_active' => true],
            // Every use consumed — the booking flow must reject it as exhausted.
            ['code' => 'FLASH30',   'type' => ModifierType::PERCENTAGE, 'value' => 30, 'expires_at' => $this->today->addMonth(),   'max_uses' => 10,   'used_count' => 10, 'is_active' => true],
            // Switched off by staff while still in date.
            ['code' => 'PAUSED15',  'type' => ModifierType::PERCENTAGE, 'value' => 15, 'expires_at' => $this->today->addMonths(3), 'max_uses' => null, 'used_count' => 4,  'is_active' => false],
        ];
        foreach ($codes as $code) {
            PromoCode::create($code);
        }

        $presidential = RoomType::where('name->en', 'Presidential Suite')->first();
        $family       = RoomType::where('name->en', 'Family Room')->first();
        if ($presidential) {
            PricingRule::create([
                'room_type_id' => $presidential->id, 'scope' => PricingScope::HOLIDAY,
                'starts_on' => $this->today->addMonths(3)->startOfMonth()->toDateString(),
                'ends_on' => $this->today->addMonths(3)->startOfMonth()->addDays(9)->toDateString(),
                'modifier_type' => ModifierType::PERCENTAGE, 'modifier_value' => 35, 'is_active' => true,
            ]);
        }
        if ($family) {
            // A seasonal surcharge that ran last year — out of date, and off.
            PricingRule::create([
                'room_type_id' => $family->id, 'scope' => PricingScope::SEASONAL,
                'starts_on' => $this->today->subMonths(9)->toDateString(),
                'ends_on' => $this->today->subMonths(7)->toDateString(),
                'modifier_type' => ModifierType::FLAT, 'modifier_value' => 30, 'is_active' => false,
            ]);
        }

        $promotions = [
            ['en' => 'Winter Escape', 'ar' => 'عطلة الشتاء', 'from' => -150, 'until' => -60, 'active' => true, 'photo' => 'web:suite-lounge-chandelier.jpg',
             'den' => 'Three nights for the price of two through the winter months.', 'dar' => 'ثلاث ليالٍ بسعر ليلتين خلال أشهر الشتاء.'],
            ['en' => 'Eid Celebration Stay', 'ar' => 'إقامة العيد', 'from' => 45, 'until' => 75, 'active' => true, 'photo' => 'web:club-lounge-buffet.jpg',
             'den' => 'Celebrate Eid with a festive breakfast, late checkout and sweets in your suite.', 'dar' => 'احتفل بالعيد مع فطور احتفالي ومغادرة متأخرة وحلويات في جناحك.'],
            ['en' => 'Old City Weekend', 'ar' => 'عطلة المدينة القديمة', 'from' => -10, 'until' => 120, 'active' => false, 'photo' => 'web:damascus-bab-sharqi.png',
             'den' => 'A guided walk through the Old City with two nights in a Deluxe King.', 'dar' => 'جولة بصحبة مرشد في المدينة القديمة مع ليلتين في غرفة ديلوكس كينغ.'],
        ];
        foreach ($promotions as $i => $p) {
            $promotion = Promotion::create([
                'title' => ['en' => $p['en'], 'ar' => $p['ar']],
                'description' => ['en' => $p['den'], 'ar' => $p['dar']],
                'terms' => ['en' => 'Subject to availability. Not combinable with other offers.', 'ar' => 'حسب التوفر. لا يمكن دمجه مع عروض أخرى.'],
                'valid_from' => $this->today->addDays($p['from'])->toDateString(),
                'valid_until' => $this->today->addDays($p['until'])->toDateString(),
                'is_active' => $p['active'], 'sort_order' => 10 + $i,
            ]);
            $this->placePhoto($promotion, $p['photo']);
        }
    }

    // ---------------------------------------------------------------------
    // Guests
    // ---------------------------------------------------------------------

    private const ARABIC_GUESTS = [
        ['Khaled', 'Mahmoud'], ['Nour', 'Al-Atassi'], ['Hiba', 'Qabbani'], ['Tarek', 'Shami'],
        ['Rim', 'Haddad'], ['Bassel', 'Kurdi'], ['Lama', 'Jabri'], ['Majd', 'Hamwi'],
        ['Dima', 'Azem'], ['Fadi', 'Mardini'], ['Ghada', 'Sukkar'], ['Hussam', 'Tabbaa'],
        ['Joud', 'Kayyali'], ['Kinan', 'Barazi'], ['Lubna', 'Midani'], ['Mazen', 'Shaalan'],
        ['Nada', 'Homsi'], ['Qusai', 'Rifai'], ['Rasha', 'Khoury'], ['Samer', 'Nahhas'],
        ['Tala', 'Zaim'], ['Wael', 'Daher'], ['Yara', 'Halabi'], ['Zaid', 'Bitar'],
        ['Alaa', 'Sabbagh'], ['Batoul', 'Qassab'], ['Firas', 'Hakim'], ['Heba', 'Mousa'],
        ['Iyad', 'Karam'], ['Jana', 'Sayegh'], ['Louay', 'Saleh'], ['Maysa', 'Farah'],
        ['Obada', 'Naser'], ['Ruba', 'Aswad'], ['Salma', 'Diab'], ['Hadi', 'Jaber'],
        ['Mira', 'Abboud'], ['Nizar', 'Salloum'], ['Reem', 'Hijazi'], ['Yamen', 'Masri'],
    ];

    private const INTERNATIONAL_GUESTS = [
        ['Emma', 'Laurent', '+33'], ['Lukas', 'Becker', '+49'], ['Sofia', 'Rossi', '+39'],
        ['James', 'Whitmore', '+44'], ['Olivia', 'Carter', '+1'], ['Mateo', 'García', '+34'],
        ['Anna', 'Novak', '+420'], ['Hiroshi', 'Tanaka', '+81'], ['Elif', 'Yılmaz', '+90'],
        ['Karim', 'Benali', '+212'], ['Mariam', 'Al-Sabah', '+965'], ['Omar', 'Al-Farsi', '+968'],
        ['Noura', 'Al-Qahtani', '+966'], ['Rashed', 'Al-Mansoori', '+971'], ['Leila', 'Haddadi', '+961'],
        ['Youssef', 'Aboud', '+962'], ['Chloé', 'Martin', '+33'], ['Henrik', 'Larsen', '+45'],
        ['Isabella', 'Costa', '+351'], ['Daniel', 'Moreau', '+32'],
    ];

    /** @return Collection<int, Guest> every guest that may hold a reservation */
    private function guests(): Collection
    {
        $n = 0;
        $make = function (string $first, string $last, ?string $phone, string $locale, array $flags) use (&$n): Guest {
            $n++;
            $joined = $this->today->subDays($this->int(20, 540))->setTime($this->int(8, 22), $this->int(0, 59));
            $email = in_array('phone_only', $flags, true)
                ? null
                : Str::slug(Str::ascii("{$first}.{$last}"), '.') . ".{$n}@" . self::GUEST_EMAIL_DOMAIN;

            $guest = new Guest([
                'first_name' => $first, 'last_name' => $last, 'name' => "{$first} {$last}",
                'phone' => in_array('email_only', $flags, true) ? null : $phone,
                'phone_country' => in_array('email_only', $flags, true) ? null : ($locale === 'ar' ? 'SY' : null),
                'email' => $email,
                'preferred_locale' => $locale,
                'phone_verified_at' => in_array('unverified', $flags, true) || in_array('email_only', $flags, true) ? null : $joined,
                'email_verified_at' => $email && ! in_array('unverified', $flags, true) && $this->chance(70) ? $joined : null,
            ]);

            return $this->persist($guest, $joined);
        };

        $guests = new Collection;

        foreach (self::ARABIC_GUESTS as $i => [$first, $last]) {
            $flags = match (true) {
                $i >= 37 => ['unverified'],   // never finished OTP — soft-hold owners
                $i === 35 => ['phone_only'],
                default  => [],
            };
            $guests->push($make($first, $last, sprintf('+96394%07d', 1000 + $i), $this->chance(75) ? 'ar' : 'en', $flags));
        }

        foreach (self::INTERNATIONAL_GUESTS as $i => [$first, $last, $cc]) {
            $flags = $i >= 18 ? ['email_only'] : [];
            $guests->push($make($first, $last, $cc . sprintf('55%07d', 2000 + $i), in_array($cc, ['+965', '+968', '+966', '+971', '+961', '+962', '+212'], true) ? 'ar' : 'en', $flags));
        }

        // The named personas from GuestSeeder join the pool so the mobile app
        // shows them a real stay history, not a single booking.
        $named = Guest::whereIn('phone', [
            GuestSeeder::PHONE_CHECKED_IN, GuestSeeder::PHONE_CONFIRMED,
            GuestSeeder::PHONE_CHECKED_OUT, GuestSeeder::PHONE_CANCELLED,
        ])->get();

        return $named->concat($guests)->values();
    }

    // ---------------------------------------------------------------------
    // Reservations
    // ---------------------------------------------------------------------

    private function loadInventory(): void
    {
        $this->roomTypes = RoomType::where('is_active', true)->whereHas('rooms')->get()->keyBy('id');

        foreach (Room::where('is_active', true)->orderBy('number')->get() as $room) {
            $this->occupancy[$room->id] = [];
            $this->roomsByType[$room->room_type_id][] = $room->id;
        }

        $this->bookingCodes = Reservation::pluck('booking_code')->all();

        // Existing bookings hold rooms too: pinned ones on their room, the
        // unassigned ones on whichever room of their type is free.
        $held = ReservationRoom::with('reservation')->get()
            ->filter(fn (ReservationRoom $rr) => $rr->reservation->status !== ReservationStatus::CANCELLED);

        foreach ($held->sortByDesc(fn ($rr) => $rr->room_id !== null) as $rr) {
            $in  = $rr->reservation->check_in->toDateString();
            $out = $rr->reservation->check_out->toDateString();
            $roomId = $rr->room_id ?? $this->freeRoomExcluding($rr->room_type_id, $in, $out);
            if ($roomId) {
                $this->occupancy[$roomId][] = [$in, $out];
            }
        }
    }

    /**
     * The whole timeline: a year of history, the house as it stands today,
     * and six months of forward bookings.
     *
     * @param Collection<int, Guest> $guests
     * @return Collection<int, Reservation>
     */
    private function reservations(Collection $guests): Collection
    {
        $verified   = $guests->filter(fn (Guest $g) => $g->phone_verified_at || $g->email_verified_at)->values();
        $unverified = $guests->filter(fn (Guest $g) => ! $g->phone_verified_at && ! $g->email_verified_at)->values();
        $personas   = $guests->take(4)->values(); // Ahmad, Layla, Sara, Omar
        $created    = new Collection;

        $add = function (?Reservation $r) use ($created): void {
            if ($r) {
                $created->push($r);
            }
        };

        // --- History: every persona gets past stays, then the house fills.
        foreach ($personas as $persona) {
            foreach (range(1, $this->int(2, 3)) as $_) {
                $in = $this->today->subDays($this->int(30, 360));
                $add($this->book($persona, $in, $in->addDays($this->int(2, 5))));
            }
        }
        for ($i = 0; $i < 190; $i++) {
            $in = $this->today->subDays($this->int(3, 365));
            $out = $in->addDays($this->weighted([1 => 20, 2 => 30, 3 => 25, 4 => 10, 5 => 8, 7 => 5, 10 => 2]));
            if ($out->greaterThan($this->today)) {
                continue;
            }
            $add($this->book($this->pick($verified), $in, $out));
        }

        // --- In house today: long stays so the demo holds for weeks.
        for ($i = 0; $i < 18; $i++) {
            $in = $this->today->subDays($this->int(0, 12));
            $add($this->book($this->pick($verified), $in, $this->today->addDays($this->weighted([3 => 3, 7 => 4, 14 => 5, 21 => 4, 30 => 3, 42 => 2]))));
        }
        // Due out today — the departures list.
        for ($i = 0; $i < 3; $i++) {
            $add($this->book($this->pick($verified), $this->today->subDays($this->int(2, 5)), $this->today, ReservationStatus::CHECKED_IN));
        }
        // Due in today — the arrivals list, not checked in yet.
        for ($i = 0; $i < 4; $i++) {
            $add($this->book($this->pick($verified), $this->today, $this->today->addDays($this->int(2, 6)), ReservationStatus::CONFIRMED));
        }

        // --- Future: personas first, so each has something upcoming.
        foreach ($personas as $persona) {
            $in = $this->today->addDays($this->int(10, 60));
            $add($this->book($persona, $in, $in->addDays($this->int(2, 5)), ReservationStatus::CONFIRMED));
        }
        for ($i = 0; $i < 110; $i++) {
            $in = $this->today->addDays($this->weighted([
                $this->int(1, 7) => 20, $this->int(8, 30) => 35, $this->int(31, 90) => 30, $this->int(91, 180) => 15,
            ]));
            $add($this->book($this->pick($verified), $in, $in->addDays($this->weighted([1 => 15, 2 => 30, 3 => 25, 4 => 15, 6 => 10, 9 => 5]))));
        }

        // --- Soft holds from guests who never verified.
        foreach ([30 * 24 * 60, 30 * 24 * 60, 4] as $i => $minutes) {
            $in = $this->today->addDays($this->int(14, 45));
            $r = $this->book($unverified->get($i % $unverified->count()), $in, $in->addDays(2), ReservationStatus::PENDING_VERIFICATION);
            $r?->forceFill(['hold_expires_at' => now()->addMinutes($minutes)])->save();
            $add($r);
        }

        return $created;
    }

    /**
     * Book one reservation, picking its status from where the dates fall
     * unless one is forced. Returns null when no room of any type is free.
     */
    private function book(Guest $guest, CarbonImmutable $in, CarbonImmutable $out, ?ReservationStatus $status = null): ?Reservation
    {
        $status ??= ReservationStatus::from(match (true) {
            $out->lessThanOrEqualTo($this->today) => $this->weighted(['checked_out' => 86, 'cancelled' => 14]),
            $in->lessThanOrEqualTo($this->today)  => ReservationStatus::CHECKED_IN->value,
            default => $this->weighted(['confirmed' => 58, 'pending' => 28, 'cancelled' => 14]),
        });

        $roomCount = $this->chance(8) ? 2 : 1;
        $inS = $in->toDateString();
        $outS = $out->toDateString();

        // Random preferred type first, then any other type with free rooms.
        $typeIds = $this->rng->shuffleArray($this->roomTypes->keys()->all());
        $type = null;
        $rooms = [];
        foreach ($typeIds as $typeId) {
            $rooms = [];
            for ($k = 0; $k < $roomCount; $k++) {
                $roomId = $this->freeRoomExcluding($typeId, $inS, $outS, $rooms);
                if ($roomId === null) {
                    break;
                }
                $rooms[] = $roomId;
            }
            if (count($rooms) === $roomCount) {
                $type = $this->roomTypes[$typeId];
                break;
            }
        }
        if ($type === null) {
            return null;
        }

        // A cancelled booking released its room, so it holds no inventory.
        if ($status !== ReservationStatus::CANCELLED) {
            foreach ($rooms as $roomId) {
                $this->occupancy[$roomId][] = [$inS, $outS];
            }
        }

        $nights = (int) $in->diffInDays($out);
        $perRoom = round((float) $type->base_price_usd * $nights, 2);
        $subtotal = $perRoom * $roomCount;

        $promo = null;
        if ($this->chance(15)) {
            $promo = $this->pick(PromoCode::whereIn('code', [BookingSeeder::PROMO_ACTIVE, BookingSeeder::PROMO_WELCOME, 'FAMILY50', 'LONGSTAY15'])->get());
        }
        $total = match (true) {
            $promo === null                         => $subtotal,
            $promo->type === ModifierType::FLAT     => max(0, $subtotal - (float) $promo->value),
            default                                 => round($subtotal * (1 - (float) $promo->value / 100), 2),
        };

        $walkIn = $in->lessThanOrEqualTo($this->today) && $status !== ReservationStatus::CANCELLED && $this->chance(12);
        $bookedAt = $walkIn
            ? $in->setTime($this->int(12, 20), $this->int(0, 59))
            : $in->subDays($this->int(1, 75))->setTime($this->int(7, 23), $this->int(0, 59));
        if ($bookedAt->greaterThan(now())) {
            $bookedAt = CarbonImmutable::now()->subHours($this->int(1, 30));
        }

        $reservation = new Reservation([
            'guest_id' => $guest->id,
            'booking_code' => $this->bookingCode(),
            'source' => $walkIn ? ReservationSource::WALK_IN : ReservationSource::DIRECT,
            'check_in' => $inS, 'check_out' => $outS,
            'status' => $status,
            'payment_method' => $walkIn || $this->chance(35) ? PaymentMethod::CASH : PaymentMethod::ON_ARRIVAL,
            'total_usd' => $total,
            'promo_code_id' => $promo?->id,
            'last_name' => $guest->last_name,
            'phone' => $guest->phone,
            'checked_in_at' => in_array($status, [ReservationStatus::CHECKED_IN, ReservationStatus::CHECKED_OUT], true)
                ? $in->setTime($this->int(13, 21), $this->int(0, 59)) : null,
            'checked_out_at' => $status === ReservationStatus::CHECKED_OUT
                ? $out->setTime($this->int(8, 12), $this->int(0, 59)) : null,
            'dnd_until' => $status === ReservationStatus::CHECKED_IN && $this->chance(15) ? now()->addHours($this->int(1, 5)) : null,
        ]);
        $this->persist($reservation, $bookedAt);

        if ($promo) {
            $promo->increment('used_count');
        }

        // Rooms are pinned once the guest is in the building, and for some
        // imminent arrivals; later bookings wait for staff assignment.
        $pin = in_array($status, [ReservationStatus::CHECKED_IN, ReservationStatus::CHECKED_OUT], true)
            || ($status === ReservationStatus::CONFIRMED && $in->diffInDays($this->today, true) <= 3 && $this->chance(50));

        foreach ($rooms as $roomId) {
            $this->persist(new ReservationRoom([
                'reservation_id' => $reservation->id,
                'room_type_id' => $type->id,
                'room_id' => $pin ? $roomId : null,
                'price_usd' => $perRoom,
            ]), $bookedAt);
        }

        return $reservation;
    }

    /** First room of the type free for [in, out) that is not already taken by this booking. */
    private function freeRoomExcluding(int $typeId, string $in, string $out, array $exclude = []): ?int
    {
        foreach ($this->roomsByType[$typeId] ?? [] as $roomId) {
            if (in_array($roomId, $exclude, true)) {
                continue;
            }
            $clash = false;
            foreach ($this->occupancy[$roomId] ?? [] as [$oIn, $oOut]) {
                if ($in < $oOut && $oIn < $out) {
                    $clash = true;
                    break;
                }
            }
            if (! $clash) {
                return $roomId;
            }
        }

        return null;
    }

    private function bookingCode(): string
    {
        do {
            $code = 'CARL-' . strtoupper(bin2hex($this->rng->getBytes(4)));
        } while (in_array($code, $this->bookingCodes, true));

        return $this->bookingCodes[] = $code;
    }

    /**
     * Housekeeping board: occupied where a guest is in house, two empty rooms
     * out for maintenance (the ones whose next arrival is furthest away), the
     * rest available.
     */
    private function roomStatuses(): void
    {
        $inHouse = ReservationRoom::whereNotNull('room_id')
            ->whereHas('reservation', fn ($q) => $q->where('status', ReservationStatus::CHECKED_IN))
            ->pluck('room_id')->unique()->all();

        Room::whereIn('id', $inHouse)->update(['status' => RoomStatus::OCCUPIED]);

        $todayS = $this->today->toDateString();
        $empty = collect($this->occupancy)
            ->reject(fn ($spans, $roomId) => in_array($roomId, $inHouse, true))
            ->reject(fn ($spans) => collect($spans)->contains(fn ($s) => $s[0] <= $todayS && $todayS < $s[1]))
            ->map(fn ($spans) => collect($spans)->pluck(0)->filter(fn ($in) => $in > $todayS)->min() ?? '9999-12-31')
            ->sortDesc()
            ->keys()->take(2);

        Room::whereIn('id', $empty)->update(['status' => RoomStatus::MAINTENANCE]);
    }

    // ---------------------------------------------------------------------
    // In-stay activity
    // ---------------------------------------------------------------------

    private const REQUEST_NOTES = [
        'room_service' => ['Two Arabic coffees and a fruit plate, please.', 'Club sandwich without mayo.', 'Breakfast for two at 8:00.', 'Could we get fresh orange juice?'],
        'housekeeping' => ['Extra towels and two more pillows.', 'Please clean the room while we are at lunch.', 'Turndown at 21:00, thank you.', 'Need an extra blanket.'],
        'laundry'      => ['Two shirts to be pressed by the evening.', 'Dry clean one suit — urgent, meeting tomorrow.', 'Express laundry for a small bag.'],
        'concierge'    => ['Can you book a table in the Old City for tonight?', 'Recommend a guided tour for Saturday morning.', 'Need a pharmacy nearby.'],
        'transport'    => ['Airport pickup at 18:00 — flight RB442.', 'Taxi to Umayyad Mosque at 10:00.', 'Drop-off at the airport tomorrow 05:30.'],
        'maintenance'  => ['The air conditioning is not cooling.', 'Bathroom sink is draining slowly.', 'TV remote does not work.', 'Bedside lamp is flickering.'],
    ];

    private function serviceRequests(Reservation $reservation): void
    {
        $count = match ($reservation->status) {
            ReservationStatus::CHECKED_IN  => $this->int(2, 5),
            ReservationStatus::CHECKED_OUT => $this->chance(55) ? $this->int(1, 3) : 0,
            default => 0,
        };
        if ($count === 0) {
            return;
        }

        $items = ServiceItem::with('category')->where('is_active', true)->get();

        for ($i = 0; $i < $count; $i++) {
            /** @var ServiceItem $item */
            $item = $this->pick($items);
            $category = $item->category;
            $department = $category->department instanceof Department ? $category->department : Department::from($category->department);

            if ($reservation->status === ReservationStatus::CHECKED_OUT) {
                $at = $this->within($reservation->checked_in_at, $reservation->checked_out_at);
                $status = $this->weighted([ServiceRequestStatus::COMPLETED->value => 85, ServiceRequestStatus::CANCELLED->value => 15]);
            } else {
                $at = $this->within($reservation->checked_in_at, now());
                $status = $this->weighted([
                    ServiceRequestStatus::NEW->value => 30, ServiceRequestStatus::IN_PROGRESS->value => 25,
                    ServiceRequestStatus::COMPLETED->value => 35, ServiceRequestStatus::CANCELLED->value => 10,
                ]);
                // Anything still open should look like it came in recently.
                if (in_array($status, ['new', 'in_progress'], true)) {
                    $at = CarbonImmutable::now()->subMinutes($this->int(5, 300));
                }
            }

            $assigned = match ($status) {
                'new'   => $this->chance(25) ? $this->staffFor($department) : null,
                default => $this->staffFor($department),
            };

            $request = new ServiceRequest([
                'guest_id' => $reservation->guest_id,
                'reservation_id' => $reservation->id,
                'service_item_id' => $item->id,
                'type' => $category->code,
                'department' => $department,
                'status' => $status,
                'priority' => $this->weighted([ServiceRequestPriority::LOW->value => 25, ServiceRequestPriority::NORMAL->value => 55, ServiceRequestPriority::HIGH->value => 20]),
                'assigned_user_id' => $assigned?->id,
                'notes' => $this->chance(80) ? $this->pick(self::REQUEST_NOTES[$category->code] ?? ['']) ?: null : null,
            ]);
            $this->persist($request, $at, $status === 'new' ? $at : $at->addMinutes($this->int(10, 180)));
        }
    }

    private function serviceBookings(Reservation $reservation): void
    {
        $count = match ($reservation->status) {
            ReservationStatus::CHECKED_IN  => $this->int(1, 3),
            ReservationStatus::CHECKED_OUT => $this->chance(40) ? $this->int(1, 2) : 0,
            ReservationStatus::CONFIRMED   => $this->chance(35) ? 1 : 0,
            ReservationStatus::PENDING     => $this->chance(15) ? 1 : 0,
            default => 0,
        };

        for ($i = 0; $i < $count; $i++) {
            $type = $this->pick(BookableType::cases());
            $this->bookables ??= [
                BookableType::SPA_SERVICE->value      => SpaService::where('is_active', true)->orderBy('id')->get(),
                BookableType::RESTAURANT_TABLE->value => RestaurantTable::where('is_active', true)->orderBy('id')->get(),
                BookableType::POOL_CABANA->value      => PoolCabana::where('is_active', true)->orderBy('id')->get(),
                BookableType::TRANSFER->value         => Transfer::where('is_active', true)->orderBy('id')->get(),
            ];
            if ($this->bookables[$type->value]->isEmpty()) {
                continue;
            }
            $bookable = $this->pick($this->bookables[$type->value]);

            $stayStart = CarbonImmutable::parse($reservation->check_in)->setTime(10, 0);
            $stayEnd   = CarbonImmutable::parse($reservation->check_out)->setTime(10, 0);
            $scheduled = $this->within($stayStart, $stayEnd)->setMinute($this->pick([0, 30]))->setSecond(0);
            if ($type === BookableType::TRANSFER) {
                $scheduled = $this->chance(50) ? $stayStart->setTime($this->int(9, 22), 0) : $stayEnd->setTime($this->int(5, 11), 0);
            }

            $status = $scheduled->isPast()
                ? $this->weighted([ServiceBookingStatus::COMPLETED->value => 80, ServiceBookingStatus::CANCELLED->value => 20])
                : $this->weighted([ServiceBookingStatus::PENDING->value => 40, ServiceBookingStatus::CONFIRMED->value => 45, ServiceBookingStatus::CANCELLED->value => 15]);

            $booking = new ServiceBooking([
                'guest_id' => $reservation->guest_id,
                'reservation_id' => $reservation->id,
                'bookable_type' => $type->value,
                'bookable_id' => $bookable->id,
                'scheduled_at' => $scheduled,
                'guest_count' => $type === BookableType::RESTAURANT_TABLE ? $this->int(1, (int) $bookable->capacity) : $this->int(1, 2),
                'status' => $status,
                'notes' => $this->chance(40) ? $this->pick(['Anniversary — a quiet corner, please.', 'One guest is vegetarian.', 'Please call the room 15 minutes before.', 'Two large suitcases.', 'Prefer a female therapist.']) : null,
            ]);
            $bookedAt = CarbonImmutable::parse($reservation->created_at)->addDays($this->int(0, 3));
            $this->persist($booking, $bookedAt->greaterThan(now()) ? CarbonImmutable::now()->subHour() : $bookedAt);
        }
    }

    /** @var array<string, Collection>|null bookable type => active bookables */
    private ?array $bookables = null;

    private ?string $specimenDocument = null;

    private function preArrival(Reservation $reservation): void
    {
        if (CheckInApproval::where('reservation_id', $reservation->id)->exists()) {
            return;
        }

        $daysOut = $this->today->diffInDays(CarbonImmutable::parse($reservation->check_in), false);
        $status = match (true) {
            $reservation->status === ReservationStatus::CHECKED_IN => CheckInApprovalStatus::APPROVED,
            in_array($reservation->status, [ReservationStatus::CONFIRMED, ReservationStatus::PENDING], true)
                && $daysOut <= 45 && $this->chance(65) => CheckInApprovalStatus::from($this->weighted(['pending' => 50, 'approved' => 35, 'rejected' => 15])),
            default => null,
        };
        if ($status === null) {
            return;
        }

        $guest = $reservation->guest;
        $types = $this->chance(30) ? ['passport', 'visa'] : [$this->pick(['passport', 'national_id'])];
        $submittedAt = CarbonImmutable::parse($reservation->created_at)->addHours($this->int(1, 48));
        if ($submittedAt->isFuture()) {
            $submittedAt = CarbonImmutable::now()->subMinutes($this->int(10, 600));
        }

        foreach ($types as $type) {
            $path = "guest-documents/{$guest->uuid}/{$reservation->uuid}/{$type}-demo.jpg";
            Storage::disk('public')->put($path, $this->specimenDocument());
            $this->persist(new GuestDocument([
                'guest_id' => $guest->id, 'reservation_id' => $reservation->id, 'type' => $type, 'file_path' => $path,
            ]), $submittedAt);
        }

        $this->persist(new CheckInApproval([
            'reservation_id' => $reservation->id,
            'status' => $status,
            'approved_by' => $status === CheckInApprovalStatus::PENDING ? null : $this->staff[$this->pick(['reception', 'reception2'])]->id,
            'notes' => match ($status) {
                CheckInApprovalStatus::APPROVED => $this->pick(['Documents verified.', 'ID matches booking name.', null]),
                CheckInApprovalStatus::REJECTED => $this->pick(['Passport photo is blurred — please upload a clearer scan.', 'Document expired; a valid ID is required.', 'Name on ID does not match the booking.']),
                default => null,
            },
        ]), $submittedAt, $status === CheckInApprovalStatus::PENDING ? $submittedAt : $submittedAt->addHours($this->int(1, 20)));
    }

    /** One grey "SPECIMEN" card, reused for every seeded ID upload. */
    private function specimenDocument(): string
    {
        if ($this->specimenDocument !== null) {
            return $this->specimenDocument;
        }

        $img = imagecreatetruecolor(900, 570);
        imagefill($img, 0, 0, imagecolorallocate($img, 226, 230, 236));
        $ink = imagecolorallocate($img, 60, 70, 90);
        imagerectangle($img, 20, 20, 879, 549, $ink);
        imagefilledrectangle($img, 50, 110, 290, 400, imagecolorallocate($img, 180, 188, 200));
        imagestring($img, 5, 330, 120, 'SPECIMEN - DEMO IDENTITY DOCUMENT', $ink);
        imagestring($img, 4, 330, 170, 'Seeded by DemoShowcaseSeeder', $ink);
        imagestring($img, 4, 330, 200, 'Not a real document', $ink);
        ob_start();
        imagejpeg($img, null, 80);
        imagedestroy($img);

        return $this->specimenDocument = (string) ob_get_clean();
    }

    // ---------------------------------------------------------------------
    // Money
    // ---------------------------------------------------------------------

    private const MINIBAR = [['Minibar — soft drinks', 12], ['Minibar — snacks', 9], ['Minibar — mineral water', 4], ['Late checkout fee', 40], ['Parking', 15]];

    private function folioAndPayments(Reservation $reservation): void
    {
        $status = $reservation->status;

        if ($status === ReservationStatus::CANCELLED) {
            $this->cancellationRefund($reservation);

            return;
        }
        if (! in_array($status, [ReservationStatus::CHECKED_IN, ReservationStatus::CHECKED_OUT], true)) {
            if ($status === ReservationStatus::CONFIRMED && $reservation->payment_method === PaymentMethod::CASH && $this->chance(30)) {
                $this->payment($reservation, round((float) $reservation->total_usd * 0.3, 2), 'Advance deposit', $reservation->created_at);
            }

            return;
        }

        $settled = $status === ReservationStatus::CHECKED_OUT;
        $items = [[
            'description' => 'Room charge', 'amount_usd' => (float) $reservation->total_usd,
            'source_type' => 'reservation', 'source_id' => $reservation->id,
        ]];

        ServiceRequest::with('serviceItem')->where('reservation_id', $reservation->id)
            ->where('status', ServiceRequestStatus::COMPLETED)->get()
            ->filter(fn (ServiceRequest $r) => (float) $r->serviceItem?->price_usd > 0)
            ->each(function (ServiceRequest $r) use (&$items) {
                $items[] = ['description' => $r->serviceItem->getTranslation('name', 'en'), 'amount_usd' => (float) $r->serviceItem->price_usd, 'source_type' => 'service_request', 'source_id' => $r->id];
            });

        ServiceBooking::where('reservation_id', $reservation->id)->where('status', ServiceBookingStatus::COMPLETED)->get()
            ->each(function (ServiceBooking $b) use (&$items) {
                $bookable = $b->bookable;
                $price = (float) ($bookable->price_usd ?? 0);
                if ($price > 0) {
                    $items[] = ['description' => $bookable->getTranslation('name', 'en'), 'amount_usd' => $price, 'source_type' => 'service_booking', 'source_id' => $b->id];
                }
            });

        foreach (range(1, $this->int(0, 2)) as $_) {
            if ($this->chance(50)) {
                [$label, $amount] = $this->pick(self::MINIBAR);
                $items[] = ['description' => $label, 'amount_usd' => $amount, 'source_type' => 'manual', 'source_id' => null];
            }
        }

        $total = round(array_sum(array_column($items, 'amount_usd')), 2);
        $closedAt = $settled ? CarbonImmutable::parse($reservation->checked_out_at) : null;

        $folio = $this->persist(new Folio([
            'reservation_id' => $reservation->id,
            'status' => $settled ? FolioStatus::SETTLED : FolioStatus::OPEN,
            'subtotal_usd' => $total, 'total_usd' => $total,
            'approved_by_guest_at' => $settled ? $closedAt->subMinutes(20) : null,
            'settled_at' => $closedAt,
        ]), CarbonImmutable::parse($reservation->checked_in_at));

        foreach ($items as $item) {
            $this->persist(new FolioItem(['folio_id' => $folio->id] + $item), CarbonImmutable::parse($reservation->checked_in_at));
        }

        if ($settled) {
            if ($this->chance(35)) {
                $deposit = round($total * 0.4, 2);
                $this->payment($reservation, $deposit, 'Deposit at check-in', $reservation->checked_in_at);
                $this->payment($reservation, round($total - $deposit, 2), 'Balance at checkout', $closedAt);
            } else {
                $this->payment($reservation, $total, $this->pick([null, 'Settled at checkout']), $closedAt);
            }
        } elseif ($this->chance(55)) {
            $this->payment($reservation, round((float) $reservation->total_usd * $this->pick([0.3, 0.5, 1.0]), 2), 'Deposit at check-in', $reservation->checked_in_at);
        }
    }

    private function cancellationRefund(Reservation $reservation): void
    {
        if (! $this->chance(30)) {
            return;
        }

        $paidAt = CarbonImmutable::parse($reservation->created_at)->addHours($this->int(1, 24));
        $payment = $this->payment($reservation, (float) $reservation->total_usd, 'Prepaid at booking', $paidAt);
        $full = $this->chance(65);

        $this->persist(new Refund([
            'payment_id' => $payment->id,
            'amount_usd' => $full ? $payment->amount_usd : round((float) $payment->amount_usd * 0.5, 2),
            'reason' => $full ? 'Cancelled within the free-cancellation window.' : 'Late cancellation — 50% retained per policy.',
            'recorded_by' => $this->staff[$this->pick(['reception', 'reception2'])]->id,
            'status' => 'completed',
        ]), $this->clamp($paidAt->addDays($this->int(1, 10))));
    }

    private function payment(Reservation $reservation, float $amount, ?string $note, $at): Payment
    {
        return $this->persist(new Payment([
            'payable_type' => Reservation::class, 'payable_id' => $reservation->id,
            'method' => 'cash', 'amount_usd' => $amount, 'note' => $note, 'status' => 'completed',
            'recorded_by' => $this->staff[$this->pick(['reception', 'reception2', 'super'])]->id,
        ]), $this->clamp($at));
    }

    // ---------------------------------------------------------------------
    // Chat & tickets
    // ---------------------------------------------------------------------

    private const THREADS = [
        [['guest', 'Hello, is it possible to get an early check-in tomorrow around 10am?'], ['staff', 'Good morning! We will do our best — I have noted 10:00 on your booking.'], ['guest', 'Thank you so much!']],
        [['guest', 'Do you have a shuttle to the Old City?'], ['staff', 'Yes, the City Tour Shuttle leaves at 10:00 and 15:00 from the lobby.'], ['guest', 'Perfect, we will take the 10:00 one.'], ['staff', 'Booked for two. Enjoy!']],
        [['guest', 'The WiFi in my room keeps dropping.'], ['staff', 'Sorry about that — an engineer is on the way to room now.'], ['guest', 'Working now, thanks.']],
        [['guest', 'Can I extend my stay by two nights?'], ['staff', 'Let me check availability for you.'], ['staff', 'Good news, your room is free — shall I extend it?'], ['guest', 'Yes please.']],
        [['guest', 'مرحباً، هل يمكن حجز طاولة لأربعة أشخاص في مطعم الشام الساعة الثامنة؟'], ['staff', 'أهلاً بك، تم حجز الطاولة الساعة الثامنة مساءً.'], ['guest', 'شكراً جزيلاً']],
        [['guest', 'هل يتوفر سرير إضافي للأطفال؟'], ['staff', 'نعم، سنرسله إلى غرفتك خلال نصف ساعة.']],
        [['guest', 'I left my charger in the room after checkout.'], ['staff', 'Housekeeping found it — we can keep it at reception or courier it to you.'], ['guest', 'I will pick it up on Friday.']],
        [['guest', 'Is breakfast included in my booking?']],
        [['guest', 'What time does the pool close?']],
        [['guest', 'هل يمكنني الدفع بالبطاقة عند الوصول؟']],
    ];

    /** @return Collection<int, Conversation> */
    private function conversations(Collection $guests): Collection
    {
        $conversations = new Collection;
        $withStays = $guests->filter(fn (Guest $g) => Reservation::where('guest_id', $g->id)->exists())->values();
        $agents = ['concierge', 'reception', 'reception2'];

        foreach (range(0, 27) as $i) {
            $guest  = $withStays->get(($i * 7) % $withStays->count());
            $thread = self::THREADS[$i % count(self::THREADS)];
            $answered = collect($thread)->contains(fn ($m) => $m[0] === 'staff');
            $closed = $answered && $this->chance(35);
            $agent = $answered ? $this->staff[$this->pick($agents)] : ($this->chance(20) ? $this->staff['concierge'] : null);

            $start = $closed
                ? CarbonImmutable::now()->subDays($this->int(3, 120))->setTime($this->int(8, 22), $this->int(0, 59))
                : CarbonImmutable::now()->subMinutes($this->int(10, 60 * 36));

            $conversation = $this->persist(new Conversation([
                'guest_id' => $guest->id, 'assigned_user_id' => $agent?->id,
                'status' => $closed ? ConversationStatus::CLOSED : ConversationStatus::OPEN,
                'last_message_at' => $start,
            ]), $start);

            $at = $start;
            foreach ($thread as $k => [$side, $body]) {
                $at = $at->addMinutes($this->int(1, 25));
                if ($at->isFuture()) {
                    $at = CarbonImmutable::now()->subSeconds(30 * (count($thread) - $k));
                }
                $sender = $side === 'guest' ? $guest : ($agent ?? $this->staff['concierge']);
                $isLast = $k === count($thread) - 1;
                $message = new Message([
                    'body' => $body,
                    // The last guest message of an open chat stays unread — the
                    // dashboard's unread badge needs something to count.
                    'read_at' => $closed || ! $isLast || $side === 'staff' ? $at->addMinutes(2) : null,
                ]);
                $message->conversation()->associate($conversation);
                $message->sender()->associate($sender);
                $this->persist($message, $at);
            }
            $conversation->forceFill(['last_message_at' => $at])->saveQuietly();
            $conversations->push($conversation);
        }

        return $conversations;
    }

    private const TICKET_SUBJECTS = [
        'inquiry'      => ['Is there parking on site?', 'Do you allow pets?', 'Airport distance and taxi fare', 'Can I host a small meeting in my suite?'],
        'complaint'    => ['Room was not cleaned today', 'Loud music from the rooftop after midnight', 'Wrong amount charged on my folio', 'Cold food delivered by room service'],
        'booking_help' => ['Change reservation dates', 'Add a second room to my booking', 'Booking confirmation email not received', 'Cancel my reservation'],
        'maintenance'  => ['Shower pressure too low', 'Air conditioning leaking water', 'Balcony door does not lock', 'Safe will not open'],
        'other'        => ['Lost property — sunglasses', 'Request an invoice with company name', 'Feedback about the spa', 'Accessibility requirements for my stay'],
    ];

    private function tickets(Collection $guests, Collection $conversations): void
    {
        $n = 0;
        foreach (TicketCategory::cases() as $category) {
            foreach (TicketStatus::cases() as $status) {
                foreach (range(1, $status === TicketStatus::OPEN ? 2 : 1) as $_) {
                    $n++;
                    $department = match ($category) {
                        TicketCategory::BOOKING_HELP => Department::RECEPTION,
                        TicketCategory::MAINTENANCE  => Department::MAINTENANCE,
                        TicketCategory::COMPLAINT    => $this->pick([Department::CONCIERGE, Department::RECEPTION, Department::HOUSEKEEPING]),
                        default                      => Department::CONCIERGE,
                    };
                    $conversation = $this->chance(30) ? $this->pick($conversations) : null;
                    $at = $status === TicketStatus::OPEN
                        ? CarbonImmutable::now()->subMinutes($this->int(15, 60 * 48))
                        : CarbonImmutable::now()->subDays($this->int(1, 200))->setTime($this->int(8, 23), $this->int(0, 59));

                    $this->persist(new Ticket([
                        'guest_id' => $conversation?->guest_id ?? ($this->chance(60) ? $this->pick($guests)->id : null),
                        'chatbot_session_id' => $this->chance(70) ? 'demo-chat-' . bin2hex($this->rng->getBytes(6)) : null,
                        'conversation_id' => $conversation?->id,
                        'subject' => $this->pick(self::TICKET_SUBJECTS[$category->value]),
                        'category' => $category,
                        'status' => $status,
                        'priority' => $this->weighted([1 => 30, 2 => 45, 3 => 25]),
                        'department' => $department,
                        'source' => TicketSource::CHATBOT,
                        'assigned_user_id' => $status === TicketStatus::OPEN ? null : $this->staffFor($department)->id,
                    ]), $at, $status === TicketStatus::OPEN ? $at : $this->clamp($at->addHours($this->int(1, 72))));
                }
            }
        }
    }

    // ---------------------------------------------------------------------
    // Events
    // ---------------------------------------------------------------------

    private const COMPANIES = ['Cham Wings', 'Syriatel', 'Al-Baraka Bank', 'Damascus University', 'Levant Pharma', 'Orient Media', 'Qasioun Group', null, null, null];

    private const REQUIREMENTS = [
        'catering'       => ['Buffet dinner, vegetarian options required', 'Coffee breaks x2 and lunch', 'Plated three-course dinner', 'Traditional Syrian sweets table'],
        'av_equipment'   => ['Projector, two wireless mics, stage lighting', 'Live-stream setup', 'DJ booth and dance floor lighting'],
        'decoration'     => ['White and gold floral theme', 'Branded backdrop and roll-ups', 'Candles and low centrepieces'],
        'accommodation'  => ['Room block of 15 rooms for two nights', 'Bridal suite for the wedding night', 'Speaker rooms x4'],
        'photography'    => ['Photographer and videographer for 6 hours', 'Photo booth'],
    ];

    /** @return Collection<int, EventInquiry> */
    private function eventInquiries(Collection $guests): Collection
    {
        $spaces = EventSpace::all()->keyBy(fn ($s) => $s->getTranslation('name', 'en'));
        $names  = array_merge(self::ARABIC_GUESTS, array_map(fn ($g) => [$g[0], $g[1]], self::INTERNATIONAL_GUESTS));
        $all    = new Collection;
        $n      = 0;

        foreach (EventType::cases() as $type) {
            foreach (EventInquiryStatus::cases() as $status) {
                $n++;
                [$first, $last] = $names[($n * 5) % count($names)];
                $space = match ($type) {
                    EventType::WEDDING, EventType::GALA                                => $spaces->get($this->pick(['Grand Ballroom', 'Garden Terrace'])),
                    EventType::CORPORATE, EventType::CONFERENCE, EventType::PRODUCT_LAUNCH => $spaces->get($this->pick(['Emerald Conference Room', 'Grand Ballroom'])),
                    default                                                            => $spaces->get('Garden Terrace'),
                };
                // Confirmed and cancelled events are split between ones that
                // already happened and ones still ahead; open ones are all ahead.
                $eventDate = in_array($status, [EventInquiryStatus::CONFIRMED, EventInquiryStatus::CANCELLED], true) && $this->chance(50)
                    ? $this->today->subDays($this->int(5, 200))
                    : $this->today->addDays($this->int(12, 270));
                $createdAt = match ($status) {
                    EventInquiryStatus::NEW => CarbonImmutable::now()->subHours($this->int(1, 96)),
                    default => CarbonImmutable::parse($eventDate)->subDays($this->int(30, 120))->setTime($this->int(9, 20), 0)->min(CarbonImmutable::now()->subDay()),
                };
                $linked = $this->chance(25) ? $this->pick($guests) : null;

                $inquiry = $this->persist(new EventInquiry([
                    'guest_id' => $linked?->id,
                    'event_space_id' => $this->chance(85) ? $space?->id : null,
                    'assigned_user_id' => $status === EventInquiryStatus::NEW ? null : $this->staff['events']->id,
                    'name' => $linked?->name ?? "{$first} {$last}",
                    'email' => $linked?->email ?? Str::slug(Str::ascii("{$first}.{$last}"), '.') . '@example.com',
                    'phone' => $linked?->phone ?? ($this->chance(70) ? sprintf('+96393%07d', 3000 + $n) : null),
                    'company' => in_array($type, [EventType::CORPORATE, EventType::CONFERENCE, EventType::PRODUCT_LAUNCH], true) ? $this->pick(array_filter(self::COMPANIES)) : null,
                    'event_type' => $type,
                    'event_date' => $eventDate->toDateString(),
                    'expected_guests' => match ($type) {
                        EventType::WEDDING, EventType::GALA => $this->int(120, 350),
                        EventType::CONFERENCE => $this->int(60, 250),
                        EventType::BIRTHDAY => $this->int(15, 60),
                        default => $this->int(20, 120),
                    },
                    'budget_usd' => $this->chance(80) ? $this->int(15, 400) * 100 : null,
                    'notes' => $this->pick([
                        'We would like to visit the venue first.', 'Flexible on the date by a week either side.',
                        'Please include a quote for accommodation.', 'Need a separate prayer room.', null,
                    ]),
                    'status' => $status,
                    'department' => $type->department(),
                ]), $createdAt, $status === EventInquiryStatus::NEW ? $createdAt : $this->clamp($createdAt->addDays($this->int(1, 7))));

                foreach (array_slice($this->rng->shuffleArray(array_keys(self::REQUIREMENTS)), 0, $this->int(1, 3)) as $reqType) {
                    $inquiry->requirements()->create(['type' => $reqType, 'notes' => $this->pick(self::REQUIREMENTS[$reqType])]);
                }
                $all->push($inquiry);
            }
        }

        return $all;
    }

    // ---------------------------------------------------------------------
    // Reviews, notifications, devices
    // ---------------------------------------------------------------------

    private const REVIEW_COMMENTS = [
        5 => ['en' => ['An unforgettable stay — the staff remembered every detail.', 'The suite was immaculate and the view over Damascus breathtaking.', 'Best breakfast I have had in a hotel. Will be back.'],
              'ar' => ['إقامة لا تُنسى، الموظفون اهتموا بكل التفاصيل.', 'الجناح نظيف جداً والإطلالة على دمشق رائعة.', 'أفضل فطور تناولته في فندق. سأعود بالتأكيد.']],
        4 => ['en' => ['Very comfortable room and friendly reception.', 'Great location, a little noise from the street.', 'Lovely food, service was a bit slow at dinner.'],
              'ar' => ['غرفة مريحة جداً واستقبال ودود.', 'موقع ممتاز مع بعض الضجيج من الشارع.', 'الطعام لذيذ لكن الخدمة كانت بطيئة قليلاً.']],
        3 => ['en' => ['Decent stay, but the room felt dated.', 'Average — check-in took too long.'],
              'ar' => ['إقامة مقبولة لكن الغرفة قديمة بعض الشيء.', 'عادية، تسجيل الدخول استغرق وقتاً طويلاً.']],
        2 => ['en' => ['Air conditioning did not work properly for two nights.', 'Overpriced for what you get.'],
              'ar' => ['التكييف لم يعمل جيداً لليلتين.', 'السعر مرتفع مقارنة بالخدمة.']],
        1 => ['en' => ['Room was not ready until 6pm and nobody apologised.'],
              'ar' => ['الغرفة لم تكن جاهزة حتى السادسة مساءً ولم يعتذر أحد.']],
    ];

    private function reviews(Collection $reservations, RecalculateRatingAction $recalculate): void
    {
        $venues = DiningVenue::where('is_active', true)->get();

        foreach ($reservations->where('status', ReservationStatus::CHECKED_OUT) as $reservation) {
            if (! $this->chance(60)) {
                continue;
            }
            $guest = $reservation->guest;
            $roomType = RoomType::find($reservation->rooms()->value('room_type_id'));
            $at = CarbonImmutable::parse($reservation->checked_out_at)->addDays($this->int(0, 6)); $at = $this->clamp($at);

            $this->review($guest, $roomType, $reservation, $at);
            if ($this->chance(45) && $venues->isNotEmpty()) {
                $this->review($guest, $this->pick($venues), $reservation, $at->addHours(1));
            }
        }

        foreach (RoomType::all()->concat(DiningVenue::all()) as $subject) {
            $recalculate->handle($subject);
        }
    }

    private function review(Guest $guest, Model $subject, Reservation $reservation, CarbonImmutable $at): void
    {
        $exists = Review::where('guest_id', $guest->id)
            ->where('reviewable_type', $subject->getMorphClass())
            ->where('reviewable_id', $subject->getKey())->exists();
        if ($exists) {
            return;
        }

        $rating = $this->weighted([5 => 40, 4 => 30, 3 => 15, 2 => 10, 1 => 5]);
        $locale = $guest->preferred_locale === 'ar' ? 'ar' : 'en';

        $this->persist(new Review([
            'guest_id' => $guest->id,
            'reviewable_type' => $subject->getMorphClass(),
            'reviewable_id' => $subject->getKey(),
            'reservation_id' => $reservation->id,
            'rating' => $rating,
            'comment' => $this->chance(85) ? $this->pick(self::REVIEW_COMMENTS[$rating][$locale]) : null,
            'is_verified_stay' => true,
            // Low scores are held for moderation more often — that is the
            // queue the dashboard's moderation screen works through.
            'is_published' => $rating >= 3 ? $this->chance(92) : $this->chance(40),
        ]), $at);
    }

    private function notifications(Collection $reservations, Collection $inquiries): void
    {
        foreach ($reservations as $reservation) {
            if (in_array($reservation->status, [ReservationStatus::CANCELLED, ReservationStatus::PENDING_VERIFICATION], true)) {
                continue;
            }
            $guest = $reservation->guest;
            $ar = $guest->preferred_locale === 'ar';
            $sent = CarbonImmutable::parse($reservation->created_at);

            $this->notify($guest->id, null, NotificationType::WELCOME,
                $ar ? 'مرحباً بك في كارلتون' : 'Welcome to Carlton',
                $ar ? "تم تأكيد حجزك {$reservation->booking_code}." : "Your booking {$reservation->booking_code} is in. We will keep you posted here.",
                ['reservation_uuid' => $reservation->uuid], $sent, $sent->diffInDays(now()) > 2 || $this->chance(40));

            if ($reservation->checked_in_at) {
                $ready = CarbonImmutable::parse($reservation->checked_in_at)->subMinutes($this->int(5, 90));
                $this->notify($guest->id, null, NotificationType::ROOM_READY,
                    $ar ? 'غرفتك جاهزة' : 'Your room is ready',
                    $ar ? 'تم تجهيز غرفتك، نراك قريباً!' : 'Your room has been prepared. See you soon!',
                    ['reservation_uuid' => $reservation->uuid], $ready, $reservation->status === ReservationStatus::CHECKED_OUT || $this->chance(50));
            }
        }

        foreach ($inquiries as $inquiry) {
            $this->notify(null, $inquiry->department, NotificationType::INQUIRY_ROUTED,
                'New event inquiry', "A new {$inquiry->event_type->value} inquiry from {$inquiry->name} has been routed to your department.",
                ['event_inquiry_uuid' => $inquiry->uuid], CarbonImmutable::parse($inquiry->created_at),
                $inquiry->status !== EventInquiryStatus::NEW);
        }
    }

    private function notify(?int $guestId, ?Department $department, NotificationType $type, string $title, string $body, array $data, CarbonImmutable $at, bool $read): void
    {
        $at = $this->clamp($at);
        $this->persist(new GuestNotification([
            'guest_id' => $guestId, 'department' => $department, 'type' => $type,
            'title' => $title, 'body' => $body, 'data' => $data,
            'sent_at' => $at, 'read_at' => $read ? $this->clamp($at->addMinutes($this->int(2, 600))) : null,
        ]), $at);
    }

    private function deviceTokens(Collection $guests): void
    {
        foreach ($guests as $guest) {
            if (! $this->chance(55) || DeviceToken::where('guest_id', $guest->id)->exists()) {
                continue;
            }
            $platform = $this->weighted([DevicePlatform::ANDROID->value => 50, DevicePlatform::IOS->value => 40, DevicePlatform::WEB->value => 10]);
            $this->persist(new DeviceToken([
                'guest_id' => $guest->id,
                'token' => "demo-fcm-{$platform}-" . bin2hex($this->rng->getBytes(12)),
                'platform' => $platform,
                'last_used_at' => CarbonImmutable::now()->subHours($this->int(1, 24 * 60)),
            ]), CarbonImmutable::parse($guest->created_at));
        }
    }

    // ---------------------------------------------------------------------
    // Recycle bin
    // ---------------------------------------------------------------------

    /**
     * Content binned at different ages inside the retention window, so the
     * bin lists, restores and purges have something to work on.
     */
    private function recycleBin(): void
    {
        $binned = [
            [Faq::create([
                'question' => ['en' => 'Do you offer a shuttle to Beirut?', 'ar' => 'هل توفرون نقلاً إلى بيروت؟'],
                'answer' => ['en' => 'This service has been discontinued.', 'ar' => 'تم إيقاف هذه الخدمة.'],
                'is_active' => true, 'sort_order' => 99,
            ]), 2],
            [Testimonial::create([
                'author_name' => 'Marco B.', 'author_title' => ['en' => 'Milan, Italy', 'ar' => 'ميلانو، إيطاليا'],
                'quote' => ['en' => 'A wonderful base for exploring the Old City.', 'ar' => 'مكان رائع لاستكشاف المدينة القديمة.'],
                'rating' => 5, 'is_active' => true, 'sort_order' => 99,
            ]), 9],
            [$this->binnedPromotion(), 25],
            [$this->binnedJournalPost(), 60],
        ];

        foreach ($binned as [$model, $daysAgo]) {
            $model->delete();
            $model->forceFill(['deleted_at' => now()->subDays($daysAgo)])->saveQuietly();
        }
    }

    private function binnedPromotion(): Promotion
    {
        $promotion = Promotion::create([
            'title' => ['en' => 'Spring Brunch Pass', 'ar' => 'باقة برانش الربيع'],
            'description' => ['en' => 'Unlimited weekend brunch for in-house guests.', 'ar' => 'برانش غير محدود في عطلة نهاية الأسبوع للنزلاء.'],
            'valid_from' => $this->today->subMonths(4)->toDateString(),
            'valid_until' => $this->today->subMonths(2)->toDateString(),
            'is_active' => false, 'sort_order' => 99,
        ]);
        $this->placePhoto($promotion, 'web:dining-brunch.jpg');

        return $promotion;
    }

    private function binnedJournalPost(): JournalPost
    {
        $post = JournalPost::create([
            'slug' => 'rooftop-season-opening',
            'title' => ['en' => 'Rooftop Season Opening', 'ar' => 'افتتاح موسم السطح'],
            'excerpt' => ['en' => 'The rooftop lounge reopens for summer.', 'ar' => 'إعادة افتتاح صالة السطح لفصل الصيف.'],
            'body' => ['en' => 'Superseded by the updated announcement.', 'ar' => 'تم استبداله بالإعلان المحدث.'],
            'published_on' => $this->today->subMonths(5)->toDateString(),
            'is_active' => true, 'sort_order' => 99,
        ]);
        $this->placePhoto($post, 'web:club-lounge-rooftop.jpg');

        return $post;
    }

    // ---------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------

    /**
     * Save with a backdated creation time. Eloquent keeps a created_at /
     * updated_at that is already dirty, so the timeline reads naturally in
     * every "latest first" list.
     *
     * @template T of Model
     * @param T $model
     * @return T
     */
    private function persist(Model $model, $createdAt, $updatedAt = null): Model
    {
        $model->created_at = $createdAt;
        $model->updated_at = $updatedAt ?? $createdAt;
        $model->save();

        return $model;
    }

    /** Never later than the moment of seeding — nothing is recorded in the future. */
    private function clamp($at): CarbonImmutable
    {
        return CarbonImmutable::parse($at)->min(CarbonImmutable::now());
    }

    private function within($from, $to): CarbonImmutable
    {
        $a = CarbonImmutable::parse($from)->getTimestamp();
        $b = max($a, CarbonImmutable::parse($to)->getTimestamp());

        return CarbonImmutable::createFromTimestamp($this->rng->getInt($a, $b), config('app.timezone'));
    }

    private function int(int $min, int $max): int
    {
        return $this->rng->getInt($min, $max);
    }

    private function chance(int $percent): bool
    {
        return $this->rng->getInt(1, 100) <= $percent;
    }

    /** Seeded replacement for Collection::random() / array_rand(). */
    private function pick(array|\Illuminate\Support\Collection $items): mixed
    {
        $items = is_array($items) ? array_values($items) : $items->values()->all();

        return $items[$this->rng->getInt(0, count($items) - 1)];
    }

    /** @param array<int|string, int> $weights value => weight */
    private function weighted(array $weights): int|string
    {
        $roll = $this->rng->getInt(1, array_sum($weights));
        foreach ($weights as $value => $weight) {
            if (($roll -= $weight) <= 0) {
                return $value;
            }
        }

        return array_key_last($weights);
    }
}
