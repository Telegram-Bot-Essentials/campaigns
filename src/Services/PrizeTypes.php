<?php

namespace TelegramBotEssentials\Campaigns\Services;

use TelegramBotEssentials\Campaigns\Contracts\PrizeType;
use TelegramBotEssentials\Essence\Exceptions\LogicException;

/**
 * The prize types known to the bot, by key. A singleton like the other
 * registries: providers register once per worker, from boot().
 */
class PrizeTypes
{
    /** @var array<string, PrizeType> */
    private array $types = [];

    public function register(PrizeType $type): self
    {
        if (isset($this->types[$type->key()]) && $type::class !== $this->types[$type->key()]::class) {
            throw new LogicException(sprintf('Prize type "%s" is already registered by %s.', $type->key(), $this->types[$type->key()]::class));
        }

        $this->types[$type->key()] = $type;

        formRegistry()->addForm($type->configForm());

        return $this;
    }

    public function get(string $key): ?PrizeType
    {
        return $this->types[$key] ?? null;
    }

    /** @return array<string, PrizeType> */
    public function all(): array
    {
        return $this->types;
    }
}
