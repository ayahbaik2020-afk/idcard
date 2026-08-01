<?php

namespace Tests\Support;

use App\Support\WorkHoursCalculator;
use DateTime;
use PHPUnit\Framework\TestCase;

class WorkHoursCalculatorTest extends TestCase
{
    public function testHoursBetweenSimpleShift(): void
    {
        $checkIn = new DateTime('2026-07-31 08:00:00');
        $checkOut = new DateTime('2026-07-31 16:30:00');

        $this->assertSame(8.5, WorkHoursCalculator::hoursBetween($checkIn, $checkOut));
    }

    public function testHoursBetweenHandlesSessionsOver24Hours(): void
    {
        // This is exactly the bug the original inline DateInterval::%h
        // version had: %h is the 0-23 remainder only, so a 30-hour session
        // would have been reported as "6 hours" instead of 30. Guard
        // against that regression here.
        $checkIn = new DateTime('2026-07-30 08:00:00');
        $checkOut = new DateTime('2026-07-31 14:00:00'); // 30 hours later

        $this->assertSame(30.0, WorkHoursCalculator::hoursBetween($checkIn, $checkOut));
    }

    public function testMinutesHaveElapsedIsFalseBeforeThreshold(): void
    {
        $since = new DateTime('2026-07-31 08:00:00');
        $now = new DateTime('2026-07-31 08:04:59');

        $this->assertFalse(WorkHoursCalculator::minutesHaveElapsed($since, $now, 5));
    }

    public function testMinutesHaveElapsedIsTrueAtExactThreshold(): void
    {
        $since = new DateTime('2026-07-31 08:00:00');
        $now = new DateTime('2026-07-31 08:05:00');

        $this->assertTrue(WorkHoursCalculator::minutesHaveElapsed($since, $now, 5));
    }

    public function testHoursHaveElapsedForAutoBanRule(): void
    {
        $since = new DateTime('2026-07-31 08:00:00');
        $justUnder = new DateTime('2026-07-31 17:59:59');
        $atThreshold = new DateTime('2026-07-31 18:00:00');

        $this->assertFalse(WorkHoursCalculator::hoursHaveElapsed($since, $justUnder, 10));
        $this->assertTrue(WorkHoursCalculator::hoursHaveElapsed($since, $atThreshold, 10));
    }
}
