<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use TelegramBotEssentials\Campaigns\Models\Campaign;
use TelegramBotEssentials\Essence\Models\Bot;
use TelegramBotEssentials\Essence\Models\BotUser;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaign_attributions', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Bot::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(BotUser::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(Campaign::class)->constrained();
            $table->timestamps();

            // First touch wins: a brand-new user can only ever be attributed once.
            $table->unique(['bot_id', 'bot_user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_attributions');
    }
};
