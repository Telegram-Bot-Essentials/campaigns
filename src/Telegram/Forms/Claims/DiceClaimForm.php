<?php

namespace TelegramBotEssentials\Campaigns\Telegram\Forms\Claims;

use Closure;
use TelegramBotEssentials\Campaigns\Claims\DiceClaim;
use TelegramBotEssentials\Campaigns\Telegram\Forms\ClaimMethodConfigForm;
use TelegramBotEssentials\Essence\Forms\Steps\Text;

class DiceClaimForm extends ClaimMethodConfigForm
{
    protected string $type = 'CAMPAIGN_CLAIM_DICE';

    protected string $lang = 'tbe-campaigns::claims.dice.wizard';

    protected function claimMethodKey(): string
    {
        return DiceClaim::KEY;
    }

    public function steps(): array
    {
        return [
            Text::make('numbers')->rules([$this->validNumbers()]),
            Text::make('tries')->rules([$this->validTries()]),
        ];
    }

    protected function config(array $answers): array
    {
        $numbers = is_string($answers['numbers'] ?? null) ? self::parse($answers['numbers']) : [];
        $tries = is_string($answers['tries'] ?? null) ? DiceClaim::latin($answers['tries']) : 1;

        return [
            'numbers' => $numbers,
            'tries' => DiceClaim::tries(['tries' => $tries]),
        ];
    }

    /**
     * "2, 4 ،6" or "۲،۴،۶" as [2, 4, 6].
     *
     * @return list<int>
     */
    private static function parse(string $answer): array
    {
        return DiceClaim::numbers(['numbers' => preg_split('/\s*,\s*/', trim(DiceClaim::latin($answer))) ?: []]);
    }

    /**
     * Numbers 1 to 6 separated by commas. All six would win on every throw,
     * which is a plain claim, not a game.
     */
    private function validNumbers(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) {
            $text = is_string($value) ? DiceClaim::latin($value) : '';

            if (preg_match('/^\s*[1-6](\s*,\s*[1-6])*\s*$/', $text) !== 1) {
                $fail($this->text('errors.numbers'));
            } elseif (count(self::parse($text)) === 6) {
                $fail($this->text('errors.everything'));
            }
        };
    }

    private function validTries(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) {
            $text = is_string($value) ? trim(DiceClaim::latin($value)) : '';

            if (preg_match('/^\d+$/', $text) !== 1 || (int) $text < 1 || (int) $text > DiceClaim::MAX_TRIES) {
                $fail($this->text('errors.tries', ['max' => (string) DiceClaim::MAX_TRIES]));
            }
        };
    }
}
