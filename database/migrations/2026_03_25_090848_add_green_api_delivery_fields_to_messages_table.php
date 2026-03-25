<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->string('relay_channel')->nullable();
            $table->string('provider_message_id')->nullable()->unique();
            $table->string('provider_status')->nullable();
            $table->text('provider_error')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropUnique(['provider_message_id']);
            $table->dropColumn([
                'relay_channel',
                'provider_message_id',
                'provider_status',
                'provider_error',
            ]);
        });
    }
};
