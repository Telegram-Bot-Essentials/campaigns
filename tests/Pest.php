<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use TelegramBotEssentials\Campaigns\Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class)->in('Feature');

/**
 * Replaces the base Http fake with one that answers like Telegram: getMe
 * gives the bot a username, and every send answers with a message. Swapped
 * in wholesale, because the base fake's catch-all would otherwise match first.
 */
function fakeTelegram(): void
{
    $factory = new Factory;
    $factory->fake(function ($request) {
        $url = (string) $request->url();

        return match (true) {
            str_ends_with($url, '/getMe') => Http::response(['ok' => true, 'result' => [
                'id' => 1, 'is_bot' => true, 'first_name' => 'Test Bot', 'username' => 'test_bot',
            ]]),
            str_ends_with($url, '/sendMessage'),
            str_ends_with($url, '/sendPhoto') => Http::response(['ok' => true, 'result' => [
                'message_id' => random_int(1, 999999), 'date' => time(),
                'chat' => ['id' => 1, 'type' => 'private'], 'text' => 'sent',
            ]]),
            default => Http::response(['ok' => true, 'result' => true]),
        };
    });
    Http::swap($factory);
}

/** Recorded Telegram API calls of one method, oldest first. */
function tgCalls(string $method): Collection
{
    return Http::recorded(fn ($request) => str_ends_with((string) $request->url(), '/'.$method))
        ->map(fn ($pair) => $pair[0]->data())
        ->values();
}

/** The text of the last message the bot sent or edited, in the order they happened. */
function lastBotText(): string
{
    return (string) Http::recorded(fn ($request) => str_ends_with((string) $request->url(), '/sendMessage')
        || str_ends_with((string) $request->url(), '/editMessageText'))
        ->map(fn ($pair) => $pair[0]->data()['text'] ?? null)
        ->filter()
        ->last();
}

/** An inline button press by the user with this peer id. */
function pressAs(int $peer, string $type, string $method, array $params = []): void
{
    test()->postWebhookUpdate(test()->bot, test()->makeCallbackQueryUpdate(encodeCallback($type, $method, $params), peerId: $peer))->assertOk();
}
