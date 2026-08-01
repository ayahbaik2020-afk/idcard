<?php

namespace Tests\Support;

use App\Support\Paginator;
use PHPUnit\Framework\TestCase;

class PaginatorTest extends TestCase
{
    public function testOffsetForFirstPage(): void
    {
        $this->assertSame(0, Paginator::offset(1, 50));
    }

    public function testOffsetForLaterPage(): void
    {
        $this->assertSame(100, Paginator::offset(3, 50));
    }

    public function testOffsetClampsPageBelowOne(): void
    {
        // Someone hand-editing ?pg=0 or ?pg=-5 in the URL shouldn't be
        // able to request a negative offset.
        $this->assertSame(0, Paginator::offset(0, 50));
        $this->assertSame(0, Paginator::offset(-5, 50));
    }

    public function testTotalPagesRoundsUp(): void
    {
        // 101 rows at 50/page needs 3 pages, not 2.
        $this->assertSame(3, Paginator::totalPages(101, 50));
    }

    public function testTotalPagesIsAtLeastOneWhenEmpty(): void
    {
        // An empty result set should still report 1 page (page 1 of 1,
        // showing "no data"), not 0 pages, which would confuse the
        // "Previous/Next" UI.
        $this->assertSame(1, Paginator::totalPages(0, 50));
    }

    public function testMetaReturnsFullShape(): void
    {
        $meta = Paginator::meta(101, 2, 50);

        $this->assertSame([
            'total' => 101,
            'page' => 2,
            'per_page' => 50,
            'total_pages' => 3,
        ], $meta);
    }

    public function testMetaClampsPageBelowOne(): void
    {
        $meta = Paginator::meta(10, 0, 50);
        $this->assertSame(1, $meta['page']);
    }
}
