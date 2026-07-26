<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('guest_id')->constrained()->cascadeOnDelete();
            $table->morphs('reviewable');
            // The stay that earns the "verified" badge. Nullable because a guest
            // may review a restaurant they visited without a room booking.
            $table->foreignId('reservation_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedTinyInteger('rating');
            $table->text('comment')->nullable();
            $table->boolean('is_verified_stay')->default(false);
            $table->boolean('is_published')->default(true)->index();
            $table->timestamps();

            // One review per guest per subject — a re-submission updates in place.
            $table->unique(['guest_id', 'reviewable_type', 'reviewable_id'], 'reviews_guest_subject_unique');
            $table->index('guest_id');
            $table->index('reservation_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
