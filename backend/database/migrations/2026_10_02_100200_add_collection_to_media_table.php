<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 8 (D-07): which role a media row plays on its parent.
 *
 * `images` (the default, so every existing row keeps its behaviour) or `menu`
 * (a dining venue's downloadable menu file, written only through the venue's
 * `menu-file` route). A morph-attached menu row inherits `PurgesMedia` and the
 * shared-file purge guard for free. The composite index serves the scoped
 * `images()` / `menuFile()` relations (leftmost prefix = the morph columns).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media', function (Blueprint $table): void {
            $table->string('collection', 20)->default('images')->after('mediable_id');

            $table->index(['mediable_type', 'mediable_id', 'collection']);
        });
    }

    public function down(): void
    {
        Schema::table('media', function (Blueprint $table): void {
            $table->dropIndex(['mediable_type', 'mediable_id', 'collection']);
            $table->dropColumn('collection');
        });
    }
};
