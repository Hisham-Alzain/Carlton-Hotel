<?php

namespace Database\Seeders;

use App\Enums\BedType;
use App\Enums\RoomView;
use App\Models\DiningVenue;
use App\Models\EventSpace;
use App\Models\Experience;
use App\Models\Facility;
use App\Models\Faq;
use App\Models\GalleryCategory;
use App\Models\GalleryItem;
use App\Enums\SettingType;
use App\Models\HomeSlider;
use App\Models\JournalPost;
use App\Models\Page;
use App\Models\Promotion;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\SiteSetting;
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
        $this->experiences();
        $this->gallery();
        $this->journalPosts();
        $this->siteSettings();
    }

    /**
     * The website's photo gallery: four chips and the nineteen photographs it
     * currently ships. Structural data (order, category) comes from
     * `src/app/content/fallback.ts` under `galleryImages`, chip labels and
     * captions from `src/app/i18n/translations.ts` under `galleryPage`.
     *
     * en/ar/fr are seeded — the site has human translations for all three.
     * tr/es are left to editors.
     *
     * CAPTION PAIRING — read this before "fixing" the order. The site pairs
     * `galleryImages[i]` with `galleryPage.captions[i]`, but `captions` still has
     * 24 entries while `galleryImages` was trimmed to 19: the five pool/hammam
     * photographs that used to sit at indices 13–17 were removed and their
     * captions were not. On the live site every Lobby and Damascus tile is
     * therefore captioned five places off — the "Lobby" tiles read "Grand indoor
     * pool…". This seeder pairs each photograph with the caption that actually
     * describes it (rooms 0–6, dining 7–12, lobby 18–20, damascus 21–23) and
     * drops the five orphans, because seeding a database from a display bug would
     * make the bug permanent and much harder to see.
     */
    private function gallery(): void
    {
        $chips = [
            ['slug' => 'rooms',    'name' => ['en' => 'Rooms',    'ar' => 'الغرف',   'fr' => 'Chambres']],
            ['slug' => 'dining',   'name' => ['en' => 'Dining',   'ar' => 'المطاعم', 'fr' => 'Restauration']],
            ['slug' => 'lobby',    'name' => ['en' => 'Lobby',    'ar' => 'الردهة',  'fr' => 'Hall']],
            ['slug' => 'damascus', 'name' => ['en' => 'Damascus', 'ar' => 'دمشق',    'fr' => 'Damas']],
        ];

        $captions = [
            'rooms' => [
                [
                    'en' => 'Grand Suite panoramic living room with city views',
                    'ar' => 'صالة معيشة بانورامية في الجناح الكبير بإطلالة على المدينة',
                    'fr' => 'Salon panoramique de la Suite Grand avec vue sur la ville',
                ],
                [
                    'en' => 'Luxury suite living area with modern chandelier',
                    'ar' => 'منطقة معيشة في جناح فاخر بثريا عصرية',
                    'fr' => "Espace de vie d'une suite de luxe avec lustre moderne",
                ],
                [
                    'en' => 'Deluxe king bedroom with floor-to-ceiling windows',
                    'ar' => 'غرفة نوم ديلوكس بسرير كينغ ونوافذ ممتدة من الأرض حتى السقف',
                    'fr' => 'Chambre Deluxe avec lit king et baies vitrées du sol au plafond',
                ],
                [
                    'en' => 'Classic twin bedroom with city panorama',
                    'ar' => 'غرفة نوم كلاسيكية بسريرين وإطلالة بانورامية على المدينة',
                    'fr' => 'Chambre classique à lits jumeaux avec panorama sur la ville',
                ],
                [
                    'en' => 'Premier suite sitting room with full-width glazing',
                    'ar' => 'صالة جلوس في الجناح الفاخر بواجهة زجاجية كاملة',
                    'fr' => 'Salon de la suite Premier avec vitrage pleine largeur',
                ],
                [
                    'en' => 'Freestanding marble bath in Premier suite bathroom',
                    'ar' => 'حوض استحمام رخامي قائم بذاته في حمّام الجناح الفاخر',
                    'fr' => 'Bain en marbre autoportant dans la salle de bain de la suite Premier',
                ],
                [
                    'en' => 'Marble double vanity in Classic Courtyard bathroom',
                    'ar' => 'مغسلة رخامية مزدوجة في حمّام الفناء الكلاسيكي',
                    'fr' => 'Double vasque en marbre dans la salle de bain Classique Cour',
                ],
            ],
            'dining' => [
                [
                    'en' => 'The Carlton Club lounge with panoramic city view',
                    'ar' => 'صالة كارلتون كلوب بإطلالة بانورامية على المدينة',
                    'fr' => 'Le salon Carlton Club avec vue panoramique sur la ville',
                ],
                [
                    'en' => 'The Carlton dining room with carved mashrabiyya screens',
                    'ar' => 'قاعة طعام كارلتون بمشربيات منحوتة يدويًا',
                    'fr' => 'La salle à manger Carlton avec moucharabiehs sculptés',
                ],
                [
                    'en' => 'Rooftop lounge with glass ceiling and Damascus skyline',
                    'ar' => 'صالة على السطح بسقف زجاجي وإطلالة على أفق دمشق',
                    'fr' => 'Salon sur le toit avec plafond vitré et silhouette de Damas',
                ],
                [
                    'en' => 'Tomahawk from The Forge — our signature grill',
                    'ar' => 'توماهوك من ذا فورج — مشوانا المميز',
                    'fr' => 'Tomahawk de The Forge — notre grillade signature',
                ],
                [
                    'en' => 'Freshly crafted brunch spread by Chef Karim Nassar',
                    'ar' => 'مائدة فطور متجددة معدّة بإشراف الشيف كريم نصّار',
                    'fr' => 'Buffet de brunch fraîchement préparé sous la supervision du Chef Karim Nassar',
                ],
                [
                    'en' => 'Artisan Syrian cuisine plated with precision',
                    'ar' => 'أطباق سورية حرفية مُقدَّمة بدقة',
                    'fr' => 'Cuisine syrienne artisanale dressée avec précision',
                ],
            ],
            'lobby' => [
                [
                    'en' => 'Grand hotel lobby with ornate chandelier',
                    'ar' => 'ردهة فندق فخمة بثريا مزخرفة',
                    'fr' => "Hall d'hôtel grandiose au lustre orné",
                ],
                [
                    'en' => 'Damascene courtyard fountain surrounded by stone arches',
                    'ar' => 'نافورة فناء دمشقي محاطة بأقواس حجرية',
                    'fr' => 'Fontaine de cour damascène entourée d\'arches de pierre',
                ],
                [
                    'en' => 'Carlton Syria grand lobby with Damascus skylight',
                    'ar' => 'ردهة كارلتون سوريا الكبرى بفتحة سقفية على طراز دمشق',
                    'fr' => 'Hall principal de Carlton Syria sous une verrière à la damascène',
                ],
            ],
            'damascus' => [
                [
                    'en' => 'Ancient architecture of Damascus at golden hour',
                    'ar' => 'عمارة دمشق العريقة عند ساعة الذهب',
                    'fr' => "Architecture ancienne de Damas à l'heure dorée",
                ],
                [
                    'en' => 'Al-Hamidiyah Souq — the great covered market',
                    'ar' => 'سوق الحميدية — السوق المسقوف الكبير',
                    'fr' => 'Souk Al-Hamidiyah — le grand marché couvert',
                ],
                [
                    'en' => 'Historic Damascus minaret at dusk',
                    'ar' => 'مئذنة دمشق التاريخية عند الغسق',
                    'fr' => 'Minaret historique de Damas au crépuscule',
                ],
            ],
        ];

        foreach ($chips as $chipIndex => $chip) {
            $category = GalleryCategory::create([
                'slug'       => $chip['slug'],
                'name'       => $chip['name'],
                'is_active'  => true,
                'sort_order' => $chipIndex,
            ]);

            foreach ($captions[$chip['slug']] as $i => $caption) {
                $item = GalleryItem::create([
                    'gallery_category_id' => $category->id,
                    'caption'             => $caption,
                    'is_active'           => true,
                    'sort_order'          => $i,
                ]);

                // The site's own photographs are frontend assets (bundled files
                // and Unsplash URLs), so there is nothing here to copy — one
                // labelled placeholder gives the API a servable image URL.
                $this->attachPhoto($item, $caption['en']);
            }
        }
    }

    /**
     * Global website copy that belongs to no single content entity, lifted from
     * `src/app/i18n/translations.ts` (the `nav`, `hero`, `footer`, `support` and
     * `location` blocks) and from the contact details the site hardcodes in its
     * components — `+963 (0)11 000 00 00` and `reservations@carltonsyria.com`,
     * which appear as `tel:` / `mailto:` hrefs in Navigation, Footer,
     * LocationPage, SupportPage and ConciergeChat.
     *
     * en/ar/fr where the site has human copy for all three; `tr`/`es` are left
     * to editors, as everywhere else in this seeder.
     *
     * Scalar vs translated is a per-key decision, and the reason `value` is json:
     * a phone number and an email address are stored as bare JSON strings
     * because they are not translated, while `footer.tagline` is a locale map.
     * One table, both shapes.
     *
     * `type` is the CMS widget hint (App\Enums\SettingType), not a storage
     * format — `address_lines` is `json` because the editor gets a list editor,
     * while `address` is `text` even though both live in the same column.
     */
    private function siteSettings(): void
    {
        $settings = [
            // ── contact ───────────────────────────────────────────────────
            // Display form, spaces and parentheses included. The `tel:` href is
            // the client's business: it strips the punctuation the humans need.
            ['contact', 'phone', SettingType::TEXT, '+963 (0)11 000 00 00'],
            ['contact', 'email', SettingType::TEXT, 'reservations@carltonsyria.com'],
            ['contact', 'address', SettingType::TEXT, [
                'en' => 'Kafr Sousa, Damascus, Syrian Arab Republic',
                'ar' => 'كفر سوسة، دمشق، الجمهورية العربية السورية',
                'fr' => 'Kafr Sousa, Damas, République Arabe Syrienne',
            ]],
            // The site prints the address as three stacked lines
            // (`location.addressLine1..3`). Stored as a list per locale rather
            // than one string with separators, so nothing has to split on a
            // comma to lay it out.
            ['contact', 'address_lines', SettingType::JSON, [
                'en' => ['Kafr Sousa', 'Damascus', 'Syrian Arab Republic'],
                'ar' => ['كفر سوسة', 'دمشق', 'الجمهورية العربية السورية'],
                'fr' => ['Kafr Sousa', 'Damas', 'République Arabe Syrienne'],
            ]],
            ['contact', 'hours_note', SettingType::TEXT, [
                'en' => 'Available 24 hours a day',
                'ar' => 'متاح على مدار الساعة',
                'fr' => 'Disponible 24h/24',
            ]],

            // ── social ────────────────────────────────────────────────────
            // Seeded INACTIVE, and that is the honest state: `Footer.tsx` lists
            // these four platforms with `href: "#"` — the hotel has no published
            // handles yet. Seeding `#` or a guessed vanity URL would put a dead
            // link in the footer of a live site. Inactive keeps the rows out of
            // the public map (so the footer renders no icons) while giving an
            // editor the four slots to fill, in the site's own order.
            ['social', 'instagram', SettingType::URL, null, false],
            ['social', 'facebook', SettingType::URL, null, false],
            ['social', 'x', SettingType::URL, null, false],
            ['social', 'youtube', SettingType::URL, null, false],

            // ── footer ────────────────────────────────────────────────────
            ['footer', 'tagline', SettingType::TEXT, [
                'en' => 'A sanctuary of refined hospitality in the heart of Damascus. Established 2026.',
                'ar' => 'ملاذ للضيافة الراقية في قلب دمشق. تأسّس عام 2026.',
                'fr' => "Un sanctuaire d'hospitalité raffinée au cœur de Damas. Établi en 2026.",
            ]],
            ['footer', 'copyright', SettingType::TEXT, [
                'en' => '© 2026 Carlton Syria. All rights reserved.',
                'ar' => '© 2026 كارلتون سوريا. جميع الحقوق محفوظة.',
                'fr' => '© 2026 Carlton Syria. Tous droits réservés.',
            ]],
            ['footer', 'newsletter_heading', SettingType::TEXT, [
                'en' => 'Private Offers',
                'ar' => 'العروض الحصرية',
                'fr' => 'Offres privées',
            ]],

            // ── booking ───────────────────────────────────────────────────
            ['booking', 'cta_label', SettingType::TEXT, [
                'en' => 'Book Now',
                'ar' => 'احجز الآن',
                'fr' => 'Réserver',
            ]],
            ['booking', 'availability_note', SettingType::TEXT, [
                'en' => 'Reservations open 24 hours',
                'ar' => 'الحجوزات متاحة على مدار الساعة',
                'fr' => 'Réservations ouvertes 24h/24',
            ]],

            // ── seo ───────────────────────────────────────────────────────
            // `index.html` still carries the template's placeholder title, so
            // these come from the hotel's own copy instead: the name as the
            // footer prints it, and the footer tagline as the description.
            ['seo', 'site_title', SettingType::TEXT, [
                'en' => 'Carlton Syria',
                'ar' => 'كارلتون سوريا',
                'fr' => 'Carlton Syria',
            ]],
            ['seo', 'meta_description', SettingType::TEXT, [
                'en' => 'A sanctuary of refined hospitality in the heart of Damascus. Established 2026.',
                'ar' => 'ملاذ للضيافة الراقية في قلب دمشق. تأسّس عام 2026.',
                'fr' => "Un sanctuaire d'hospitalité raffinée au cœur de Damas. Établi en 2026.",
            ]],

            // ── hero ──────────────────────────────────────────────────────
            ['hero', 'eyebrow', SettingType::TEXT, [
                'en' => 'The Luxury Hotel',
                'ar' => 'الفندق الفاخر',
                'fr' => "L'Hôtel de Luxe",
            ]],
            // The site splits its headline across two lines (`heading1` /
            // `heading2`) for typography. Stored as the one sentence it is —
            // where a line breaks is the front end's decision, not content.
            ['hero', 'heading', SettingType::TEXT, [
                'en' => 'Stay with Comfort and Style.',
                'ar' => 'إقامة في راحة وأناقة.',
                'fr' => 'Séjournez avec confort et style.',
            ]],
            ['hero', 'subheading', SettingType::TEXT, [
                'en' => 'Enjoy thoughtfully designed rooms and suites offering ultimate comfort, stunning city views, and premium amenities.',
                'ar' => 'استمتع بغرف وأجنحة مصممة بعناية توفر راحة فائقة وإطلالات أخّاذة على المدينة ومرافق راقية.',
                'fr' => 'Profitez de chambres et suites pensées dans les moindres détails, offrant un confort absolu, une vue imprenable sur la ville et des prestations haut de gamme.',
            ]],
            ['hero', 'cta_label', SettingType::TEXT, [
                'en' => 'Reserve Your Stay',
                'ar' => 'احجز إقامتك',
                'fr' => 'Réserver votre séjour',
            ]],
        ];

        foreach ($settings as $setting) {
            [$group, $key, $type, $value] = $setting;

            SiteSetting::create([
                'group'     => $group,
                'key'       => $key,
                'value'     => $value,
                'type'      => $type,
                'is_active' => $setting[4] ?? true,
            ]);
        }
    }

    /**
     * Journal articles.
     *
     * The three articles the public website shows today, from
     * `translations.ts` under `news` — `news.heading1/heading2` render as
     * "Latest News and Updates" with a "Read More" link, which is this module.
     *
     * Searching for "Journal" is what misleads here: the only `Journal` strings
     * in that file are `gallery.tag` / `galleryPage.heroTag` — "Visual Journal"
     * — and those name the photo wall. The journal content is under `news`.
     *
     * Titles, categories and dates are the site's real copy in **en/ar/fr**, so
     * swapping the site onto this endpoint shows the same three headlines it
     * shows now rather than three different articles.
     *
     * `excerpt` and `body` are PLACEHOLDER. The site renders only a category, a
     * date and a headline behind "Read More" — it has no article text at all, so
     * there is nothing to lift. These are written from each headline, kept short,
     * and are meant to be replaced by the hotel's editorial team. They are not
     * machine translations: each locale was written, not converted.
     *
     * The third post is DATED IN THE FUTURE on purpose. It must appear on the
     * public endpoint — `published_on` is a display date, not a schedule — and
     * seeding one makes that visible to anyone who runs `migrate --seed` and
     * looks at `/api/public/journal`, not only to the test suite.
     */
    private function journalPosts(): void
    {
        $posts = [
            [
                'slug'      => 'new-lighting-design-refreshes-the-lobby',
                'published' => '2026-05-12',
                'category'  => ['en' => 'Interior Design', 'ar' => 'التصميم الداخلي', 'fr' => "Design d'intérieur"],
                'title'     => [
                    'en' => 'New Lighting Design Refreshes The Lobby',
                    'ar' => 'تصميم إضاءة جديد يجدد أجواء الردهة',
                    'fr' => 'Un nouvel éclairage redonne vie au hall',
                ],
                'excerpt'   => [
                    'en' => 'A new scheme for the lobby, built around the way the room is used at four different hours of the day.',
                    'ar' => 'مخطط إضاءة جديد للردهة، مبني على طريقة استخدام المكان في أربع ساعات مختلفة من اليوم.',
                    'fr' => "Un nouveau schéma pour le hall, conçu autour des quatre moments de la journée où l'on y passe.",
                ],
                'body'      => [
                    'en' => "The lobby now carries four lighting states rather than one, each set to how the room is actually occupied: arrivals in the morning, work and meetings through the afternoon, aperitifs at dusk, and a low late setting for guests coming in after midnight.\n\nThe fittings themselves are largely the originals, rewired and re-aimed. The change is in the control, not the hardware.",
                    'ar' => "تحمل الردهة الآن أربع حالات إضاءة بدلاً من واحدة، كل منها مضبوطة على الاستخدام الفعلي للمكان: الوصول صباحاً، والعمل والاجتماعات بعد الظهر، والمشروبات عند الغروب، وإضاءة خفيفة متأخرة للضيوف القادمين بعد منتصف الليل.\n\nأما وحدات الإضاءة نفسها فهي في معظمها الأصلية، أُعيد توصيلها وتوجيهها. التغيير في التحكم، لا في العتاد.",
                    'fr' => "Le hall dispose désormais de quatre ambiances lumineuses au lieu d'une, chacune réglée sur l'usage réel de la pièce : les arrivées le matin, le travail et les rendez-vous l'après-midi, l'apéritif au crépuscule, et une lumière basse pour les clients qui rentrent après minuit.\n\nLes luminaires eux-mêmes sont pour l'essentiel les originaux, recâblés et réorientés. Le changement porte sur la commande, pas sur le matériel.",
                ],
            ],
            [
                'slug'      => 'restoring-carltons-heritage-facade',
                'published' => '2026-04-28',
                'category'  => ['en' => 'Renovation', 'ar' => 'التجديد', 'fr' => 'Rénovation'],
                'title'     => [
                    'en' => "Restoring Carlton's Heritage Façade",
                    'ar' => 'ترميم الواجهة التراثية لكارلتون',
                    'fr' => 'Restauration de la façade historique du Carlton',
                ],
                'excerpt'   => [
                    'en' => 'Stone-by-stone conservation work on the street elevation, carried out without closing the hotel.',
                    'ar' => 'أعمال ترميم حجراً بحجر للواجهة المطلة على الشارع، أُنجزت دون إغلاق الفندق.',
                    'fr' => "Une conservation pierre par pierre de la façade sur rue, menée sans fermer l'hôtel.",
                ],
                'body'      => [
                    'en' => "The street elevation has been cleaned, repointed and in places re-cut, using stone from the same quarry that supplied the original build. Conservation rules ruled out replacement panels, so damaged blocks were repaired in situ.\n\nThe work ran in six-metre sections behind screens so that the hotel never closed and no guest room lost its window for more than a week.",
                    'ar' => "نُظّفت الواجهة المطلة على الشارع وأُعيد رصف مفاصلها ونُحتت في بعض المواضع من جديد، باستخدام حجر من المقلع ذاته الذي زوّد البناء الأصلي. منعت قواعد الحفاظ استخدام ألواح بديلة، فأُصلحت الكتل المتضررة في موضعها.\n\nجرى العمل على مقاطع بطول ستة أمتار خلف حواجز، فلم يُغلق الفندق ولم تفقد أي غرفة نافذتها أكثر من أسبوع.",
                    'fr' => "La façade sur rue a été nettoyée, rejointoyée et par endroits retaillée, avec de la pierre issue de la carrière qui avait fourni la construction d'origine. Les règles de conservation excluaient des panneaux de remplacement : les blocs abîmés ont donc été réparés sur place.\n\nLe chantier a progressé par sections de six mètres derrière des écrans, si bien que l'hôtel n'a jamais fermé et qu'aucune chambre n'a perdu sa fenêtre plus d'une semaine.",
                ],
            ],
            [
                'slug'      => 'a-new-look-for-our-garden-lounge',
                'published' => '2026-04-15',
                'category'  => ['en' => 'Hospitality', 'ar' => 'الضيافة', 'fr' => 'Hôtellerie'],
                'title'     => [
                    'en' => 'A New Look for Our Garden Lounge',
                    'ar' => 'إطلالة جديدة لصالة الحديقة',
                    'fr' => 'Un nouveau visage pour notre salon-jardin',
                ],
                'excerpt'   => [
                    'en' => 'Reseated, replanted, and reopened onto the courtyard it had been turned away from.',
                    'ar' => 'أُعيد ترتيب مقاعدها وزراعتها وفتحها على الفناء الذي كانت تُعطيه ظهرها.',
                    'fr' => "Réaménagé, replanté, et enfin ouvert sur la cour à laquelle il tournait le dos.",
                ],
                'body'      => [
                    'en' => "The garden lounge has been reseated around the courtyard rather than the bar, which is where guests were sitting anyway. Fewer, larger tables replaced the old arrangement, and the planting was rebuilt so the jasmine reads from inside the room as well as from the terrace.\n\nIt reopened in April and now takes breakfast as well as afternoon service.",
                    'ar' => "أُعيد ترتيب مقاعد صالة الحديقة حول الفناء بدلاً من البار، وهو المكان الذي كان الضيوف يجلسون فيه على أي حال. حلّت طاولات أقل عدداً وأكبر حجماً محل الترتيب القديم، وأُعيد بناء المزروعات ليُقرأ الياسمين من داخل الصالة كما يُقرأ من التراس.\n\nأُعيد فتحها في نيسان، وهي تستقبل الآن الفطور إلى جانب خدمة العصر.",
                    'fr' => "Le salon-jardin a été réagencé autour de la cour plutôt que du bar — là où les clients s'installaient de toute façon. Des tables moins nombreuses et plus grandes ont remplacé l'ancienne disposition, et les plantations ont été refaites pour que le jasmin se lise de l'intérieur autant que de la terrasse.\n\nRéouvert en avril, il assure désormais le petit-déjeuner en plus du service de l'après-midi.",
                ],
            ],
            [
                // Dated ahead of today on purpose — see this method's docblock.
                // Not a site article: a fixture that makes the display-date
                // decision visible to anyone who runs `migrate --seed`.
                'slug'      => 'the-carlton-standard-a-note-on-arrivals',
                'published' => null, // resolved below, relative to seed time
                'category'  => ['en' => 'The Hotel', 'ar' => 'الفندق', 'fr' => "L'hôtel"],
                'title'     => [
                    'en' => 'The Carlton Standard: A Note on Arrivals',
                    'ar' => 'معيار كارلتون: ملاحظة عن الوصول',
                    'fr' => "Le standard Carlton : note sur les arrivées",
                ],
                'excerpt'   => [
                    'en' => 'What happens between the airport kerb and your room key, and why we count it in minutes.',
                    'ar' => 'ما يحدث بين رصيف المطار ومفتاح غرفتك، ولماذا نحسبه بالدقائق.',
                    'fr' => "Ce qui se passe entre le trottoir de l'aéroport et la clé de votre chambre, et pourquoi nous le comptons en minutes.",
                ],
                'body'      => [
                    'en' => "A stay begins before the door. From the moment a chauffeur closes the boot at Damascus International, the arrival is a sequence we time: twenty minutes on the road, and — if the reservation reached us with a flight number — a room already opened, cooled and lit before the car turns in.\n\nThis piece is dated ahead of publication on purpose: it is the editorial note that accompanies the new arrivals procedure, and it carries the date the procedure takes effect rather than the date it was written.",
                    'ar' => "تبدأ الإقامة قبل الباب. من اللحظة التي يُغلق فيها السائق صندوق السيارة في مطار دمشق الدولي، يصبح الوصول تسلسلاً نقيس زمنه: عشرون دقيقة على الطريق، و — إن وصلنا الحجز مصحوباً برقم الرحلة — غرفة مفتوحة ومُبرَّدة ومُضاءة قبل أن تدخل السيارة.\n\nهذا المقال مؤرَّخ بتاريخ لاحق للنشر عن قصد: فهو الملاحظة التحريرية المرافقة لإجراء الوصول الجديد، ويحمل تاريخ نفاذ الإجراء لا تاريخ كتابته.",
                    'fr' => "Un séjour commence avant la porte. Dès que le chauffeur referme le coffre à Damas International, l'arrivée devient une séquence que nous chronométrons : vingt minutes de route et — si la réservation nous est parvenue avec un numéro de vol — une chambre déjà ouverte, rafraîchie et éclairée avant que la voiture ne se présente.\n\nCet article est daté après sa publication à dessein : c'est la note éditoriale qui accompagne la nouvelle procédure d'arrivée, et il porte la date d'entrée en vigueur de celle-ci, non celle de sa rédaction.",
                ],
            ],
        ];

        foreach ($posts as $i => $post) {
            $journalPost = JournalPost::create([
                'slug'         => $post['slug'],
                'title'        => $post['title'],
                'excerpt'      => $post['excerpt'],
                'body'         => $post['body'],
                'category'     => $post['category'],
                'published_on' => $post['published'] ?? now()->addMonth()->toDateString(),
                'is_active'    => true,
                'sort_order'   => $i,
            ]);

            $this->attachPhotos($journalPost, [$post['title']['en']]);
        }

        // One draft — proves the public endpoints hide it, in a seeded database
        // as well as in the tests.
        JournalPost::factory()->inactive()->create([
            'slug'         => 'unpublished-draft-a-note-on-suites',
            'title'        => ['en' => 'A Note on Suites (draft)', 'ar' => 'ملاحظة عن الأجنحة (مسودة)'],
            'published_on' => '2026-02-01',
            'sort_order'   => 99,
        ]);
    }

    /**
     * The twelve concierge experiences the public website hardcodes — structural
     * data (id, order) in `src/app/content/fallback.ts` under `experienceMeta`,
     * copy in `src/app/i18n/translations.ts` under `experiencesPage.items`.
     *
     * en/ar/fr are seeded because the site already has human translations for all
     * three. tr/es are left to editors: these paragraphs are the hotel's own
     * marketing voice, and a machine rendering of "the mountain that has presided
     * over Damascus for four thousand years" is not a translation, it is a guess.
     *
     * `category` stores the stable lowercase key, not the site's per-locale chip
     * label ("Gastronomy" / "فنون الطهي" / "Gastronomie") — the column is indexed
     * and filtered, so it cannot depend on a locale.
     *
     * `duration_minutes` carries the UPPER bound of the site's printed range,
     * because the block of time a concierge reserves has to be the longest the
     * experience can run. "Half day" is read as four hours.
     *
     * `price_usd` stays null everywhere: the site publishes no prices for these
     * and its call to action is "Enquire". A seeded number would be a quote the
     * hotel never gave.
     */
    private function experiences(): void
    {
        $items = [
            [
                'slug' => 'spice-journey', 'category' => 'gastronomy', 'minutes' => 180,
                'title' => [
                    'en' => 'Private Spice & Herb Journey',
                    'ar' => 'رحلة خاصة في عالم التوابل والأعشاب',
                    'fr' => 'Voyage privé des épices et herbes',
                ],
                'description' => [
                    'en' => "An exclusive session with our head chef exploring the aromatic foundations of the Levantine kitchen. Taste, blend, and compose using saffron, sumac, oud, and dried roses — then sit for a private tasting dinner built around what you have discovered. Each guest receives a bespoke spice collection to take home.",
                    'ar' => 'جلسة حصرية مع رئيس الطهاة لاستكشاف الأسس العطرية للمطبخ الشامي. تذوّق وامزج وكوّن باستخدام الزعفران والسماق والعود والورد المجفف — ثم اجلس لعشاء تذوّقي خاص يُبنى على ما اكتشفته. يحصل كل ضيف على مجموعة توابل مخصصة لاصطحابها معه.',
                    'fr' => "Une session exclusive avec notre chef explorant les fondements aromatiques de la cuisine levantine. Goûtez, composez et assemblez safran, sumac, oud et roses séchées — puis installez-vous pour un dîner dégustation privé construit autour de vos découvertes. Chaque hôte repart avec une collection d'épices sur mesure.",
                ],
            ],
            [
                'slug' => 'chefs-table', 'category' => 'gastronomy', 'minutes' => 240,
                'title' => [
                    'en' => "Chef's Table by Candlelight",
                    'ar' => 'مائدة الشيف على ضوء الشموع',
                    'fr' => 'Table du Chef aux chandelles',
                ],
                'description' => [
                    'en' => "A private table set beside the open kitchen of Le Rocher. Chef Nassar designs a bespoke multi-course menu in real time — responding to your preferences, the season's finest produce, and the mood of the evening. The most intimate dining in Damascus.",
                    'ar' => 'طاولة خاصة بجانب المطبخ المفتوح في لو روشيه. يصمم الشيف نصّار قائمة متعددة الأطباق في الوقت الحقيقي — استجابةً لتفضيلاتك، وأجود محاصيل الموسم، وأجواء الأمسية. أكثر تجربة طعامٍ حميمية في دمشق.',
                    'fr' => "Une table privée installée à côté de la cuisine ouverte du Rocher. Le Chef Nassar conçoit en temps réel un menu sur mesure à plusieurs services — en fonction de vos préférences, des plus beaux produits de saison et de l'ambiance du soir. Le dîner le plus intime de Damas.",
                ],
            ],
            [
                'slug' => 'bab-sharqi', 'category' => 'culture', 'minutes' => 240,
                'title' => [
                    'en' => 'Bab Sharqi — Gate of the East',
                    'ar' => 'باب شرقي — بوابة الشرق',
                    'fr' => "Bab Sharqi — La Porte de l'Orient",
                ],
                'description' => [
                    'en' => "One of the oldest inhabited streets in the world: the Via Recta of Roman Damascus, which cuts through Bab Sharqi — the Gate of the East — deep into the heart of the old city. Your private guide walks you past ancient stone facades and workshops unchanged for centuries, through one of the most storied quarters of Damascus. The journey ends with lunch in a restored Damascene house.",
                    'ar' => 'واحد من أقدم الشوارع المأهولة في العالم: الطريق المستقيم في دمشق الرومانية، يخترق باب شرقي — بوابة الشرق — في عمق قلب المدينة القديمة. مرشدك الخاص يأخذك عبر الواجهات الحجرية العريقة وورش العمل التي لم تتغير منذ قرون، عبر أحد أعرق أحياء دمشق التاريخية. تنتهي الجولة بغداء في منزل دمشقي أصيل.',
                    'fr' => "L'une des rues les plus anciennes du monde : la Via Recta de la Damas romaine, qui traverse Bab Sharqi — la Porte de l'Orient — jusqu'au cœur de la vieille ville. Votre guide privé vous emmène à travers des façades en pierre millénaires et des ateliers inchangés depuis des siècles, au fil de l'un des quartiers les plus historiques de Damas. La visite se conclut par un déjeuner dans une maison damascène restaurée.",
                ],
            ],
            [
                'slug' => 'personal-shopping', 'category' => 'privilege', 'minutes' => 240,
                'title' => [
                    'en' => 'Personal Shopping Concierge',
                    'ar' => 'كونسيرج التسوّق الشخصي',
                    'fr' => 'Concierge shopping personnel',
                ],
                'description' => [
                    'en' => "Our style concierge curates a private half-day of appointment-only access to Damascus's most distinguished boutiques, ateliers, and jewellers — with refreshments arranged at each destination and purchases delivered seamlessly to your suite.",
                    'ar' => 'ينسّق خبير الأناقة لدينا نصف يومٍ خاص لزيارة أرقى المتاجر والمحترفات والمجوهرات في دمشق بمواعيد حصرية — مع تجهيز المرطبات في كل وجهة وتوصيل المشتريات بسلاسة إلى جناحك.',
                    'fr' => "Notre concierge styliste organise une demi-journée privée d'accès sur rendez-vous aux boutiques, ateliers et joailliers les plus distingués de Damas — avec des rafraîchissements prévus à chaque étape et vos achats livrés directement dans votre suite.",
                ],
            ],
            [
                'slug' => 'in-suite-cinema', 'category' => 'privilege', 'minutes' => 240,
                'title' => [
                    'en' => 'In-Suite Cinema Evening',
                    'ar' => 'أمسية سينما في الجناح',
                    'fr' => 'Soirée cinéma en suite',
                ],
                'description' => [
                    'en' => "Your suite transformed: a cinema-grade projection screen, Dolby Atmos sound, and a custom tasting menu of small plates and artisanal beverages served course by course throughout the screening. Our concierge will select a film — or you may choose from our curated library.",
                    'ar' => 'يتحوّل جناحك بالكامل: شاشة عرض سينمائية، ونظام صوت دولبي أتموس، وقائمة تذوق مخصصة من الأطباق الصغيرة والمشروبات الحرفية تُقدَّم طبقًا تلو الآخر طوال العرض. سيختار الكونسيرج فيلمًا — أو يمكنك الاختيار من مكتبتنا المنتقاة.',
                    'fr' => "Votre suite transformée : un écran de projection digne d'une salle de cinéma, un système Dolby Atmos, et un menu dégustation sur mesure de petites assiettes et de boissons artisanales servies au fil de la projection. Notre concierge sélectionnera un film — ou vous pourrez choisir dans notre bibliothèque sélectionnée.",
                ],
            ],
            [
                'slug' => 'umayyad-mosque', 'category' => 'culture', 'minutes' => 300,
                'title' => [
                    'en' => 'Umayyad Mosque & Old City Walk',
                    'ar' => 'الجامع الأموي والجولة في المدينة القديمة',
                    'fr' => 'La Mosquée des Omeyyades & la Vieille Ville',
                ],
                'description' => [
                    'en' => "A private guided journey through the heart of Old Damascus — beginning at the seventh-century Umayyad Mosque, one of the oldest and grandest mosques in the Islamic world. Your expert guide leads you through Byzantine mosaics, ancient prayer halls, and winding stone lanes. A private courtyard lunch follows.",
                    'ar' => 'رحلة خاصة ومصحوبة بمرشد في قلب دمشق القديمة — تبدأ من الجامع الأموي الذي يعود إلى القرن السابع الميلادي، أحد أقدم المساجد وأعظمها في العالم الإسلامي. يقودك مرشدك الخبير عبر الفسيفساء البيزنطية وقاعات الصلاة العريقة والأزقة الحجرية. يلي ذلك غداء في فناء دمشقي خاص.',
                    'fr' => "Un parcours guidé en privé au cœur de la vieille Damas — depuis la mosquée des Omeyyades du VIIe siècle, l'une des plus anciennes du monde islamique. Votre guide vous mène à travers les mosaïques byzantines, les salles de prière millénaires et les ruelles pavées de la vieille ville. Un déjeuner dans une cour damascène privée clôture la visite.",
                ],
            ],
            [
                'slug' => 'old-city-bazaar', 'category' => 'culture', 'minutes' => 240,
                'title' => [
                    'en' => 'Al-Hamidiyah Souq Expedition',
                    'ar' => 'رحلة سوق الحميدية الكبير',
                    'fr' => 'Expédition au Souk Al-Hamidiyah',
                ],
                'description' => [
                    'en' => "The great vaulted covered market of Damascus, as it should be experienced — privately, unhurried, with a guide who knows every craftsman. Antique silver, hand-woven brocade, Damascene steel, and artisan sweets. Our concierge arranges appointments with the finest ateliers and negotiates on your behalf.",
                    'ar' => 'سوق دمشق المسقوف العظيم كما ينبغي أن يُرى — بشكل خاص وبلا استعجال، مع مرشد يعرف كل حرفي. أعمال فضية أنتيكا، وديباج منسوج يدويًا، وفولاذ دمشقي، وحلوى تقليدية حرفية. ينسّق الكونسيرج مواعيد مع أمهر الحرفيين ويتفاوض نيابةً عنك.',
                    'fr' => "Le grand souk couvert de Damas, tel qu'il mérite d'être découvert — en privé, sans hâte, guidé par quelqu'un qui connaît chaque artisan. Argenterie ancienne, brocarts tissés à la main, acier damascène et confiseries artisanales. Notre concierge organise des rendez-vous avec les meilleurs ateliers et négocie en votre nom.",
                ],
            ],
            [
                'slug' => 'qasioun-sunset', 'category' => 'culture', 'minutes' => 180,
                'title' => [
                    'en' => 'Qasioun Mountain at Dusk',
                    'ar' => 'جبل قاسيون عند الغسق',
                    'fr' => 'Le Mont Qasioun au crépuscule',
                ],
                'description' => [
                    'en' => "As Damascus glows below, your private driver takes you to the summit of Mount Qasioun — the mountain that has presided over Damascus for four thousand years. Arrive for the last hour of golden light, stay for the spectacle of a city illuminating at nightfall. Syrian sweets and silver tea service prepared on site.",
                    'ar' => 'بينما تتوهج دمشق في الأسفل، يأخذك سائقك الخاص إلى قمة جبل قاسيون — الجبل الذي أشرف على دمشق منذ أربعة آلاف عام. تصل في اللحظة الأخيرة من الضوء الذهبي، وتبقى لمشهد المدينة وهي تضيء عند حلول الليل. تُحضَّر حلويات سورية وطقم شاي فضي مسبقًا في الموقع.',
                    'fr' => "Tandis que Damas brille en contrebas, votre chauffeur privé vous conduit au sommet du Qasioun — la montagne qui veille sur Damas depuis quatre mille ans. Arrivez pour la dernière heure de lumière dorée et restez pour le spectacle d'une ville qui s'illumine à la tombée de la nuit. Thé argenté et douceurs syriennes préparés sur place.",
                ],
            ],
            [
                'slug' => 'azm-palace', 'category' => 'culture', 'minutes' => 180,
                'title' => [
                    'en' => 'Azm Palace & Damascene Heritage',
                    'ar' => 'قصر العظم والتراث الدمشقي',
                    'fr' => 'Le Palais Azm & le Patrimoine Damascène',
                ],
                'description' => [
                    'en' => "The 18th-century palace of the Ottoman governor of Damascus — considered the finest example of traditional Damascene domestic architecture. A private guided visit through the inner courtyards, the grand iwan, and the historic apartments. Your curator explains the architectural language of the mashrabiya, the muqarnas, and the elaborate marquetry that defines classical Damascus.",
                    'ar' => 'قصر والي دمشق العثماني من القرن الثامن عشر — يُعدّ النموذج الأرقى للعمارة المنزلية الدمشقية التقليدية. جولة خاصة بمرشد متخصص عبر الأفنية الداخلية والإيوان الكبير والأجنحة التاريخية. يشرح المرشد لغة العمارة الدمشقية: المشربية والمقرنصات والتطعيم الخشبي الدقيق.',
                    'fr' => "Le palais du gouverneur ottoman de Damas au XVIIIe siècle — considéré comme le chef-d'œuvre de l'architecture domestique damascène. Visite guidée privée des cours intérieures, du grand iwan et des appartements historiques. Votre guide vous explique le langage architectural de la mashrabiya, des muqarnas et de la marqueterie damascène.",
                ],
            ],
            [
                'slug' => 'calligraphy-workshop', 'category' => 'culture', 'minutes' => 120,
                'title' => [
                    'en' => 'Arabic Calligraphy with a Master',
                    'ar' => 'الخط العربي مع أستاذ متمرّس',
                    'fr' => 'Calligraphie Arabe avec un Maître',
                ],
                'description' => [
                    'en' => "A private atelier session with one of Damascus's last practicing master calligraphers. Learn the foundations of Nastaliq script, work with reed pen and walnut ink on fine Syrian paper, and leave with a composed piece bearing your name rendered in classical Arabic. Tea and conversation throughout.",
                    'ar' => 'جلسة خاصة في أتيليه أحد آخر أساتذة الخط العربي في دمشق. تتعلّم أسس خط النسخ والنستعليق، وتعمل بالقلم الرصاصي وحبر الجوز على أوراق سورية فاخرة، وتغادر بعملٍ فني يحمل اسمك بالخط العربي الكلاسيكي. شاي وحوار طوال الجلسة.',
                    'fr' => "Une session d'atelier privé avec l'un des derniers maîtres calligraphes de Damas. Apprenez les bases du Nastaliq, travaillez avec un calame de roseau et de l'encre de noyer sur du papier syrien fin, et repartez avec une composition portant votre nom en arabe classique. Thé et conversation tout au long de la session.",
                ],
            ],
            [
                'slug' => 'meze-masterclass', 'category' => 'gastronomy', 'minutes' => 180,
                'title' => [
                    'en' => 'Syrian Meze Masterclass',
                    'ar' => 'تحضير المازة السورية',
                    'fr' => 'Masterclass Meze Syrien',
                ],
                'description' => [
                    'en' => "A hands-on session in our private kitchen — led by Chef Nassar's sous-chef — dedicated entirely to the art of the Syrian meze table. Prepare kibbeh nayyeh, muhammara, fattoush, and a dozen other dishes from scratch, then sit down to eat what you have made. Recipes printed and bound for you to take home.",
                    'ar' => 'جلسة عملية في مطبخنا الخاص — بقيادة مساعد الشيف نصّار — مخصصة كلياً لفن مائدة المازة السورية. تحضّر الكبة النيئة والمحمّرة والفتوش واثني عشر طبقاً آخر من الصفر، ثم تجلس لتناول ما صنعته. تحصل على وصفاتك مطبوعة ومجلّدة للمنزل.',
                    'fr' => "Une session pratique dans notre cuisine privée — animée par le sous-chef de Nassar — entièrement dédiée à l'art de la table meze syrienne. Préparez kibbeh nayyeh, muhammara, fattoush et une douzaine d'autres plats, puis attablez-vous pour déguster ce que vous avez cuisiné. Les recettes sont imprimées et reliées pour vous.",
                ],
            ],
            [
                'slug' => 'rooftop-dawn', 'category' => 'privilege', 'minutes' => 120,
                'title' => [
                    'en' => 'Rooftop Dawn & Private Breakfast',
                    'ar' => 'سطح الفجر وإفطار خاص',
                    'fr' => "Toit à l'Aube & Petit-Déjeuner Privé",
                ],
                'description' => [
                    'en' => "Before the city wakes: the Al-Qamar rooftop reserved entirely for you, as the call to prayer echoes from the old mosques below. Our team sets a full Syrian breakfast — labneh, za'atar, warm bread, and fresh seasonal produce — accompanied by loose-leaf tea or hand-brewed Levantine coffee. No schedule, no other guests. Just Damascus at first light.",
                    'ar' => 'قبل أن تستيقظ المدينة: يُخصَّص سطح القمر كاملاً لك، بينما يتردّد صدى الأذان من المساجد القديمة في الأسفل. يُجهّز فريقنا إفطاراً سورياً متكاملاً — لبنة وزعتر وخبز طازج وموسميات طازجة — يرافقه شاي بالأوراق أو قهوة شامية مُعدّة يدوياً. لا جدول، لا ضيوف آخرون. دمشق عند الفجر لك وحدك.',
                    'fr' => "Avant le réveil de la ville : le toit Al-Qamar réservé entièrement pour vous, tandis que l'appel à la prière résonne depuis les vieilles mosquées en contrebas. Notre équipe dresse un petit-déjeuner syrien complet — labneh, za'atar, pain chaud et produits frais de saison — accompagné de thé en feuilles ou de café levantin préparé à la main. Aucun programme, aucun autre convive. Juste Damas à la première lumière.",
                ],
            ],
        ];

        foreach ($items as $i => $item) {
            $experience = Experience::create([
                'slug'             => $item['slug'],
                'title'            => $item['title'],
                'description'      => $item['description'],
                'category'         => $item['category'],
                'duration_minutes' => $item['minutes'],
                'price_usd'        => null,
                'is_active'        => true,
                'sort_order'       => $i,
            ]);

            // The site's own images are frontend assets (bundled files and
            // Unsplash URLs), so there is nothing here to copy — one labelled
            // placeholder per record gives the API a servable image URL.
            $this->attachPhotos($experience, [$item['title']['en']]);
        }
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
