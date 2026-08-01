<?php

namespace Tests\Support;

use App\Support\IdCardNumberFormatter;
use PHPUnit\Framework\TestCase;

class IdCardNumberFormatterTest extends TestCase
{
    public function testFormatsFirstIdOfTheYear(): void
    {
        // No existing contractors this year yet (max id = 0) -> first
        // number issued should be 0001.
        $this->assertSame('26.0001', IdCardNumberFormatter::format('26', 0));
    }

    public function testIncrementsFromHighestExistingNumber(): void
    {
        $this->assertSame('26.0158', IdCardNumberFormatter::format('26', 157));
    }

    public function testPadsToFourDigits(): void
    {
        $this->assertSame('26.0007', IdCardNumberFormatter::format('26', 6));
    }

    public function testDoesNotTruncateBeyondFourDigits(): void
    {
        // A plant that somehow registers more than 9999 contractors in one
        // year should get a 5-digit number rather than silently wrapping
        // or truncating - str_pad only pads, it never cuts.
        $this->assertSame('26.10000', IdCardNumberFormatter::format('26', 9999));
    }

    public function testFormatForYearUsesGivenTimestamp(): void
    {
        $timestamp = strtotime('2027-01-15');
        $this->assertSame('27.0001', IdCardNumberFormatter::formatForYear(0, $timestamp));
    }
}
