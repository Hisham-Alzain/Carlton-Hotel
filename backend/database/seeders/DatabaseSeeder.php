<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Order matters — each seeder after RolesAndPermissionsSeeder depends on
     * records created by the ones before it (guests before reservations,
     * content before bookings, catalog before service bookings, etc.).
     */
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            StaffSeeder::class,
            GuestSeeder::class,
            CmsContentSeeder::class,
            AmenitySeeder::class,
            ReviewSeeder::class,
            BookingSeeder::class,
            EventInquirySeeder::class,
            ServiceCatalogSeeder::class,
            GuestServiceCatalogSeeder::class,
            GuestActivitySeeder::class,
            // Last: overlays the Flutter demo content (mobile/lib/constants/
            // demo_data.dart) and its artwork on top of the generic CMS rows,
            // deliberately overwriting the stock menu every venue was given.
            MobileDemoSeeder::class,
            // Very last: real photos over every placeholder, plus a year of
            // history and six months of bookings in every workflow state.
            DemoShowcaseSeeder::class,
        ]);
    }
}
