<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

require_method('POST');

try {
    $pdo = db();
    $userId = current_user_id($pdo);
    if (!$userId) {
        json_response(['error' => 'Trebuie să fii autentificat pentru a propune un titlu.'], 401);
    }

    $raw = file_get_contents('php://input');
    $body = json_decode($raw, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        // Dacă nu e JSON valid, încercăm clasic form-data
        $body = $_POST;
    }
    if (!is_array($body)) {
        $body = [];
    }

    $title = trim($body['title'] ?? '');
    $description = trim($body['description'] ?? '');
    $director = trim($body['director'] ?? '');
    $year = isset($body['year']) ? (int) $body['year'] : null;
    $rating = isset($body['rating']) ? (float) $body['rating'] : null;

    if (strlen($title) < 2) {
        json_response(['error' => 'Titlul este prea scurt.'], 422);
    }
    if ($year !== null && ($year < 1900 || $year > 2099)) {
        json_response(['error' => 'An invalid (1900-2099).'], 422);
    }
    if ($rating !== null && ($rating < 0 || $rating > 10)) {
        json_response(['error' => 'Rating invalid.'], 422);
    }

    $overview = $description;
    if ($director) {
        $overview = $director . ' — ' . $overview;
    }

    // Evităm duplicate simple: același titlu și an în pending/published
    $dupStmt = $pdo->prepare('SELECT id FROM movies WHERE title = :title AND (release_year = :year_exact OR (:year_null IS NULL AND release_year IS NULL)) LIMIT 1');
    $dupStmt->execute([
        'title' => $title,
        'year_exact' => $year,
        'year_null' => $year,
    ]);
    
    if ($dupStmt->fetchColumn()) {
        json_response(['error' => 'Există deja un titlu cu acest nume/an.'], 409);
    }

    $releaseDate = $year ? sprintf('%d-01-01', $year) : null;

    $stmt = $pdo->prepare(
        "INSERT INTO movies (title, original_title, overview, category, status, source, submitted_by, release_year, rating_average)
         VALUES (:title, :original_title, :overview, 'movie', 'pending', 'manual', :submitted_by, :release_year, :rating_average)"
    );

    $stmt->execute([
        'title' => $title,
        'original_title' => $title,
        'overview' => $overview,
        'submitted_by' => $userId,
        'release_year' => $year,
        'rating_average' => $rating ?: null,
    ]);

    json_response([
        'success' => true,
        'message' => 'Propunerea a fost salvată și așteaptă aprobarea.',
    ]);
} catch (Throwable $e) {
    json_response([
        'error' => 'Eroare la salvarea propunerii: ' . $e->getMessage(),
    ], 500);
}
