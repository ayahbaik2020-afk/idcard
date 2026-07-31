<?php

namespace App\Support;

/**
 * Small pagination math helper. Extracted out of ContractorRepository /
 * AttendanceController, which each computed offset/total_pages inline
 * with their own copy of the same formula.
 */
class Paginator
{
    public static function offset(int $page, int $perPage): int
    {
        $page = max(1, $page);
        $perPage = max(1, $perPage);
        return ($page - 1) * $perPage;
    }

    public static function totalPages(int $total, int $perPage): int
    {
        $perPage = max(1, $perPage);
        return max(1, (int) ceil($total / $perPage));
    }

    /**
     * Builds the full pagination metadata array used by templates/partials/pagination.php.
     */
    public static function meta(int $total, int $page, int $perPage): array
    {
        $page = max(1, $page);
        return [
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => self::totalPages($total, $perPage),
        ];
    }
}
