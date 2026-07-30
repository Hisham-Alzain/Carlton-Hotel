<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Questions and answers for the website's FAQ section.
//
// `category` is a nullable free-text grouping rather than a foreign key or an
// enum: the site currently renders one flat list, and inventing a taxonomy the
// design does not use would be a table nobody maintains. When the design grows
// sections, this column already carries them; promote it to its own table only
// once editors need to rename a group in five languages.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('faqs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('category')->nullable()->index();
            $table->json('question');
            $table->json('answer');
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedSmallInteger('sort_order')->default(0)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('faqs');
    }
};
