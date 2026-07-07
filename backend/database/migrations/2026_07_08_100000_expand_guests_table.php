<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('guests', function (Blueprint $table) {
            // At least one of phone/email required — enforced in application layer, not DB
            $table->string('phone')->nullable()->unique()->after('name');
            $table->string('phone_country', 2)->nullable()->after('phone');
            $table->timestamp('phone_verified_at')->nullable()->after('phone_country');
            $table->string('email')->nullable()->unique()->after('phone_verified_at');
            $table->timestamp('email_verified_at')->nullable()->after('email');
            $table->string('first_name')->nullable()->after('email_verified_at');
            $table->string('last_name')->nullable()->after('first_name');
            $table->string('preferred_locale', 2)->nullable()->default('en')->after('last_name');
            $table->index('last_name');
        });
    }

    public function down(): void
    {
        Schema::table('guests', function (Blueprint $table) {
            $table->dropColumn(['phone','phone_country','phone_verified_at','email','email_verified_at','first_name','last_name','preferred_locale']);
        });
    }
};
