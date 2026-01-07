<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

require_method('POST');

$pdo = db();
$adminId = require_admin($pdo);

function tmdb_api_key(): string
{
    $apiKey = getenv('TMDB_API_KEY');
    if (!$apiKey) {
        throw new RuntimeException('Lipsește TMDB_API_KEY pe server.');
    }
    return $apiKey;
}

function tmdb_search_movie(string $title, ?int $year): ?array
{
    $params = [
        'api_key' => tmdb_api_key(),
        'query' => $title,
        'include_adult' => 'false',
    ];
    if ($year) {
        $params['year'] = $year;
    }

    $url = 'https://api.themoviedb.org/3/search/movie?' . http_build_query($params);
    $body = tmdb_http_get($url);

    $json = json_decode($body, true);
    if (!is_array($json)) {
        throw new RuntimeException('Răspuns TMDb invalid');
    }

    $results = $json['results'] ?? [];
    if (empty($results)) {
        return null;
    }

    return $results[0];
}

function tmdb_http_get(string $url): string
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
    ]);
    $body = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $err = $body === false ? curl_error($ch) : null;
    curl_close($ch);

    if ($body === false) {
        throw new RuntimeException($err ?: 'Eroare la apel TMDb');
    }
    if ($status >= 400) {
        throw new RuntimeException('TMDb a răspuns cu HTTP ' . $status);
    }

    return (string) $body;
}

function tmdb_fetch_details(int $tmdbId): array
{
    $params = [
        'api_key' => tmdb_api_key(),
        'append_to_response' => 'external_ids',
        'language' => 'ro-RO',
    ];
    $url = 'https://api.themoviedb.org/3/movie/' . $tmdbId . '?' . http_build_query($params);
    $body = tmdb_http_get($url);
    $json = json_decode($body, true);
    if (!is_array($json) || empty($json['id'])) {
        throw new RuntimeException('Detalii TMDb invalide');
    }

    // fallback en-US dacă lipsesc overview/title locale
    if (empty($json['overview']) || empty($json['title'])) {
        $params['language'] = 'en-US';
        $url = 'https://api.themoviedb.org/3/movie/' . $tmdbId . '?' . http_build_query($params);
        $fallbackBody = tmdb_http_get($url);
        $fallback = json_decode($fallbackBody, true);
        if (is_array($fallback) && !empty($fallback['id'])) {
            $json = array_merge($fallback, $json); // păstrează câmpurile locale existente
        }
    }

    return $json;
}

$payload = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $payload['action'] ?? '';
$movieId = isset($payload['movieId']) ? (int) $payload['movieId'] : 0;

if ($movieId <= 0) {
    json_response(['success' => false, 'error' => 'ID film invalid'], 422);
}

if (!in_array($action, ['approve', 'reject'], true)) {
    json_response(['success' => false, 'error' => 'Acțiune necunoscută'], 422);
}

try {
    $pdo->beginTransaction();

    $movieStmt = $pdo->prepare('SELECT status, title, release_year FROM movies WHERE id = :id FOR UPDATE');
    $movieStmt->execute(['id' => $movieId]);
    $movie = $movieStmt->fetch(PDO::FETCH_ASSOC);

    if (!$movie) {
        $pdo->rollBack();
        json_response(['success' => false, 'error' => 'Filmul nu există'], 404);
    }

    if ($movie['status'] !== 'pending') {
        $pdo->rollBack();
        json_response(['success' => false, 'error' => 'Doar titlurile în așteptare pot fi gestionate'], 409);
    }

    if ($action === 'approve') {
        // Caută în TMDb și completează metadatele înainte de publicare; dacă nu există, șterge titlul
        $search = tmdb_search_movie($movie['title'] ?? '', $movie['release_year'] ? (int) $movie['release_year'] : null);
        if (!$search) {
            $pdo->prepare('DELETE FROM movies WHERE id = :id')->execute(['id' => $movieId]);
            $pendingCount = (int) $pdo->query("SELECT COUNT(*) FROM movies WHERE status = 'pending'")->fetchColumn();
            $totalCount = (int) $pdo->query('SELECT COUNT(*) FROM movies')->fetchColumn();
            $pdo->commit();
            json_response([
                'success' => true,
                'movieId' => $movieId,
                'deleted' => true,
                'message' => 'Titlul nu a fost găsit în TMDb și a fost șters.',
                'stats' => [
                    'pending' => $pendingCount,
                    'total' => $totalCount,
                ],
            ]);
        }

        $details = tmdb_fetch_details((int) $search['id']);
        $releaseDate = $details['release_date'] ?? null;
        $releaseYear = $releaseDate ? (int) substr($releaseDate, 0, 4) : ($movie['release_year'] ?? null);
        $genres = isset($details['genres']) ? implode(', ', array_map(static fn($g) => $g['name'], $details['genres'])) : null;
        $runtime = $details['runtime'] ?? null;
        $rating = isset($details['vote_average']) ? round((float) $details['vote_average'], 1) : null;
        $voteCount = $details['vote_count'] ?? null;
        $poster = !empty($details['poster_path']) ? 'https://image.tmdb.org/t/p/w500' . $details['poster_path'] : null;
        $backdrop = !empty($details['backdrop_path']) ? 'https://image.tmdb.org/t/p/w1280' . $details['backdrop_path'] : null;
        $imdbId = $details['imdb_id'] ?? ($details['external_ids']['imdb_id'] ?? null);

        $updateStmt = $pdo->prepare(
            "UPDATE movies SET
                status = 'published',
                approved_by = :adminId,
                updated_at = NOW(),
                tmdb_id = :tmdb_id,
                imdb_id = :imdb_id,
                original_title = :original_title,
                overview = :overview,
                release_date = :release_date,
                release_year = :release_year,
                genres = :genres,
                runtime = :runtime,
                rating_average = :rating_average,
                vote_count = :vote_count,
                poster_url = :poster_url,
                backdrop_url = :backdrop_url,
                source = 'tmdb'
             WHERE id = :id"
        );

        $updateStmt->execute([
            'adminId' => $adminId,
            'tmdb_id' => $details['id'] ?? null,
            'imdb_id' => $imdbId,
            'original_title' => $details['original_title'] ?? $movie['title'],
            'overview' => $details['overview'] ?? null,
            'release_date' => $releaseDate,
            'release_year' => $releaseYear,
            'genres' => $genres,
            'runtime' => $runtime,
            'rating_average' => $rating,
            'vote_count' => $voteCount,
            'poster_url' => $poster,
            'backdrop_url' => $backdrop,
            'id' => $movieId,
        ]);
    } else {
        $updateStmt = $pdo->prepare("UPDATE movies SET status = 'archived', approved_by = :adminId, updated_at = NOW() WHERE id = :id");
        $updateStmt->execute([
            'adminId' => $adminId,
            'id' => $movieId,
        ]);
    }

    $pdo->commit();

    $pendingCount = (int) $pdo->query("SELECT COUNT(*) FROM movies WHERE status = 'pending'")->fetchColumn();
    $totalCount = (int) $pdo->query('SELECT COUNT(*) FROM movies')->fetchColumn();

    json_response([
        'success' => true,
        'movieId' => $movieId,
        'newStatus' => $action === 'approve' ? 'published' : 'archived',
        'stats' => [
            'pending' => $pendingCount,
            'total' => $totalCount,
        ],
    ]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    json_response(['success' => false, 'error' => 'Acțiunea nu a putut fi finalizată: ' . $e->getMessage()], 500);
}
