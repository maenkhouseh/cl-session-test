<?php

namespace App\Services;

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Color\Color;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Writer\SvgWriter;

class QrCodeService
{
    /** @return array{0: string, 1: string} [binary body, mime type] */
    public function render(string $data, int $size = 600, string $fg = '000000', string $bg = 'FFFFFF', string $level = 'M', string $format = 'svg'): array
    {
        $result = (new Builder(
            writer: $format === 'png' ? new PngWriter : new SvgWriter,
            data: $data,
            errorCorrectionLevel: match (strtoupper($level)) {
                'L' => ErrorCorrectionLevel::Low, 'Q' => ErrorCorrectionLevel::Quartile, 'H' => ErrorCorrectionLevel::High,
                default => ErrorCorrectionLevel::Medium,
            },
            size: $size,
            margin: 10,
            roundBlockSizeMode: RoundBlockSizeMode::Margin,
            foregroundColor: $this->color($fg),
            backgroundColor: $this->color($bg),
        ))->build();

        return [$result->getString(), $result->getMimeType()];
    }

    private function color(string $hex): Color
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = preg_replace('/(.)/', '$1$1', $hex);
        }

        return new Color(hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2)));
    }
}
