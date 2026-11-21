<?php

namespace App\Support;

use DateTimeImmutable;

/**
 * A minimal iCalendar (RFC 5545) feed with all-day events, for subscribing from Google Calendar etc.
 */
class ICalendar
{
    /** @var list<string> */
    private array $events = [];

    public function __construct(private readonly string $name)
    {
    }

    /**
     * An all-day event from $startDate to $endDate (both Y-m-d, inclusive).
     *
     * @param string $uid stable and unique, so calendar apps update the event instead of duplicating it
     */
    public function addAllDayEvent(string $uid, string $startDate, string $endDate, string $summary, string $description = ''): void
    {
        // DTEND of an all-day event is exclusive: the day after the last day.
        $end = (new DateTimeImmutable($endDate))->modify('+1 day');
        $lines = [
            'BEGIN:VEVENT',
            'UID:' . self::escape($uid),
            'DTSTAMP:' . gmdate('Ymd\THis\Z'),
            'DTSTART;VALUE=DATE:' . (new DateTimeImmutable($startDate))->format('Ymd'),
            'DTEND;VALUE=DATE:' . $end->format('Ymd'),
            'SUMMARY:' . self::escape($summary),
            'TRANSP:TRANSPARENT',
        ];
        if ($description !== '') {
            $lines[] = 'DESCRIPTION:' . self::escape($description);
        }
        $lines[] = 'END:VEVENT';
        $this->events[] = implode("\r\n", array_map(self::fold(...), $lines));
    }

    public function render(): string
    {
        $header = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//LH Höjden//lh-api//SV',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'X-WR-CALNAME:' . self::escape($this->name),
            'X-WR-TIMEZONE:Europe/Stockholm',
            // A hint to refresh hourly; Google decides itself (often every 12-24 hours).
            'REFRESH-INTERVAL;VALUE=DURATION:PT1H',
            'X-PUBLISHED-TTL:PT1H',
        ];
        return implode("\r\n", [...array_map(self::fold(...), $header), ...$this->events, 'END:VCALENDAR']) . "\r\n";
    }

    /** Escapes a TEXT value: backslash, semicolon, comma and newlines. */
    public static function escape(string $text): string
    {
        return str_replace(['\\', ';', ',', "\r\n", "\n", "\r"], ['\\\\', '\;', '\\,', '\\n', '\\n', '\\n'], $text);
    }

    /** Folds a content line at 75 octets, without splitting a UTF-8 character. */
    public static function fold(string $line): string
    {
        $folded = '';
        $limit = 75;
        while (strlen($line) > $limit) {
            $cut = $limit;
            // Don't cut inside a multi-byte character (continuation bytes are 10xxxxxx).
            while ($cut > 0 && (ord($line[$cut]) & 0xC0) === 0x80) {
                $cut--;
            }
            $folded .= substr($line, 0, $cut) . "\r\n ";
            $line = substr($line, $cut);
            $limit = 74; // continuation lines start with a space
        }
        return $folded . $line;
    }
}
