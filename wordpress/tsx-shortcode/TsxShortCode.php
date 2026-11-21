<?php

namespace Puggan\TsxShortCode;

/**
 * [TSX src="calender"] (or the TSX block) loads /ts/calender.js, built by `npm run build` in lh-api,
 * plus /ts/jsx.js once per page. The script finds its own tag by data-tsx="calender"
 * and puts its content right after it.
 */
class TsxShortCode
{
    /** URL path and directory (relative to the site root, the parent of ABSPATH) of the built scripts. */
    private const SCRIPT_DIR = '/ts/';

    private static array $availableAttributes = [
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
        return '<script src="' . esc_url(self::scriptUrl('jsx.js')) . '"></script>' . PHP_EOL;
    }

    public static function shortcode($rawAttributes): string
    {
        $attributes = shortcode_atts(self::$availableAttributes, $rawAttributes);
        $idAttribute = empty($attributes['id']) ? '' : ' id="' . esc_attr($attributes['id']) . '"';

        if (empty($attributes['src'])) {
            return '<script data-tsx=""' . $idAttribute . '></script>';
        }

        // Only a plain file name in SCRIPT_DIR, so a post can't load any other script on the site.
        if (!preg_match('/^([A-Za-z0-9_-]+)(\.(ts|tsx|jsx|js))?$/', $attributes['src'], $match)) {
            return '<!-- TsxShortCode error, src must be a file name in ' . esc_html(self::SCRIPT_DIR) . ': ' . esc_html($attributes['src']) . ' -->';
        }
        $name = $match[1];

        return self::jsxLibOnce()
            . '<script' . $idAttribute
            . ' data-tsx="' . esc_attr($name) . '"'
            . ' src="' . esc_url(self::scriptUrl($name . '.js')) . '"></script>'
            . PHP_EOL;
    }

    public static function gutenbergInit(): void
    {
        register_block_type(__DIR__ . '/build/tsx', [
            'render_callback' => static fn (array $attributes): string => self::shortcode($attributes),
        ]);
    }

    /** The file's modification time as version, so browsers don't keep an old build. */
    private static function scriptUrl(string $file): string
    {
        $path = dirname(ABSPATH) . self::SCRIPT_DIR . $file;
        $version = is_file($path) ? filemtime($path) : 0;
        return self::SCRIPT_DIR . $file . '?ver=' . $version;
    }
}
