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
            BookingSeeder::class,
            EventInquirySeeder::class,
            ServiceCatalogSeeder::class,
            GuestActivitySeeder::class,
        ]);
    }
}
