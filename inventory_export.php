<?php
require_once __DIR__ . '/simple_auth/middleware.php';
require_once 'inventory_mysql.php';

$schema = require __DIR__ . '/inventory_schema.php';
$items = fetch_inventory_mysql($schema);

$query = trim((string) ($_GET['q'] ?? ''));
$statusFilter = trim((string) ($_GET['status'] ?? ''));
$sortBy = trim((string) ($_GET['sort'] ?? 'item_name'));
$sortDir = strtolower(trim((string) ($_GET['dir'] ?? 'asc')));

$allowedSort = ['item_id', 'item_name', 'category', 'supplier_name', 'quantity_in_stock', 'status', 'description', 'updated_at'];
if (!in_array($sortBy, $allowedSort, true)) {
    $sortBy = 'item_name';
}
if ($sortDir !== 'asc' && $sortDir !== 'desc') {
    $sortDir = 'asc';
}

$filtered = array_values(array_filter($items, function (array $item) use ($query, $statusFilter): bool {
    $status = trim((string) ($item['status'] ?? ''));
    if ($statusFilter !== '' && strcasecmp($status, $statusFilter) !== 0) {
        return false;
    }

    if ($query === '') {
        return true;
    }

    $needle = strtolower($query);
    $haystacks = [
        (string) ($item['item_id'] ?? ''),
        (string) ($item['item_name'] ?? ''),
        (string) ($item['category'] ?? ''),
        (string) ($item['brand'] ?? ''),
        (string) ($item['model'] ?? ''),
        (string) ($item['supplier_name'] ?? ''),
        (string) ($item['supplier_id'] ?? ''),
        (string) ($item['status'] ?? ''),
    ];

    foreach ($haystacks as $text) {
        if (strpos(strtolower($text), $needle) !== false) {
            return true;
        }
    }

    return false;
}));

usort($filtered, function (array $a, array $b) use ($sortBy, $sortDir): int {
    if ($sortBy === 'quantity_in_stock') {
        $left = is_numeric($a['quantity_in_stock'] ?? null) ? (float) $a['quantity_in_stock'] : 0.0;
        $right = is_numeric($b['quantity_in_stock'] ?? null) ? (float) $b['quantity_in_stock'] : 0.0;
        $cmp = $left <=> $right;
        return $sortDir === 'desc' ? -$cmp : $cmp;
    }

    $leftText = (string) ($a[$sortBy] ?? '');
    $rightText = (string) ($b[$sortBy] ?? '');
    $cmp = strcasecmp($leftText, $rightText);
    return $sortDir === 'desc' ? -$cmp : $cmp;
});

$filename = 'inventory_export_' . date('Ymd_His') . '.csv';
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename=' . $filename);

$output = fopen('php://output', 'w');
fputcsv($output, [
    'item_id', 'item_name', 'category', 'brand', 'model',
    'supplier_id', 'supplier_name', 'quantity_in_stock', 'reorder_level',
    'status', 'unit', 'cost_price', 'selling_price', 'location', 'description'
]);

foreach ($filtered as $item) {
    fputcsv($output, [
        (string) ($item['item_id'] ?? ''),
        (string) ($item['item_name'] ?? ''),
        (string) ($item['category'] ?? ''),
        (string) ($item['brand'] ?? ''),
        (string) ($item['model'] ?? ''),
        (string) ($item['supplier_id'] ?? ''),
        (string) ($item['supplier_name'] ?? ''),
        (string) ($item['quantity_in_stock'] ?? ''),
        (string) ($item['reorder_level'] ?? ''),
        (string) ($item['status'] ?? ''),
        (string) ($item['unit'] ?? ''),
        (string) ($item['cost_price'] ?? ''),
        (string) ($item['selling_price'] ?? ''),
        (string) ($item['location'] ?? ''),
        (string) ($item['description'] ?? ''),
    ]);
}

fclose($output);
exit;
