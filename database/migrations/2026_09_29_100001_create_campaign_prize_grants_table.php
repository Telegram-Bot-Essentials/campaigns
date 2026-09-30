<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use TelegramBotEssentials\Billing\Models\Invoice;
use TelegramBotEssentials\Campaigns\Models\CampaignAttribution;
use TelegramBotEssentials\Campaigns\Models\CampaignPrize;
use TelegramBotEssentials\Essence\Models\Bot;
use TelegramBotEssentials\Essence\Models\BotUser;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaign_prize_grants', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Bot::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(CampaignPrize::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(CampaignAttribution::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(BotUser::class)->constrained()->cascadeOnDelete();
            $table->string('status')->default('pending');
            $table->unsignedInteger('attempts')->default(0);
            $table->text('error')->nullable();
            // Whatever the prize type needs to make a retry safe.
            $table->json('meta')->nullable();
            // Set by PrizeSettlement for prizes that are an order paid by a PrizePaymentAttempt.
            $table->foreignIdFor(Invoice::class)->nullable()->constrained()->nullOnDelete();
            $table->nullableMorphs('order');
            $table->timestamp('granted_at')->nullable();
            $table->timestamps();

            // A user is owed each prize once, however often the listener runs.
            $table->unique(['campaign_prize_id', 'campaign_attribution_id'], 'prize_grants_prize_attribution_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_prize_grants');
    }
};
