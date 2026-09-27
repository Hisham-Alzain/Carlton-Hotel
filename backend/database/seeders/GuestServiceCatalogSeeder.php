<?php

namespace Database\Seeders;

use App\Enums\Department;
use App\Enums\ServiceCategoryKind;
use App\Models\ServiceCategory;
use App\Models\ServiceItem;
use Illuminate\Database\Seeder;

/**
 * The ten top-level services the mobile app shows, and the microservices
 * under them. Idempotent: keyed on category `code` and item name so re-running
 * never duplicates rows.
 *
 * `late_checkout` (reception) and `luggage` (concierge) were added in Phase 6
 * for departure services and the quick-request chips (D-19, D-23). Their
 * default items are unpriced on purpose, so a request that was never granted
 * never lands on a folio (fees are deferred).
 *
 * `expected_minutes` values are seeds for operations to tune, not promises.
 * `price_usd` is null for complimentary items; a value is billed to the folio
 * by GenerateFolioAction when a request for the item exists.
 */
class GuestServiceCatalogSeeder extends Seeder
{
    private const CATALOG = [
        [
            'code' => 'room_service', 'kind' => ServiceCategoryKind::CATALOG, 'department' => Department::KITCHEN,
            'icon' => 'room_service', 'sort' => 0,
            'name' => ['en' => 'Room Service', 'ar' => 'خدمة الغرف'],
            'description' => ['en' => 'Dining delivered to your room.', 'ar' => 'وجبات تُقدَّم في غرفتك.'],
            'items' => [
                ['en' => 'Carlton Breakfast', 'ar' => 'فطور كارلتون',
                 'den' => 'Full breakfast selection with fresh juice', 'dar' => 'تشكيلة فطور كاملة مع عصير طازج',
                 'minutes' => 30, 'price' => 18],
                ['en' => 'Lunch Menu', 'ar' => 'قائمة الغداء',
                 'den' => 'Syrian and international cuisine', 'dar' => 'مأكولات سورية وعالمية',
                 'minutes' => 45, 'price' => 24],
                ['en' => 'Late Night Menu', 'ar' => 'قائمة ما بعد منتصف الليل',
                 'den' => 'Light bites available until 2 AM', 'dar' => 'وجبات خفيفة متاحة حتى الساعة 2 صباحاً',
                 'minutes' => 30, 'price' => 15],
            ],
        ],
        [
            'code' => 'housekeeping', 'kind' => ServiceCategoryKind::CATALOG, 'department' => Department::HOUSEKEEPING,
            'icon' => 'housekeeping', 'sort' => 1,
            'name' => ['en' => 'House Keeping', 'ar' => 'خدمة تنظيف الغرف'],
            'description' => ['en' => 'Keep your room fresh throughout your stay.', 'ar' => 'حافظ على نظافة غرفتك طوال إقامتك.'],
            'items' => [
                ['en' => 'Room Cleaning', 'ar' => 'تنظيف الغرفة',
                 'den' => 'Full room service and tidying', 'dar' => 'تنظيف وترتيب كامل للغرفة',
                 'minutes' => 45, 'price' => null],
                ['en' => 'Fresh Towels', 'ar' => 'مناشف نظيفة',
                 'den' => 'Towels and linens replacement', 'dar' => 'تبديل المناشف والمفروشات',
                 'minutes' => 15, 'price' => null],
                ['en' => 'Turndown Service', 'ar' => 'خدمة تجهيز السرير',
                 'den' => 'Evening bed preparation', 'dar' => 'تجهيز السرير مساءً',
                 'minutes' => 20, 'price' => null],
            ],
        ],
        [
            'code' => 'laundry', 'kind' => ServiceCategoryKind::CATALOG, 'department' => Department::HOUSEKEEPING,
            'icon' => 'laundry', 'sort' => 2,
            'name' => ['en' => 'Laundry', 'ar' => 'المغسلة'],
            'description' => ['en' => 'Cleaning and pressing for your wardrobe.', 'ar' => 'تنظيف وكي لملابسك.'],
            'items' => [
                ['en' => 'Express Laundry', 'ar' => 'غسيل سريع',
                 'den' => 'Fast cleaning for items you need today.', 'dar' => 'تنظيف سريع للقطع التي تحتاجها اليوم.',
                 'minutes' => 240, 'price' => 25],
                ['en' => 'Dry Cleaning', 'ar' => 'تنظيف جاف',
                 'den' => 'Suits, dresses, and delicates', 'dar' => 'بدلات وفساتين وأقمشة حساسة',
                 'minutes' => 1440, 'price' => 30],
                ['en' => 'Pressing Service', 'ar' => 'خدمة الكي',
                 'den' => 'Quick pressing for a crisp finish.', 'dar' => 'كي سريع لمظهر أنيق.',
                 'minutes' => 120, 'price' => 12],
            ],
        ],
        [
            'code' => 'concierge', 'kind' => ServiceCategoryKind::DIRECT, 'department' => Department::CONCIERGE,
            'icon' => 'concierge', 'sort' => 3,
            'name' => ['en' => 'Concierge', 'ar' => 'الكونسيرج'],
            'description' => ['en' => 'Bookings, directions and local recommendations.', 'ar' => 'حجوزات وإرشادات وتوصيات محلية.'],
            'items' => [
                ['en' => 'Concierge Assistance', 'ar' => 'مساعدة الكونسيرج',
                 'den' => 'Tell us what you need and we will arrange it.', 'dar' => 'أخبرنا بما تحتاجه وسنرتّبه.',
                 'minutes' => 30, 'price' => null, 'default' => true],
            ],
        ],
        [
            'code' => 'transport', 'kind' => ServiceCategoryKind::DIRECT, 'department' => Department::CONCIERGE,
            'icon' => 'transport', 'sort' => 4,
            'name' => ['en' => 'Transport', 'ar' => 'النقل'],
            'description' => ['en' => 'Airport pickup, taxis and city transfers.', 'ar' => 'استقبال من المطار وسيارات أجرة وتنقلات داخل المدينة.'],
            'items' => [
                ['en' => 'Transport Request', 'ar' => 'طلب نقل',
                 'den' => 'Tell us where and when.', 'dar' => 'أخبرنا بالوجهة والوقت.',
                 'minutes' => 30, 'price' => null, 'default' => true],
            ],
        ],
        [
            'code' => 'restaurant', 'kind' => ServiceCategoryKind::LINK, 'department' => null,
            'icon' => 'restaurant', 'sort' => 5, 'link_target' => 'dining',
            'name' => ['en' => 'Restaurant', 'ar' => 'المطعم'],
            'description' => ['en' => 'Browse menus and reserve a table.', 'ar' => 'تصفّح القوائم واحجز طاولة.'],
            'items' => [],
        ],
        [
            'code' => 'maintenance', 'kind' => ServiceCategoryKind::DIRECT, 'department' => Department::MAINTENANCE,
            'icon' => 'maintenance', 'sort' => 6,
            'name' => ['en' => 'Maintenance', 'ar' => 'الصيانة'],
            'description' => ['en' => 'Report anything that needs fixing.', 'ar' => 'أبلغ عن أي شيء يحتاج إلى إصلاح.'],
            'items' => [
                ['en' => 'Maintenance Request', 'ar' => 'طلب صيانة',
                 'den' => 'Describe the issue and we will send someone.', 'dar' => 'صف المشكلة وسنرسل من يعالجها.',
                 'minutes' => 45, 'price' => null, 'default' => true],
            ],
        ],
        [
            'code' => 'do_not_disturb', 'kind' => ServiceCategoryKind::TOGGLE, 'department' => null,
            'icon' => 'do_not_disturb', 'sort' => 7,
            'name' => ['en' => 'Do Not Disturb', 'ar' => 'عدم الإزعاج'],
            'description' => ['en' => 'Hold all visits to your room.', 'ar' => 'إيقاف جميع الزيارات إلى غرفتك.'],
            'items' => [],
        ],
        [
            'code' => 'late_checkout', 'kind' => ServiceCategoryKind::DIRECT, 'department' => Department::RECEPTION,
            'icon' => 'late_checkout', 'sort' => 8,
            'name' => ['en' => 'Late Checkout', 'ar' => 'تأخير المغادرة'],
            'description' => ['en' => 'Ask to keep your room past check-out time.', 'ar' => 'اطلب الاحتفاظ بغرفتك بعد موعد المغادرة.'],
            'items' => [
                ['en' => 'Late Checkout Request', 'ar' => 'طلب تأخير المغادرة',
                 'den' => 'Tell us what time you would like to leave.', 'dar' => 'أخبرنا بالوقت الذي تودّ المغادرة فيه.',
                 'minutes' => 15, 'price' => null, 'default' => true],
            ],
        ],
        [
            'code' => 'luggage', 'kind' => ServiceCategoryKind::DIRECT, 'department' => Department::CONCIERGE,
            'icon' => 'luggage', 'sort' => 9,
            'name' => ['en' => 'Luggage', 'ar' => 'الأمتعة'],
            'description' => ['en' => 'Luggage storage and help with your bags.', 'ar' => 'حفظ الأمتعة والمساعدة في حمل حقائبك.'],
            'items' => [
                ['en' => 'Luggage Assistance', 'ar' => 'المساعدة في الأمتعة',
                 'den' => 'Tell us how many bags and when.', 'dar' => 'أخبرنا بعدد الحقائب والوقت.',
                 'minutes' => 20, 'price' => null, 'default' => true],
            ],
        ],
    ];

    public function run(): void
    {
        foreach (self::CATALOG as $c) {
            $category = ServiceCategory::updateOrCreate(
                ['code' => $c['code']],
                [
                    'name'        => $c['name'],
                    'description' => $c['description'],
                    'kind'        => $c['kind'],
                    'department'  => $c['department'],
                    'link_target' => $c['link_target'] ?? null,
                    'icon'        => $c['icon'],
                    'is_active'   => true,
                    'sort_order'  => $c['sort'],
                ],
            );

            foreach ($c['items'] as $i => $item) {
                ServiceItem::updateOrCreate(
                    [
                        'service_category_id' => $category->id,
                        'name->en'            => $item['en'],
                    ],
                    [
                        'name'             => ['en' => $item['en'], 'ar' => $item['ar']],
                        'description'      => ['en' => $item['den'], 'ar' => $item['dar']],
                        'expected_minutes' => $item['minutes'],
                        'price_usd'        => $item['price'],
                        'is_default'       => $item['default'] ?? false,
                        'is_active'        => true,
                        'sort_order'       => $i,
                    ],
                );
            }
        }
    }
}
