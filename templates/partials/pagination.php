<?php
/**
 * Reusable pagination control. Expects $pagination = ['page'=>, 'total_pages'=>, 'total'=>, 'per_page'=>]
 * to be set by the including template. Preserves all current filter/query
 * params (search, plant, company_id, etc.) when building page links -
 * only the `pg` param changes.
 */
if (empty($pagination) || (int) $pagination['total_pages'] <= 1) {
    return;
}

$currentPg = (int) $pagination['page'];
$totalPages = (int) $pagination['total_pages'];

if (!function_exists('pagination_url')) {
    function pagination_url($pg)
    {
        $params = $_GET;
        $params['pg'] = $pg;
        return 'index.php?' . http_build_query($params);
    }
}

// Show at most 7 page numbers: first, last, and a window around current.
$window = 2;
$pagesToShow = [];
for ($i = max(1, $currentPg - $window); $i <= min($totalPages, $currentPg + $window); $i++) {
    $pagesToShow[] = $i;
}
?>
<nav aria-label="Page navigation" class="mt-3">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="text-muted small">
            Menampilkan halaman <?php echo $currentPg; ?> dari <?php echo $totalPages; ?>
            (total <?php echo number_format($pagination['total'], 0, ',', '.'); ?> data)
        </div>
        <ul class="pagination mb-0">
            <li class="page-item <?php echo $currentPg <= 1 ? 'disabled' : ''; ?>">
                <a class="page-link" href="<?php echo pagination_url(max(1, $currentPg - 1)); ?>">&laquo; Sebelumnya</a>
            </li>
            <?php if (!in_array(1, $pagesToShow, true)): ?>
                <li class="page-item"><a class="page-link" href="<?php echo pagination_url(1); ?>">1</a></li>
                <?php if ($pagesToShow[0] > 2): ?><li class="page-item disabled"><span class="page-link">&hellip;</span></li><?php endif; ?>
            <?php endif; ?>
            <?php foreach ($pagesToShow as $p): ?>
                <li class="page-item <?php echo $p === $currentPg ? 'active' : ''; ?>">
                    <a class="page-link" href="<?php echo pagination_url($p); ?>"><?php echo $p; ?></a>
                </li>
            <?php endforeach; ?>
            <?php if (!in_array($totalPages, $pagesToShow, true)): ?>
                <?php if (end($pagesToShow) < $totalPages - 1): ?><li class="page-item disabled"><span class="page-link">&hellip;</span></li><?php endif; ?>
                <li class="page-item"><a class="page-link" href="<?php echo pagination_url($totalPages); ?>"><?php echo $totalPages; ?></a></li>
            <?php endif; ?>
            <li class="page-item <?php echo $currentPg >= $totalPages ? 'disabled' : ''; ?>">
                <a class="page-link" href="<?php echo pagination_url(min($totalPages, $currentPg + 1)); ?>">Berikutnya &raquo;</a>
            </li>
        </ul>
    </div>
</nav>
