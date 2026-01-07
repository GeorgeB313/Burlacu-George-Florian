<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

require_method('GET');

$pdo = db();
$query = trim($_GET['q'] ?? '');

if ($query === '') {
    json_response(['data' => []]);
}

$sql = "SELECT id, tmdb_id, imdb_id, title, release_year, genres, rating_average, accent_color, category, poster_url
                FROM movies
                WHERE status = 'published'
                    AND (title LIKE :titleTerm OR original_title LIKE :originalTerm OR genres LIKE :genreTerm)
                ORDER BY rating_average DESC
                LIMIT 10";
$stmt = $pdo->prepare($sql);
$term = '%' . $query . '%';
$stmt->execute([
        'titleTerm' => $term,
        'originalTerm' => $term,
        'genreTerm' => $term,
]);

json_response(['data' => $stmt->fetchAll()]);
