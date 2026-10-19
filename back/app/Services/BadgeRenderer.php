<?php

namespace App\Services;

class BadgeRenderer
{
    private const HEIGHT = 20;

    private const PADDING = 6;

    private const LABEL_COLOR = '#555';

    private const NARROW_CHARACTERS = 'fijlrtI1.,:;!|\' ';

    private const WIDE_CHARACTERS = 'mwMW%@';

    public function render(string $label, string $message, string $color): string
    {
        $labelWidth = $this->textWidth($label) + self::PADDING * 2;
        $messageWidth = $this->textWidth($message) + self::PADDING * 2;
        $width = $labelWidth + $messageWidth;
        $label = $this->escape($label);
        $message = $this->escape($message);
        $height = self::HEIGHT;
        $labelColor = self::LABEL_COLOR;

        return <<<SVG
            <svg xmlns="http://www.w3.org/2000/svg" width="{$width}" height="{$height}" role="img" aria-label="{$label}: {$message}">
              <title>{$label}: {$message}</title>
              <clipPath id="r"><rect width="{$width}" height="{$height}" rx="3" fill="#fff"/></clipPath>
              <g clip-path="url(#r)">
                <rect width="{$labelWidth}" height="{$height}" fill="{$labelColor}"/>
                <rect x="{$labelWidth}" width="{$messageWidth}" height="{$height}" fill="{$color}"/>
              </g>
              <g fill="#fff" text-anchor="middle" font-family="Verdana,Geneva,DejaVu Sans,sans-serif" font-size="11">
                <text x="{$this->center(0, $labelWidth)}" y="14">{$label}</text>
                <text x="{$this->center($labelWidth, $messageWidth)}" y="14">{$message}</text>
              </g>
            </svg>
            SVG;
    }

    private function textWidth(string $text): int
    {
        $width = 0.0;

        foreach (mb_str_split($text) as $character) {
            $width += match (true) {
                str_contains(self::NARROW_CHARACTERS, $character) => 3.5,
                str_contains(self::WIDE_CHARACTERS, $character) => 10.0,
                default => 7.0,
            };
        }

        return (int) ceil($width);
    }

    private function center(int $offset, int $width): string
    {
        return number_format($offset + $width / 2, 1, '.', '');
    }

    private function escape(string $text): string
    {
        return htmlspecialchars($text, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
