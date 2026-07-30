<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Curated marketing quotes for the website's "Guest Reflections" section.
//
// Deliberately NOT the `reviews` table. A `Review` is guest-authored UGC bound
// to a reviewable entity and gated by its own moderation flow; a testimonial is
// copy an editor writes or licenses, with no author account behind it. Merging
// the two would couple moderation to marketing — the day marketing wants to
// reword a quote, they would be editing a guest's words. A "promote a published
// review into a testimonial" action can be added later; it copies, it does not
// alias.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('testimonials', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            // Not translatable: a person's name is not translated, it is
            // transliterated at most, and the site prints it verbatim.
            $table->string('author_name');
            // The line under the name — origin, suite stayed in, or job title.
            $table->json('author_title')->nullable();
            $table->json('quote');
            $table->unsignedTinyInteger('rating')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedSmallInteger('sort_order')->default(0)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('testimonials');
    }
};
