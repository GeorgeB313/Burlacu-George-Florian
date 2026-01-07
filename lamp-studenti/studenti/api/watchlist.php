<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$pdo = db();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$userId = require_auth($pdo);

switch ($method) {
    case 'GET':
        $stmt = $pdo->prepare(
                'SELECT w.id AS watchlist_id, w.status AS watch_status, w.created_at,
                    m.id AS movie_id, m.tmdb_id, m.imdb_id, m.title, m.release_year,
                    m.genres, m.rating_average, m.poster_url, m.accent_color, m.overview, m.category
             FROM watchlist_items w
             INNER JOIN movies m ON m.id = w.movie_id
             WHERE w.user_id = :user
             ORDER BY w.created_at DESC'
        );
        $stmt->execute(['user' => $userId]);
        json_response(['data' => $stmt->fetchAll()]);
    case 'POST':
        $payload = json_decode(file_get_contents('php://input'), true) ?: [];
        $movieId = isset($payload['movie_id']) ? (int) $payload['movie_id'] : 0;
        if ($movieId <= 0) {
            json_response(['error' => 'movie_id is required'], 400);
        }

        try {
            $stmt = $pdo->prepare('INSERT INTO watchlist_items (user_id, movie_id) VALUES (:user, :movie)');
            $stmt->execute(['user' => $userId, 'movie' => $movieId]);
        } catch (PDOException $e) {
            if ((int) $e->getCode() === 23000) {
                json_response(['message' => 'Film deja în watchlist'], 200);
            }
            throw $e;
        }

        json_response(['message' => 'Adăugat în watchlist']);
    case 'DELETE':
        $payload = json_decode(file_get_contents('php://input'), true) ?: [];
        $movieId = isset($payload['movie_id']) ? (int) $payload['movie_id'] : 0;
        if ($movieId <= 0) {
            json_response(['error' => 'movie_id is required'], 400);
        }

        $stmt = $pdo->prepare('DELETE FROM watchlist_items WHERE user_id = :user AND movie_id = :movie');
        $stmt->execute(['user' => $userId, 'movie' => $movieId]);
        json_response(['message' => 'Eliminat din watchlist']);
    default:
        json_response(['error' => 'Method Not Allowed'], 405);
}
