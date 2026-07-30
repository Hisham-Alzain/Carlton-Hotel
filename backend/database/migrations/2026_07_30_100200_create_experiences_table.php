<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// The concierge-arranged experiences the website lists on its Experiences page —
// spice journeys, private guided walks, in-suite cinema.
//
// Deliberately NOT `facilities` or `service_items`. A facility is a place inside
// the hotel that opens and closes; a service item is something a checked-in
// guest orders from their room. An experience is editorial copy about an outing
// arranged before arrival, with no bookable inventory behind it — the site's
// call to action is "Enquire", not "Book". When these do become bookable, the
// booking rows point at this table; they do not replace it.
//
// `slug` is the stable content key. The website currently hardcodes these
// records under ids like `bab-sharqi`, so the slug is what lets a content
// migration (or a re-seed) recognise the record it already had. Route binding
// still goes through `uuid` like every other public route — the slug is a
// filter/join key, not an address.
//
// `category` is a plain indexed string holding a stable lowercase key
// (`gastronomy`, `culture`, `privilege`), NOT a translated label. The site's
// filter chips read a per-locale label out of its own translations; a column
// that has to be indexed and filtered cannot also be locale-dependent, and an
// enum would mean a migration every time marketing invents a theme.
//
// `duration_minutes` is one number where the site prints a range ("2–3 hours").
// It carries the upper bound, because the number a concierge blocks out has to
// be the longest the experience can run. `price_usd` is nullable and seeded
// null: the site publishes no prices for these and invented ones would be a
// quote the hotel never gave.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('experiences', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('slug')->unique()->index();
            $table->json('title');
            $table->json('description');
            $table->string('category')->index();
            $table->unsignedSmallInteger('duration_minutes')->nullable();
            $table->decimal('price_usd', 10, 2)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedSmallInteger('sort_order')->default(0)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('experiences');
    }
};
