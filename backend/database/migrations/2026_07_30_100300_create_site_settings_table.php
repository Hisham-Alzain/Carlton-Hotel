<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Global website copy and configuration that belongs to no single content
// entity: the switchboard number, the footer tagline, the hero headline.
//
// `value` is `json` so a translatable setting and a scalar one can share one
// table. The footer tagline stores `{"en": "...", "ar": "...", "fr": "..."}`;
// the reservations phone number stores the JSON scalar `"+963 (0)11 000 00 00"`.
// Splitting these into two tables (or adding five nullable per-locale columns)
// would mean every new setting is a schema decision. `type` tells the CMS which
// editor widget to render for the row and is validated against
// `App\Enums\SettingType` at the request boundary — the column itself is a
// plain string because a MySQL `enum` cannot be extended without an ALTER.
//
// The composite unique on (group, key) is the real primary identity: `group` on
// its own is not unique, `key` on its own is not either ("cta_label" exists in
// both `booking` and `hero`). Without it a bulk upsert that ran twice
// concurrently would leave two rows for one key and the public map would
// non-deterministically pick one.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_settings', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            // Indexed on its own as well as inside the composite: the CMS list
            // screen and the public map both group by it, and the composite's
            // leftmost prefix is `group`, so this standalone index is redundant
            // for lookups — it is declared because the module spec asks for it
            // and MySQL will happily use whichever is cheaper.
            $table->string('group', 50)->index();
            $table->string('key', 100);
            $table->json('value')->nullable();
            $table->string('type', 20);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->unique(['group', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_settings');
    }
};
