<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Turn `media` from an attachment side-table into a media library.
 *
 * Two additive columns:
 *
 * - `alt_text` json — translatable via `HasTranslations`, because alt text is
 *   prose a screen reader speaks and a crawler indexes, so it needs the same
 *   five locales as every other editor-authored string. json, not string, for
 *   exactly that reason.
 * - `title` string — the editor-facing label in the picker. Not translatable:
 *   it names the *asset* ("Rooftop pool, dusk"), not page copy, and the CMS is
 *   one shared workspace.
 *
 * And one relaxation, the only non-purely-additive change in the CMS project:
 * `mediable_type` / `mediable_id` become nullable, so a row can exist in the
 * library before anything points at it. `POST /api/cms/media` writes such a row;
 * `POST /api/cms/{parent}/{uuid}/images/attach` copies it onto a parent.
 *
 * Why relaxing NOT NULL here is safe — verified, not assumed:
 *
 * 1. The morph is written in exactly one place (`MediaService::attach`) and in
 *    tests through `$parent->images()->create()`. Nothing else in `app/`,
 *    `database/` or `routes/` assigns `mediable_type`/`mediable_id`.
 * 2. All twelve `morphMany(Media::class, 'mediable')` relations compile to
 *    `where mediable_type = ? and mediable_id = ?`, and SQL equality never
 *    matches NULL — so an unattached row is invisible to every existing read.
 * 3. `Media::mediable()` (the inverse) has no caller in `app/`; the one caller
 *    anywhere is a GalleryTest assertion on an attached row.
 * 4. `MediaResource` never exposes the parent, so no response shape changes.
 *
 * `->change()` rather than `nullableMorphs()`: the columns already exist, and
 * `nullableMorphs()` would try to create them a second time. `change()` restates
 * the original `morphs()` definition (`string`, `unsignedBigInteger`) with
 * `nullable()` added — MySQL keeps the composite index through the MODIFY, and
 * SQLite rebuilds the table with it.
 *
 * Reversible: `down()` cannot restore NOT NULL while library rows exist, so it
 * detaches nothing and deletes nothing — it drops the two columns and puts the
 * constraint back only if every row is attached. Anything else would silently
 * destroy assets.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media', function (Blueprint $table): void {
            $table->json('alt_text')->nullable()->after('file_name');
            $table->string('title')->nullable()->after('alt_text');

            // `GET /api/cms/media?mime=image/webp` is a first-class filter on the
            // library screen. The `unattached` flag needs no index of its own:
            // `mediable_type` is already the leading column of the composite
            // morph index, which serves `IS NULL` as well as `=`.
            $table->index('mime_type');
        });

        // Separate closure: a column addition and a column change in one
        // Blueprint are two statements anyway, and keeping them apart makes the
        // rebuild SQLite performs for `change()` operate on the final shape.
        Schema::table('media', function (Blueprint $table): void {
            $table->string('mediable_type')->nullable()->change();
            $table->unsignedBigInteger('mediable_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('media', function (Blueprint $table): void {
            $table->dropIndex(['mime_type']);
            $table->dropColumn(['alt_text', 'title']);
        });

        if (\Illuminate\Support\Facades\DB::table('media')->whereNull('mediable_type')->exists()) {
            // Library rows have no parent to restore. Reinstating NOT NULL would
            // mean inventing one or deleting the asset; leaving the column
            // nullable is the only non-destructive answer.
            return;
        }

        Schema::table('media', function (Blueprint $table): void {
            $table->string('mediable_type')->nullable(false)->change();
            $table->unsignedBigInteger('mediable_id')->nullable(false)->change();
        });
    }
};
