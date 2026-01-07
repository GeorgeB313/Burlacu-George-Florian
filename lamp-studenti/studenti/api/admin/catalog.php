<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

require_method('GET');

$pdo = db();
require_admin($pdo);

$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = (int) ($_GET['perPage'] ?? 50);
$perPage = max(10, min(200, $perPage));
$search = trim((string) ($_GET['search'] ?? ''));
$statusFilter = $_GET['status'] ?? null;
$categoryFilter = $_GET['category'] ?? null;

$where = [];
$params = [];

if ($search !== '') {
    $where[] = '(m.title LIKE :term_title OR m.original_title LIKE :term_original)';
    $likeValue = '%' . $search . '%';
    $params['term_title'] = $likeValue;
    $params['term_original'] = $likeValue;
}

if ($statusFilter && in_array($statusFilter, ['pending', 'published', 'archived'], true)) {
    $where[] = 'm.status = :status';
    $params['status'] = $statusFilter;
}

if ($categoryFilter && in_array($categoryFilter, ['movie', 'series'], true)) {
    $where[] = 'm.category = :category';
    $params['category'] = $categoryFilter;
}

$whereSql = $where ? (' WHERE ' . implode(' AND ', $where)) : '';

$totalStmt = $pdo->prepare("SELECT COUNT(*) FROM movies m{$whereSql}");
foreach ($params as $name => $value) {
    if (str_starts_with($name, 'term')) {
        $totalStmt->bindValue(':' . $name, $value, PDO::PARAM_STR);
    } else {
        $totalStmt->bindValue(':' . $name, $value);
    }
}
$totalStmt->execute();
$totalRows = (int) $totalStmt->fetchColumn();

$offset = ($page - 1) * $perPage;

$listSql = "
    SELECT m.id, m.title, m.original_title, m.category, m.status, m.release_year, m.rating_average, m.vote_count, m.created_at
    FROM movies m
    {$whereSql}
    ORDER BY m.id ASC
    LIMIT :limit OFFSET :offset
";

$listStmt = $pdo->prepare($listSql);
foreach ($params as $name => $value) {
    if (str_starts_with($name, 'term')) {
        $listStmt->bindValue(':' . $name, $value, PDO::PARAM_STR);
    } else {
        $listStmt->bindValue(':' . $name, $value);
    }
}
$listStmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$listStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$listStmt->execute();

$rows = $listStmt->fetchAll(PDO::FETCH_ASSOC);

json_response([
    'data' => $rows,
    'meta' => [
        'page' => $page,
        'perPage' => $perPage,
        'total' => $totalRows,
        'totalPages' => $perPage ? (int) ceil($totalRows / $perPage) : 0,
    ],
]);
