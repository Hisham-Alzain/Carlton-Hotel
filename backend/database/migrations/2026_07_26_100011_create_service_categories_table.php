<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Top level of the guest service menu. `code` is the stable machine key and is
// what gets snapshotted into service_requests.type, so the P10 operations queue
// and Department::forServiceType() keep working unchanged.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_categories', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('code', 50)->unique();
            $table->json('name');
            $table->json('description')->nullable();
            $table->string('kind', 20);
            // Routing target for requests created here; null for link/toggle kinds.
            $table->string('department', 30)->nullable();
            // Only for kind=link — which existing module the app should open.
            $table->string('link_target', 30)->nullable();
            $table->string('icon', 50)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_categories');
    }
};
