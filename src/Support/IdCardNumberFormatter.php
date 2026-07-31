<?php

namespace App\Support;

/**
 * Formats the contractor ID Card number: 2-digit year prefix + a 4-digit
 * zero-padded sequence number that continues from the highest existing
 * number for that year (e.g. "26.0157"). Extracted out of
 * ContractorService::createContractor() so the format itself can be unit
 * tested without needing a database connection.
 */
class IdCardNumberFormatter
{
    public static function format(string $yearPrefix, int $maxExistingId): string
    {
        $nextId = $maxExistingId + 1;
        return $yearPrefix . '.' . str_pad((string) $nextId, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Convenience wrapper that derives the 2-digit year prefix from a
     * timestamp (defaults to now), matching date('y') used elsewhere in
     * the app.
     */
    public static function formatForYear(int $maxExistingId, ?int $timestamp = null): string
    {
        $yearPrefix = date('y', $timestamp ?? time());
        return self::format($yearPrefix, $maxExistingId);
    }
}
