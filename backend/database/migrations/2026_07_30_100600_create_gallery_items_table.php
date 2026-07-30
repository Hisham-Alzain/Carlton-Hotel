<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// One photograph in the website's gallery. The image itself lives in `media`
// through the same `mediable` morph every other CMS model uses; this row carries
// only the caption, the chip it belongs to, and the editor's ordering.
//
// ON DELETE CASCADE, not RESTRICT or SET NULL: a gallery item has no meaning
// outside its chip. `SET NULL` would leave photographs the website can never
// render (it groups strictly by category), and `RESTRICT` would force an editor
// to delete twenty rows by hand before retiring a chip. Deleting a category is
// an explicit editorial act, and the media rows are cleaned up by the same
// morph-delete path as any other CMS attachment.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gallery_items', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('gallery_category_id')
                ->index()
                ->constrained('gallery_categories')
                ->cascadeOnDelete();
            $table->json('caption');
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedSmallInteger('sort_order')->default(0)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gallery_items');
    }
};
