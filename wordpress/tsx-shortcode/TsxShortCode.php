<?php

namespace Puggan\TsxShortCode;

class TsxShortCode
{
    private static array $availibleAttributes = [
        'src' => null,
        'id' => null,
    ];
    public static function init(): void
    {
        /** @see self::shortcode */
        add_shortcode('TSX', [self::class, 'shortcode']);
        /** @see self::gutenbergInit */
        add_action('init', [self::class, 'gutenbergInit']);
    }

    public static function jsxLibOnce(): string
    {
        static $once = false;
        if ($once) {
            return '';
        }
        $once = true;
        return '<script src="/ts/jsx.js"></script>' . PHP_EOL;
    }

    public static function shortcode($rawAttributes): string
    {
        $attributes = shortcode_atts(self::$availibleAttributes, $rawAttributes);
        if (empty($attributes['src'])) {
            if (empty($attributes['id'])) {
                return '<script data-tsx=""></script>';
            }
            return '<script data-tsx="" id="' . htmlentities($attributes['id'], ENT_QUOTES) . '"></script>';
        }

        if (str_ends_with($attributes['src'], '.ts')) {
            $filename = substr($attributes['src'], 0, -2) . 'js';
        } elseif (str_ends_with($attributes['src'], '.tsx')) {
            $filename = substr($attributes['src'], 0, -3) . 'js';
        } elseif (str_ends_with($attributes['src'], '.jsx')) {
            $filename = substr($attributes['src'], 0, -3) . 'js';
        } elseif (str_ends_with($attributes['src'], '.js')) {
            $filename = $attributes['src'];
        } elseif (str_contains($attributes['src'], '.')) {
            return '<!-- TsxShortCode error, unknown file format: ' . htmlentities($attributes['src']) . ' -->';
        } else {
            $filename = $attributes['src'] . '.js';
        }

        $scriptAttributes = [
            'id="' . empty($attributes['id']) ? null : 'id="' . htmlentities(empty($attributes['id']), ENT_QUOTES) . '"',
            'data-tsx="' . htmlentities(basename($filename, '.js'), ENT_QUOTES) . '"',
            'src="/ts/' . htmlentities($filename, ENT_QUOTES) . '"',
        ];

        return self::jsxLibOnce() . '<script ' . implode(' ', $scriptAttributes) . '></script>' . PHP_EOL;
    }

    public static function gutenbergInit(): void
    {
        $blockType = register_block_type(__DIR__ . '/build/tsx');
        $blockType->render_callback = [self::class, 'shortcode'];
    }
}
