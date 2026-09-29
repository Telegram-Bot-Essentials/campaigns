<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use TelegramBotEssentials\Campaigns\Models\Campaign;
use TelegramBotEssentials\Essence\Models\Bot;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaign_prizes', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Bot::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(Campaign::class)->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->json('config');
            // Null = unlimited. grants_count counts pending, processing, granted
            // and failed grants: a user who was promised a prize keeps their slot.
            $table->unsignedInteger('max_grants')->nullable();
            $table->unsignedInteger('grants_count')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_prizes');
    }
};
