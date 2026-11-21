<?php

namespace Tests\Unit;

use App\Support\ICalendar;
use PHPUnit\Framework\TestCase;

class ICalendarTest extends TestCase
{
    public function test_escape(): void
    {
        $this->assertSame('a\\\\b\;c\\,d\\ne', ICalendar::escape("a\\b;c,d\ne"));
    }

    public function test_fold_keeps_lines_within_75_octets_and_utf8_intact(): void
    {
        $line = 'SUMMARY:' . str_repeat('åäö', 40);
        $folded = ICalendar::fold($line);

        foreach (explode("\r\n", $folded) as $part) {
            $this->assertLessThanOrEqual(75, strlen($part));
            $this->assertTrue(mb_check_encoding($part, 'UTF-8'), 'no split characters');
        }
        $this->assertSame($line, str_replace("\r\n ", '', $folded));
    }

    public function test_all_day_event_end_is_exclusive(): void
    {
        $calendar = new ICalendar('Test');
        $calendar->addAllDayEvent('x@test', '2026-12-31', '2027-01-01', 'Ledig');
        $ics = $calendar->render();

        $this->assertStringStartsWith("BEGIN:VCALENDAR\r\n", $ics);
        $this->assertStringEndsWith("END:VCALENDAR\r\n", $ics);
        $this->assertStringContainsString("DTSTART;VALUE=DATE:20261231\r\n", $ics);
        $this->assertStringContainsString("DTEND;VALUE=DATE:20270102\r\n", $ics);
    }
}
