<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Editorial articles for the website's Journal section.
//
// `published_on` IS A DISPLAY DATE, NOT A SCHEDULE. Publishing is `is_active`,
// by explicit product decision. Nothing in this codebase may compare it to
// `now()`: a `where('published_on', '<=', now())` would silently turn an
// editorial date field into a scheduling feature, and a post an editor dated
// next month (a preview, a dated retrospective, a typo) would vanish from the
// site with no error and no explanation. Its only job is to order the public
// list, newest first, and to print under the title.
// `JournalPostTest::test_future_dated_active_post_is_still_returned_publicly`
// is the regression guard — if it fails, someone added the scheduling filter.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journal_posts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            // The website's URL segment: /journal/{slug}. `unique()` already
            // creates the index the slug lookup needs, so there is no separate
            // `index()` here — a second index on the same single column would
            // be dead weight the writer still has to maintain.
            $table->string('slug')->unique();
            $table->json('title');
            $table->json('excerpt')->nullable();
            $table->json('body');
            // Translatable: the site prints the category as a label, so it is
            // copy, not a machine key. Filtering is via `?search=`, not `eq`.
            $table->json('category')->nullable();
            // NOT NULL: every post the site renders shows a date, and a null
            // date would leave the public ordering undefined for that row.
            $table->date('published_on')->index();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedSmallInteger('sort_order')->default(0)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_posts');
    }
};
