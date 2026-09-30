<?php

namespace TelegramBotEssentials\Campaigns\Services;

use TelegramBotEssentials\Campaigns\Contracts\ClaimMethod;
use TelegramBotEssentials\Essence\Exceptions\LogicException;

/**
 * The claim methods known to the bot, by key. A singleton like `PrizeTypes`:
 * providers register once per worker, from boot().
 */
class ClaimMethods
{
    /** @var array<string, ClaimMethod> */
    private array $methods = [];

    public function register(ClaimMethod $method): self
    {
        if (isset($this->methods[$method->key()]) && $method::class !== $this->methods[$method->key()]::class) {
            throw new LogicException(sprintf('Claim method "%s" is already registered by %s.', $method->key(), $this->methods[$method->key()]::class));
        }

        $this->methods[$method->key()] = $method;

        if ($method->configForm() !== null) {
            formRegistry()->addForm($method->configForm());
        }

        return $this;
    }

    public function get(string $key): ?ClaimMethod
    {
        return $this->methods[$key] ?? null;
    }

    /** @return array<string, ClaimMethod> */
    public function all(): array
    {
        return $this->methods;
    }
}
