<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// The two per-experience details the public site renders but the table could not
// supply, so the site recovered them from bundled copy keyed by slug — which
// covers the twelve seeded records and leaves every new CMS record blank. An
// editor could not create a complete experience.
//
// `group_size` is translatable copy, not a number pair. The site prints it
// verbatim in two places (the card meta row and the modal's "Group Size" row) as
// "2–6 guests" / "2–6 ضيوف" / "2–6 convives" — the noun is part of the string and
// it is a marketing range ("1–6 guests" for a walking tour), not an inventory
// constraint. When these become bookable, occupancy limits belong on the booking
// rows, where they can be enforced; this column is a label.
//
// ## Duration: a translatable label, not `duration_min_minutes` + `duration_max_minutes`
//
// The site's real strings cannot be produced from a numeric range:
//
//   en  "2–3 hours"   "Half day"      "2 hours"
//   ar  "ساعتان – 3 ساعات"  "نصف يوم"   "ساعتان"
//   fr  "2–3 heures"  "Demi-journée"  "2 heures"
//
//   * "Half day" / "نصف يوم" / "Demi-journée" is not a count of hours at all. The
//     nearest number, 240, renders "4 hours" — a different promise.
//   * Arabic uses the grammatical dual: "ساعتان" is a single word meaning "two
//     hours", and the two-hour range reads "ساعتان – 3 ساعات", a spelled dual
//     against a numeral. `Intl.NumberFormat('ar', {unit: 'hour'})` yields
//     "2 ساعة"; it has no dual form to reach for.
//   * The site already demonstrates the ceiling. `durationLabel()` in
//     `src/app/content/adapters.ts` formats `duration_minutes` through `Intl` and
//     can only ever print one value ("3 hours"), never a range — which is exactly
//     why the slug-keyed fallback had to exist.
//
// A minutes pair would therefore force the backend (or every client) to own
// locale-specific number grammar plus a special case for "half day". A
// translatable label reproduces the published copy byte for byte and keeps
// formatting out of the API entirely — the API carries what the hotel wrote.
//
// `duration_minutes` is deliberately KEPT and still populated: it is the block of
// time a concierge reserves, it is what `ExperienceFilter` sorts and filters on
// ("show me everything under three hours"), and other code reads it. The two are
// not redundant — one is schedulable, one is publishable. The label is the upper
// bound's prose sibling, not its replacement.
//
// Additive and nullable: existing rows keep working, and both fields are optional
// for an editor who has not written them yet.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('experiences', function (Blueprint $table) {
            // Not indexed: display copy. Nothing filters, sorts or joins on
            // either, and a JSON column would not serve a leftmost-prefix
            // lookup anyway.
            $table->json('group_size')->nullable()->after('category');
            $table->json('duration_label')->nullable()->after('duration_minutes');
        });
    }

    public function down(): void
    {
        Schema::table('experiences', function (Blueprint $table) {
            $table->dropColumn(['group_size', 'duration_label']);
        });
    }
};
