<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Creates the branding settings table. */
return new class extends Migration
{
    public function up(): void
    {
        // Generic key/value settings store backing the branding management UI.
        // `value` is longText so it can hold a base64-encoded logo (a TEXT column
        // caps at ~64KB on MySQL).
        Schema::create('branding_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->longText('value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branding_settings');
    }
};
