<?php

namespace TelegramBotEssentials\Campaigns\Services;

use chillerlan\QRCode\Output\QRGdImagePNG;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use RuntimeException;

class CampaignQr
{
    /** A PNG of the QR code for the given link, as raw bytes. */
    public static function png(string $content, int $scale = 10): string
    {
        $options = new QROptions([
            'outputInterface' => QRGdImagePNG::class,
            'outputBase64' => false,
            'scale' => $scale,
        ]);

        $png = (new QRCode($options))->render($content);

        if (! is_string($png)) {
            throw new RuntimeException('The QR code could not be rendered.');
        }

        return $png;
    }
}
