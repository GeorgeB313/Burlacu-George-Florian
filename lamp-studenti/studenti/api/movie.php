<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

require_method('GET');

$pdo = db();
$id = isset($_GET['id']) ? (int) $_GET['id'] : null;
$tmdbId = isset($_GET['tmdb_id']) ? (int) $_GET['tmdb_id'] : null;
$imdbId = $_GET['imdb_id'] ?? null;

if (!$id && !$tmdbId && !$imdbId) {
    json_response(['error' => 'Missing identifier'], 400);
}

$sql = "SELECT id, tmdb_id, imdb_id, title, original_title, category, overview, release_date, release_year, genres, runtime, rating_average, vote_count, poster_url, backdrop_url, accent_color, status FROM movies WHERE status IN ('published','pending')";
$params = [];

if ($id) {
    $sql .= ' AND id = :id';
    $params['id'] = $id;
} elseif ($tmdbId) {
    $sql .= ' AND tmdb_id = :tmdb';
    $params['tmdb'] = $tmdbId;
} else {
    $sql .= ' AND imdb_id = :imdb';
    $params['imdb'] = $imdbId;
}

$sql .= ' LIMIT 1';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$movie = $stmt->fetch();

if (!$movie) {
    json_response(['error' => 'Movie not found'], 404);
}

$userId = current_user_id($pdo);
if ($userId) {
    $watchStmt = $pdo->prepare('SELECT 1 FROM watchlist_items WHERE user_id = :user AND movie_id = :movie LIMIT 1');
    $watchStmt->execute(['user' => $userId, 'movie' => $movie['id']]);
    $movie['in_watchlist'] = (bool) $watchStmt->fetchColumn();
} else {
    $movie['in_watchlist'] = false;
}

json_response(['data' => $movie]);
