<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaign_prizes', function (Blueprint $table) {
            $table->string('method')->default('click');
            $table->json('method_config')->nullable();
        });

        Schema::table('campaign_prize_grants', function (Blueprint $table) {
            // A copy of the prize's method at the time the grant was issued, so
            // changing the prize later does not alter a game already offered.
            $table->string('method')->default('click');
            $table->json('method_config')->nullable();
            // The message announcing the prize, edited as the outcome settles.
            $table->unsignedBigInteger('message_id')->nullable();
            // The bot's "throw your dice" message: set once the user opted in
            // to play, and the marker a throw must come after.
            $table->unsignedBigInteger('prompt_message_id')->nullable();
            $table->unsignedInteger('plays')->default(0);
            $table->unsignedBigInteger('last_throw_id')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('campaign_prize_grants', function (Blueprint $table) {
            $table->dropColumn(['method', 'method_config', 'message_id', 'prompt_message_id', 'plays', 'last_throw_id']);
        });

        Schema::table('campaign_prizes', function (Blueprint $table) {
            $table->dropColumn(['method', 'method_config']);
        });
    }
};
