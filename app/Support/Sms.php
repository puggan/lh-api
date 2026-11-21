<?php

namespace App\Support;

/**
 * Keeps SMS texts within one message: 160 characters, in the GSM 03.38 (GSM-7) alphabet.
 *
 * A single character outside that alphabet makes the whole message UCS-2, where only 70 characters fit,
 * and the extension characters (€ [ ] { } ~ ^ | \ and form feed) count as two.
 */
class Sms
{
    public const MAX_LENGTH = 160;

    /** GSM 03.38 basic character set (å, ä, ö, Å, Ä, Ö, é and ü included). */
    private const BASIC = "@£\$¥èéùìòÇ\nØø\rÅåΔ_ΦΓΛΩΠΨΣΘΞÆæßÉ !\"#¤%&'()*+,-./0123456789:;<=>?"
        . '¡ABCDEFGHIJKLMNOPQRSTUVWXYZÄÖÑÜ§¿abcdefghijklmnopqrstuvwxyzäöñüà';

    /** GSM 03.38 extension table: each counts as two characters. */
    private const EXTENDED = "^{}\\[~]|€\f";

    /** The length in GSM-7 characters, or null if the text needs UCS-2 (at most 70 per SMS). */
    public static function length(string $text): ?int
    {
        $length = 0;
        foreach (mb_str_split($text) as $char) {
            if (str_contains(self::BASIC, $char)) {
                $length++;
            } elseif (str_contains(self::EXTENDED, $char)) {
                $length += 2;
            } else {
                return null;
            }
        }
        return $length;
    }

    /** Whether the text fits in one SMS: GSM-7 and at most 160 characters. */
    public static function fits(string $text): bool
    {
        $length = self::length($text);
        return $length !== null && $length <= self::MAX_LENGTH;
    }

    /**
     * $before . $variable . $after, with $variable (e.g. a name typed by the booker) made GSM-7
     * and shortened with ".." so the whole text fits in one SMS. $before and $after are kept as they are.
     */
    public static function fit(string $before, string $variable, string $after): string
    {
        $variable = self::toGsm($variable);
        $room = self::MAX_LENGTH - (self::length($before . $after) ?? self::MAX_LENGTH);
        if (self::length($variable) <= $room) {
            return $before . $variable . $after;
        }
        $chars = mb_str_split($variable);
        while ($chars && self::length(implode('', $chars) . '..') > $room) {
            array_pop($chars);
        }
        return $before . rtrim(implode('', $chars)) . '..' . $after;
    }

    /** Replaces characters outside GSM-7: transliterated if possible (Ł -> L), otherwise "?". */
    public static function toGsm(string $text): string
    {
        $result = '';
        foreach (mb_str_split($text) as $char) {
            if (self::length($char) !== null) {
                $result .= $char;
                continue;
            }
            $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT', $char);
            $result .= ($ascii !== false && $ascii !== '' && self::length($ascii) !== null) ? $ascii : '?';
        }
        return $result;
    }
}
