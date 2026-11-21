<?php

namespace Tests\Unit;

use App\Support\Sms;
use PHPUnit\Framework\TestCase;

class SmsTest extends TestCase
{
    public function test_swedish_letters_are_gsm(): void
    {
        $this->assertSame(6, Sms::length('åäöÅÄÖ'));
        $this->assertSame(3, Sms::length('é€'), '€ counts as two');
        $this->assertNull(Sms::length('Łukasz'), 'Ł needs UCS-2');
        $this->assertNull(Sms::length('hej 😀'));
    }

    public function test_to_gsm_transliterates(): void
    {
        $this->assertSame('Lukasz Wojcik', Sms::toGsm('Łukasz Wójcik'));
        $this->assertSame('Åsa Öberg', Sms::toGsm('Åsa Öberg'));
    }

    public function test_fit_shortens_only_the_variable_part(): void
    {
        $before = 'Ny bokningsförfrågan för LH Lokalen 2026-12-05 från ';
        $after = ', lgh 466. Svara här: https://hgf-tuve.se/pr';

        $short = Sms::fit($before, 'Anna Andersson', $after);
        $this->assertSame($before . 'Anna Andersson' . $after, $short);

        $long = Sms::fit($before, str_repeat('Långt Namn ', 20), $after);
        $this->assertTrue(Sms::fits($long));
        $this->assertGreaterThanOrEqual(Sms::MAX_LENGTH - 1, Sms::length($long), 'uses the room it has');
        $this->assertStringStartsWith($before . 'Långt Namn', $long);
        $this->assertStringEndsWith('..' . $after, $long);

        $emoji = Sms::fit($before, 'Anna 😀 Łukasz', $after);
        $this->assertTrue(Sms::fits($emoji), 'no UCS-2 because of the name');
    }
}
