<?php

namespace App\Support;

use DateTimeInterface;

/**
 * Shared attendance/work-hours math. Extracted out of
 * AttendanceController::scan() so it can be unit tested without needing a
 * database connection or a live HTTP request.
 */
class WorkHoursCalculator
{
    /**
     * Hours worked between check-in and check-out, as a float (e.g. 8.5
     * hours). Uses total elapsed seconds rather than DateInterval's %h
     * (which only holds the 0-23 remainder and silently drops full
     * day(s) for sessions >= 24h - this bit the original inline version).
     */
    public static function hoursBetween(DateTimeInterface $checkIn, DateTimeInterface $checkOut): float
    {
        return ($checkOut->getTimestamp() - $checkIn->getTimestamp()) / 3600;
    }

    /**
     * True once at least $minutes have elapsed since $since. Used for the
     * "wait 5 minutes before you can scan again" check-in/check-out
     * cooldown rule.
     */
    public static function minutesHaveElapsed(DateTimeInterface $since, DateTimeInterface $now, int $minutes): bool
    {
        return ($now->getTimestamp() - $since->getTimestamp()) >= ($minutes * 60);
    }

    /**
     * True once at least $hours have elapsed since $since. Used for the
     * "auto-ban after N hours without checking out" rule.
     */
    public static function hoursHaveElapsed(DateTimeInterface $since, DateTimeInterface $now, float $hours): bool
    {
        return ($now->getTimestamp() - $since->getTimestamp()) >= ($hours * 3600);
    }
}
