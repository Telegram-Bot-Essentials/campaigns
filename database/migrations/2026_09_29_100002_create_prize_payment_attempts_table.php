<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use TelegramBotEssentials\Campaigns\Models\CampaignPrizeGrant;
use TelegramBotEssentials\Essence\Models\Bot;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prize_payment_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Bot::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(CampaignPrizeGrant::class)->constrained()->cascadeOnDelete();
            $table->decimal('amount', 65, 30);
            $table->string('status')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prize_payment_attempts');
    }
};
