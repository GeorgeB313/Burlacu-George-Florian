<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$pdo = db();
$currentUserId = current_user_id($pdo);
$scope = $_GET['scope'] ?? 'trending';
$limit = (int) ($_GET['limit'] ?? 12);
$limit = max(1, min(50, $limit));
$type = $_GET['type'] ?? null;

$select = 'm.id, m.tmdb_id, m.imdb_id, m.title, m.original_title, m.category, m.overview, m.release_year, m.genres, m.runtime, m.rating_average, m.vote_count, m.poster_url, m.accent_color';
if ($currentUserId) {
    $select .= ', CASE WHEN w.user_id IS NULL THEN 0 ELSE 1 END AS in_watchlist';
}

$sql = "SELECT {$select} FROM movies m";
if ($currentUserId) {
    $sql .= ' LEFT JOIN watchlist_items w ON w.movie_id = m.id AND w.user_id = :userId';
}
$sql .= " WHERE m.status = 'published'";
$params = [];
$orderBy = 'm.rating_average DESC, m.vote_count DESC';

if ($type && in_array($type, ['movie', 'series'], true)) {
    $sql .= ' AND m.category = :category';
    $params['category'] = $type;
}

switch ($scope) {
    case 'latest':
        $orderBy = 'm.release_date DESC, m.id DESC';
        break;
    case 'alphabetical':
        $orderBy = 'm.title ASC';
        break;
    default:
        // trending/top-rated share same default ordering
        break;
}

$sql .= " ORDER BY {$orderBy} LIMIT :limit";

$stmt = $pdo->prepare($sql);
if ($currentUserId) {
    $stmt->bindValue(':userId', $currentUserId, PDO::PARAM_INT);
}
foreach ($params as $key => $value) {
    $stmt->bindValue(':' . $key, $value);
}
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->execute();

$movies = $stmt->fetchAll();

json_response(['data' => $movies]);
