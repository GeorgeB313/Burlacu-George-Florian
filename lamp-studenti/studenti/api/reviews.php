<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

try {
    $pdo = db();
    $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

    if ($method === 'GET') {
        $movieId = isset($_GET['movie_id']) ? (int) $_GET['movie_id'] : 0;
        $tmdbId = isset($_GET['tmdb_id']) ? (int) $_GET['tmdb_id'] : 0;

        if ($movieId <= 0 && $tmdbId <= 0) {
            json_response(['error' => 'Missing movie_id or tmdb_id'], 400);
        }

        if ($movieId <= 0 && $tmdbId > 0) {
            $lookup = $pdo->prepare('SELECT id FROM movies WHERE tmdb_id = :tmdb_id LIMIT 1');
            $lookup->execute(['tmdb_id' => $tmdbId]);
            $movieId = (int) $lookup->fetchColumn();
        }

        if ($movieId <= 0) {
            json_response(['data' => []]);
        }

        $stmt = $pdo->prepare(
            'SELECT r.id, r.movie_id, r.user_id, r.rating, r.content, r.created_at, u.username
             FROM reviews r
             INNER JOIN users u ON u.id = r.user_id
             WHERE r.movie_id = :movie_id
             ORDER BY r.created_at DESC
             LIMIT 100'
        );
        $stmt->execute(['movie_id' => $movieId]);
        $reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);

        json_response(['data' => $reviews]);
    }

    if ($method === 'POST') {
        $userId = require_auth($pdo);

        $raw = file_get_contents('php://input');
        $body = json_decode($raw, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $body = $_POST;
        }
        if (!is_array($body)) {
            $body = [];
        }

        $movieId = isset($body['movie_id']) ? (int) $body['movie_id'] : 0;
        $tmdbId = isset($body['tmdb_id']) ? (int) $body['tmdb_id'] : 0;
        $rating = isset($body['rating']) ? (int) $body['rating'] : 0;
        $content = trim((string) ($body['content'] ?? ''));
        $title = trim((string) ($body['title'] ?? ''));
        $category = ($body['category'] ?? 'movie') === 'series' ? 'series' : 'movie';
        $releaseYear = isset($body['release_year']) ? (int) $body['release_year'] : null;
        $overview = trim((string) ($body['overview'] ?? ''));

        if ($movieId <= 0 && $tmdbId <= 0) {
            json_response(['error' => 'movie_id or tmdb_id required'], 422);
        }

        if ($rating < 1 || $rating > 10) {
            json_response(['error' => 'Rating invalid (1-10).'], 422);
        }

        if (mb_strlen($content) < 2) {
            json_response(['error' => 'Review prea scurt.'], 422);
        }

        if ($movieId > 0) {
            $movieExists = $pdo->prepare('SELECT 1 FROM movies WHERE id = :id LIMIT 1');
            $movieExists->execute(['id' => $movieId]);
            if (!$movieExists->fetchColumn()) {
                json_response(['error' => 'Filmul nu există în catalog.'], 404);
            }
        } else {
            $lookup = $pdo->prepare('SELECT id FROM movies WHERE tmdb_id = :tmdb_id LIMIT 1');
            $lookup->execute(['tmdb_id' => $tmdbId]);
            $movieId = (int) $lookup->fetchColumn();

            if ($movieId <= 0) {
                if ($title === '') {
                    json_response(['error' => 'Titlul este necesar pentru a salva review-ul.'], 422);
                }

                $insertMovie = $pdo->prepare(
                    'INSERT INTO movies (tmdb_id, title, original_title, overview, category, status, source, release_year)
                     VALUES (:tmdb_id, :title, :original_title, :overview, :category, :status, :source, :release_year)'
                );
                $insertMovie->execute([
                    'tmdb_id' => $tmdbId ?: null,
                    'title' => $title,
                    'original_title' => $title,
                    'overview' => $overview ?: null,
                    'category' => $category,
                    'status' => 'published',
                    'source' => 'tmdb',
                    'release_year' => $releaseYear ?: null,
                ]);
                $movieId = (int) $pdo->lastInsertId();
            }
        }

        $stmt = $pdo->prepare(
            'INSERT INTO reviews (movie_id, user_id, rating, content)
             VALUES (:movie_id, :user_id, :rating, :content)
             ON DUPLICATE KEY UPDATE rating = VALUES(rating), content = VALUES(content), updated_at = CURRENT_TIMESTAMP'
        );

        $stmt->execute([
            'movie_id' => $movieId,
            'user_id' => $userId,
            'rating' => $rating,
            'content' => $content,
        ]);

        json_response(['success' => true, 'message' => 'Review salvat.']);
    }

    json_response(['error' => 'Method Not Allowed'], 405);
} catch (Throwable $e) {
    json_response(['error' => 'Eroare API reviews: ' . $e->getMessage()], 500);
}
