<?php

namespace Database\Seeders;

use App\Enums\BedType;
use App\Enums\RoomView;
use App\Models\DiningVenue;
use App\Models\EventSpace;
use App\Models\Facility;
use App\Models\Faq;
use App\Models\HomeSlider;
use App\Models\Page;
use App\Models\Promotion;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\Testimonial;
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
        $this->homeSliders();
        $this->testimonials();
        $this->faqs();
    }

    /**
     * The eight questions the public website hardcodes in
     * `src/app/i18n/translations.ts` under `faq.items`.
     *
     * Seeded in en/ar/fr because the site already has human translations for all
     * three. `tr` and `es` are left for editors — the locale config makes them
     * optional, and machine-translating hotel policy (cancellation windows,
     * dietary handling) is how a hotel ends up committed to something it did not
     * mean in a language nobody on the team reads.
     *
     * `category` stays null: the site renders one flat list today.
     */
    private function faqs(): void
    {
        $items = [
            [
                'q' => [
                    'en' => 'What are the check-in and check-out times?',
                    'ar' => 'ما أوقات تسجيل الوصول والمغادرة؟',
                    'fr' => "Quels sont les horaires d'arrivée et de départ ?",
                ],
                'a' => [
                    'en' => 'Check-in is from 3:00 pm and check-out is by 12:00 noon. Early arrivals and late departures are accommodated whenever possible — please contact our concierge team in advance and we will do our utmost to arrange it.',
                    'ar' => 'تسجيل الوصول من الساعة 3:00 مساءً وتسجيل المغادرة بحلول الساعة 12:00 ظهراً. يُراعَى الوصول المبكر والمغادرة المتأخرة كلما أمكن ذلك — يُرجى التواصل مع فريق الكونسيرج مسبقاً وسنبذل قصارى جهدنا.',
                    'fr' => "L'arrivée est possible à partir de 15h00 et le départ est à 12h00. Les arrivées anticipées et les départs tardifs sont accommodés dans la mesure du possible — veuillez contacter notre équipe de conciergerie à l'avance.",
                ],
            ],
            [
                'q' => [
                    'en' => 'Is airport transfer available?',
                    'ar' => 'هل يتوفر نقل من المطار؟',
                    'fr' => 'Le transfert aéroport est-il disponible ?',
                ],
                'a' => [
                    'en' => 'Yes. We offer private chauffeur transfers from Damascus International Airport in our house fleet of Mercedes-Benz vehicles. The journey takes approximately 20 minutes. Grand Suite guests receive complimentary transfers; all other guests may book at a preferential rate.',
                    'ar' => 'نعم. نقدم خدمة نقل خاص مع سائق من مطار دمشق الدولي بأسطول مركباتنا من طراز مرسيدس-بنز. تستغرق الرحلة نحو 20 دقيقة. يحصل ضيوف الجناح الكبير على النقل مجاناً؛ ويمكن لسائر الضيوف الحجز بأسعار مفضّلة.',
                    'fr' => "Oui. Nous proposons des transferts privés avec chauffeur depuis l'aéroport international de Damas avec notre flotte de véhicules Mercedes-Benz. Le trajet dure environ 20 minutes. Les clients de la Grande Suite bénéficient de transferts gratuits ; tous les autres clients peuvent réserver à un tarif préférentiel.",
                ],
            ],
            [
                'q' => [
                    'en' => 'What dining options are available?',
                    'ar' => 'ما خيارات تناول الطعام المتاحة؟',
                    'fr' => 'Quelles options de restauration sont disponibles ?',
                ],
                'a' => [
                    'en' => 'Carlton Syria is home to four distinct dining venues: Le Rocher (gastronomic tasting menu, Tues–Sun evenings), Al-Qamar Rooftop Lounge (artisanal beverages and light dining, daily), Private Dining in our jasmine garden courtyard (by reservation), and Al-Sabah Terrace (breakfast and brunch, daily). In-room dining is available around the clock.',
                    'ar' => 'يضم كارلتون سوريا أربعة صالات طعام: لو روشيه (قائمة تذوق غاسترونومية، مساء الثلاثاء حتى الأحد)، وصالة القمر على السطح (مشروبات حرفية وطعام خفيف، يومياً)، وتناول الطعام الخاص في فناء حديقة الياسمين (بالحجز)، وتراس الصباح (إفطار وبرانش، يومياً). خدمة الطعام داخل الغرف متوفرة على مدار الساعة.',
                    'fr' => 'Carlton Syria abrite quatre restaurants distincts : Le Rocher (menu dégustation gastronomique, mar–dim soirs), Al-Qamar Lounge sur la terrasse (boissons artisanales et restauration légère, tous les jours), Dîner Privé dans notre patio au jardin de jasmin (sur réservation), et la Terrasse Al-Sabah (petit-déjeuner et brunch, tous les jours). Le service en chambre est disponible 24h/24.',
                ],
            ],
            [
                'q' => [
                    'en' => 'Do you accommodate dietary requirements and allergies?',
                    'ar' => 'هل تراعون الاحتياجات الغذائية الخاصة والحساسية؟',
                    'fr' => 'Prenez-vous en compte les exigences alimentaires et les allergies ?',
                ],
                'a' => [
                    'en' => 'Absolutely. Please inform us of any dietary requirements, allergies, or preferences at the time of booking. Chef Karim Nassar and his team are experienced in accommodating all needs — including vegan, gluten-free, halal, and bespoke medically-advised diets — without compromise to the dining experience.',
                    'ar' => 'بالتأكيد. يُرجى إخبارنا بأي متطلبات غذائية أو حساسية أو تفضيلات عند الحجز. الشيف كريم نصار وفريقه متمرسون في تلبية جميع الاحتياجات — بما فيها النظام النباتي وخالي الغلوتين والحلال والأنظمة الغذائية الطبية المخصصة.',
                    'fr' => "Absolument. Veuillez nous informer de toute exigence alimentaire, allergie ou préférence au moment de la réservation. Le chef Karim Nassar et son équipe ont l'expérience nécessaire pour répondre à tous les besoins — végétalien, sans gluten, halal et régimes médicaux personnalisés.",
                ],
            ],
            [
                'q' => [
                    'en' => 'What is the cancellation policy?',
                    'ar' => 'ما سياسة الإلغاء؟',
                    'fr' => "Quelle est la politique d'annulation ?",
                ],
                'a' => [
                    'en' => 'Standard reservations may be cancelled or modified up to 48 hours before arrival without charge. Cancellations made within 48 hours will incur a one-night fee. Package reservations and Grand Suite bookings are subject to a 7-day cancellation policy. Full details are provided at the time of booking.',
                    'ar' => 'يمكن إلغاء الحجوزات العادية أو تعديلها حتى 48 ساعة قبل الوصول دون أي رسوم. الإلغاءات خلال 48 ساعة تستوجب رسوم ليلة واحدة. تخضع حجوزات الباقات والجناح الكبير لسياسة إلغاء مدتها 7 أيام.',
                    'fr' => "Les réservations standard peuvent être annulées ou modifiées jusqu'à 48 heures avant l'arrivée sans frais. Les annulations effectuées dans les 48 heures entraînent des frais d'une nuit. Les réservations de packages et de la Grande Suite sont soumises à une politique d'annulation de 7 jours.",
                ],
            ],
            [
                'q' => [
                    'en' => 'Are children welcome at Carlton Syria?',
                    'ar' => 'هل الأطفال مرحّب بهم في كارلتون سوريا؟',
                    'fr' => 'Les enfants sont-ils les bienvenus au Carlton Syria ?',
                ],
                'a' => [
                    'en' => "Children are warmly welcomed. We offer a dedicated children's welcome amenity, a babysitting service (available on request), and child-friendly menus across all dining venues. The pool is open to all ages, with designated shallow sections and a lifeguard on duty.",
                    'ar' => 'الأطفال مرحّب بهم بحفاوة. نقدّم هدية ترحيبية مخصصة للأطفال وخدمة جليسة أطفال (بناءً على الطلب) وقوائم طعام ملائمة للأطفال. المسبح مفتوح لجميع الأعمار مع مقاطع ضحلة مخصصة ومنقذ حياة في الخدمة.',
                    'fr' => "Les enfants sont chaleureusement accueillis. Nous proposons une attention de bienvenue dédiée aux enfants, un service de baby-sitting (sur demande) et des menus adaptés aux enfants. La piscine est ouverte à tous les âges, avec des zones peu profondes désignées et un maître-nageur en service.",
                ],
            ],
            [
                'q' => [
                    'en' => 'Is parking available?',
                    'ar' => 'هل يتوفر موقف للسيارات؟',
                    'fr' => 'Y a-t-il un parking disponible ?',
                ],
                'a' => [
                    'en' => 'Yes. Secure underground valet parking is available to resident and restaurant guests. Our team will collect and return your vehicle. Please advise us upon arrival if you are travelling by car.',
                    'ar' => 'نعم. تتوفر خدمة صف السيارات في موقف أرضي آمن لضيوف المقيمين والمطاعم. يتولى فريقنا استلام سيارتك وإعادتها. يُرجى إخبارنا عند وصولك إذا كنت مسافراً بسيارة خاصة.',
                    'fr' => "Oui. Un service de voiturier en parking souterrain sécurisé est disponible pour les clients résidents et les visiteurs des restaurants. Notre équipe récupère et restitue votre véhicule. Veuillez nous informer à votre arrivée si vous voyagez en voiture.",
                ],
            ],
            [
                'q' => [
                    'en' => 'Can you arrange cultural or private excursions?',
                    'ar' => 'هل يمكنكم تنظيم رحلات ثقافية أو خاصة؟',
                    'fr' => 'Pouvez-vous organiser des excursions culturelles ou privées ?',
                ],
                'a' => [
                    'en' => 'This is one of our great pleasures. Our concierge team has deep connections across Damascus and Syria — from private after-hours access to the Umayyad Mosque to bespoke journeys to Palmyra or Krak des Chevaliers. No request is too specific. Contact us before your arrival and we will begin curating.',
                    'ar' => 'هذا من أعظم متعنا. يملك فريق الكونسيرج شبكة علاقات واسعة في دمشق وسوريا — من دخول خاص بعد ساعات الزيارة إلى الجامع الأموي إلى رحلات مخصصة إلى تدمر أو قلعة الحصن. لا طلب يبدو مبالغاً فيه. تواصل معنا قبل وصولك وسنبدأ التخطيط.',
                    'fr' => "C'est l'un de nos grands plaisirs. Notre équipe de conciergerie dispose de connexions profondes dans toute Damas et la Syrie — de l'accès privé après fermeture à la Grande Mosquée des Omeyyades aux voyages sur mesure à Palmyre ou au Krak des Chevaliers. Contactez-nous avant votre arrivée et nous commencerons à préparer votre itinéraire.",
                ],
            ],
        ];

        foreach ($items as $i => $item) {
            Faq::create([
                'question'   => $item['q'],
                'answer'     => $item['a'],
                'is_active'  => true,
                'sort_order' => $i,
            ]);
        }
    }

    /**
     * The three quotes the public website currently hardcodes in
     * `src/app/i18n/translations.ts` under `testimonials.items`.
     *
     * No avatars are attached: the site renders these as text only, and seeding
     * placeholder portraits would invent faces for named guests.
     *
     * `author_title` folds the site's separate `origin` and `stay` fields into
     * the single line the schema carries. If the site ever needs them apart
     * again, that is a schema addition, not a parsing exercise — do not split
     * this string on the separator.
     */
    private function testimonials(): void
    {
        $quotes = [
            [
                'author' => 'Sarah & James M.',
                'title'  => ['en' => 'London, United Kingdom · Grand Suite', 'ar' => 'لندن، المملكة المتحدة · الجناح الكبير'],
                'quote'  => [
                    'en' => "From the moment we arrived, every detail felt considered. The staff remembered our names, our preferences, and — somehow — what we didn't yet know we needed.",
                    'ar' => 'منذ لحظة وصولنا، كانت كل تفصيلة مدروسة ودقيقة. تذكّر الطاقم أسماءنا وتفضيلاتنا — وبطريقة ما — ما لم نكن قد أدركنا أننا نحتاجه بعد.',
                ],
            ],
            [
                'author' => 'Prof. Henri D.',
                'title'  => ['en' => 'Paris, France · Premier Terrace', 'ar' => 'باريس، فرنسا · تيراس بريمير'],
                'quote'  => [
                    'en' => 'Carlton Syria achieves something rare: it is at once grand and deeply personal. The most beautiful hotel I have stayed in across forty years of travel.',
                    'ar' => 'يحقق كارلتون سوريا شيئاً نادراً: إنه في آنٍ واحد فاخر وشخصي للغاية. أجمل فندق أقمت فيه خلال أربعين عاماً من السفر.',
                ],
            ],
            [
                'author' => 'Valentina R.',
                'title'  => ['en' => 'Milan, Italy · Deluxe Suite', 'ar' => 'ميلانو، إيطاليا · جناح ديلوكس'],
                'quote'  => [
                    'en' => 'We came for a week and extended our stay by five days. The cuisine, the light on the private terrace at dusk, the extraordinary attention of the team — we simply could not bring ourselves to leave.',
                    'ar' => 'أتينا لأسبوع ومدّدنا إقامتنا خمسة أيام. المطبخ والضوء على التراس الخاص عند الغسق والاهتمام الاستثنائي من الفريق — ببساطة لم يكن بوسعنا المغادرة.',
                ],
            ],
        ];

        foreach ($quotes as $i => $q) {
            Testimonial::create([
                'author_name'  => $q['author'],
                'author_title' => $q['title'],
                'quote'        => $q['quote'],
                'rating'       => 5,
                'is_active'    => true,
                'sort_order'   => $i,
            ]);
        }
    }

    private function homeSliders(): void
    {
        $slides = [
            [
                'header' => ['en' => 'Timeless Damascus Hospitality', 'ar' => 'ضيافة دمشقية خالدة'],
                'location' => ['en' => 'Damascus, Syria', 'ar' => 'دمشق، سوريا'],
                'desc' => ['en' => 'A landmark address in the heart of the city, welcoming guests since 1998.',
                           'ar' => 'عنوان مميز في قلب المدينة، يرحب بالضيوف منذ عام 1998.'],
            ],
            [
                'header' => ['en' => 'Rooftop Dining Above the City', 'ar' => 'مطعم على السطح فوق المدينة'],
                'location' => ['en' => 'Carlton Rooftop', 'ar' => 'سطح كارلتون'],
                'desc' => ['en' => 'Syrian and international cuisine served under the Damascus skyline.',
                           'ar' => 'مأكولات سورية وعالمية تُقدَّم تحت سماء دمشق.'],
            ],
        ];

        foreach ($slides as $i => $s) {
            $slider = HomeSlider::create([
                'header_text'      => $s['header'],
                'location'         => $s['location'],
                'description_text' => $s['desc'],
                'is_active'        => true,
                'sort_order'       => $i,
            ]);
            $this->attachPhotos($slider, [$s['header']['en']]);
        }
    }

    private function roomTypesAndRooms(): void
    {
        $types = [
            ['en' => 'Standard Queen', 'ar' => 'غرفة كوين ستاندرد', 'occ' => [2, 2], 'size' => 24, 'price' => 90,
                'desc' => 'A comfortable queen room with city views, perfect for solo travelers and couples.', 'floors' => [1, 2],
                'view' => RoomView::CITY, 'beds' => [BedType::QUEEN], 'cancel' => 24],
            ['en' => 'Deluxe King', 'ar' => 'غرفة ديلوكس كينغ', 'occ' => [2, 3], 'size' => 32, 'price' => 150,
                'desc' => 'Spacious king room with a seating area and premium amenities.', 'floors' => [3, 4],
                'view' => RoomView::CITY, 'beds' => [BedType::KING], 'cancel' => 48],
            ['en' => 'Executive Suite', 'ar' => 'جناح تنفيذي', 'occ' => [2, 4], 'size' => 55, 'price' => 280,
                'desc' => 'A separate living area, executive lounge access, and panoramic views.', 'floors' => [5, 6],
                'view' => RoomView::MOUNTAIN, 'beds' => [BedType::KING, BedType::EXTRA], 'cancel' => 48],
            ['en' => 'Family Room', 'ar' => 'غرفة عائلية', 'occ' => [4, 6], 'size' => 48, 'price' => 220,
                'desc' => 'Two queen beds and extra space, ideal for families.', 'floors' => [8, 9],
                'view' => RoomView::GARDEN, 'beds' => [BedType::QUEEN, BedType::TWIN], 'cancel' => 72],
            ['en' => 'Presidential Suite', 'ar' => 'الجناح الرئاسي', 'occ' => [2, 4], 'size' => 95, 'price' => 550,
                'desc' => 'The hotel\'s finest suite — private terrace, dining room, and butler service.', 'floors' => [10],
                'view' => RoomView::POOL, 'beds' => [BedType::KING, BedType::SINGLE], 'cancel' => 96],
        ];

        foreach ($types as $i => $t) {
            $roomType = RoomType::create([
                'name' => ['en' => $t['en'], 'ar' => $t['ar']],
                'description' => ['en' => $t['desc'], 'ar' => $t['desc']],
                'amenities' => ['WiFi', 'Air Conditioning', 'Mini Bar', 'Flat-screen TV', 'Safe'],
                'view_type' => $t['view'],
                'bed_types' => array_map(fn (BedType $b) => $b->value, $t['beds']),
                'base_occupancy' => $t['occ'][0],
                'max_occupancy' => $t['occ'][1],
                'size_sqm' => $t['size'],
                'base_price_usd' => $t['price'],
                'cancellation_hours' => $t['cancel'],
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
            // Deliberately not "Al Sham" — MobileDemoSeeder seeds the app's own
            // "Al-Sham Restaurant", and two near-identical names in one list
            // make the seeded API confusing to read.
            ['en' => 'Barada Brasserie', 'ar' => 'مطعم بردى', 'cuisine' => 'Levantine', 'hours' => '7:00 AM – 11:00 PM'],
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
            ['en' => 'Early Bird Booking', 'ar' => 'حجز مبكر', 'desc' => 'Book 30 days ahead and save 15% on any room type.',
                'desc2' => 'Applies to every room type, all year round.', 'desc2_ar' => 'ينطبق على جميع أنواع الغرف طوال العام.'],
            ['en' => 'Honeymoon Package', 'ar' => 'باقة شهر العسل', 'desc' => 'Complimentary suite upgrade and a bottle of wine for newlyweds.',
                'desc2' => 'Includes late checkout and breakfast in bed.', 'desc2_ar' => 'يشمل تسجيل مغادرة متأخر وفطور في الغرفة.'],
            ['en' => 'Long Stay Discount', 'ar' => 'خصم الإقامة الطويلة', 'desc' => 'Stay 7 nights or more and save 20%.',
                'desc2' => 'The discount applies automatically at checkout.', 'desc2_ar' => 'يُطبَّق الخصم تلقائياً عند المغادرة.'],
        ];

        foreach ($promos as $i => $p) {
            $promo = Promotion::create([
                'title' => ['en' => $p['en'], 'ar' => $p['ar']],
                'description' => ['en' => $p['desc'], 'ar' => $p['desc']],
                'secondary_description' => ['en' => $p['desc2'], 'ar' => $p['desc2_ar']],
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
