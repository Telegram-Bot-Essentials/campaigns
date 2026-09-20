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
        Schema::create('campaign_misses', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Bot::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(BotUser::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(Campaign::class)->nullable()->constrained()->nullOnDelete();
            $table->string('payload', 64);
            // A string (cast to MissReason) rather than a DB enum, so a new
            // reason never needs an ALTER on a populated table.
            $table->string('reason', 32);
            $table->timestamps();

            // Deduped so hand-typed c_xxx links cannot grow the table unbounded.
            $table->unique(['bot_user_id', 'payload', 'reason']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_misses');
    }
};
