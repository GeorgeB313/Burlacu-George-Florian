#!/usr/bin/env php
<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

function tmdb_api_key(): string
{
    $apiKey = getenv('TMDB_API_KEY');
    if (!$apiKey) {
        fwrite(STDERR, "Lipsește TMDB_API_KEY în mediul containerului.\n");
        exit(1);
    }
    return $apiKey;
}

function tmdb_http_get(string $url): array
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
        throw new RuntimeException('TMDb HTTP ' . $status);
    }

    $json = json_decode((string) $body, true);
    if (!is_array($json)) {
        throw new RuntimeException('Răspuns TMDb invalid');
    }
    return $json;
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
    $json = tmdb_http_get($url);
    $results = $json['results'] ?? [];
    if (empty($results)) {
        return null;
    }
    return $results[0];
}

function tmdb_fetch_details(int $tmdbId): array
{
    $params = [
        'api_key' => tmdb_api_key(),
        'append_to_response' => 'external_ids',
        'language' => 'ro-RO',
    ];
    $url = 'https://api.themoviedb.org/3/movie/' . $tmdbId . '?' . http_build_query($params);
    $primary = tmdb_http_get($url);

    // fallback en-US dacă lipsesc overview/title
    if (empty($primary['overview'] ?? null) || empty($primary['title'] ?? null)) {
        $params['language'] = 'en-US';
        $url = 'https://api.themoviedb.org/3/movie/' . $tmdbId . '?' . http_build_query($params);
        $fallback = tmdb_http_get($url);
        if (is_array($fallback) && !empty($fallback)) {
            $primary = array_merge($fallback, $primary);
        }
    }

    return $primary;
}

function enrich_movie(PDO $pdo, array $movie): void
{
    $id = (int) $movie['id'];
    $title = (string) $movie['title'];
    $year = $movie['release_year'] ? (int) $movie['release_year'] : null;
    $tmdbId = $movie['tmdb_id'] ? (int) $movie['tmdb_id'] : null;

    // Fetch details either by existing tmdb_id or via search
    if ($tmdbId) {
        $details = tmdb_fetch_details($tmdbId);
    } else {
        $search = tmdb_search_movie($title, $year);
        if (!$search) {
            throw new RuntimeException('Nu a fost găsit în TMDb');
        }
        $details = tmdb_fetch_details((int) $search['id']);
    }

    $releaseDate = $details['release_date'] ?? null;
    $releaseYear = $releaseDate ? (int) substr($releaseDate, 0, 4) : ($year ?? null);
    $genres = isset($details['genres']) ? implode(', ', array_map(static fn($g) => $g['name'], $details['genres'])) : null;
    $runtime = $details['runtime'] ?? null;
    $rating = isset($details['vote_average']) ? round((float) $details['vote_average'], 1) : null;
    $voteCount = $details['vote_count'] ?? null;
    $poster = !empty($details['poster_path']) ? 'https://image.tmdb.org/t/p/w500' . $details['poster_path'] : null;
    $backdrop = !empty($details['backdrop_path']) ? 'https://image.tmdb.org/t/p/w1280' . $details['backdrop_path'] : null;
    $imdbId = $details['imdb_id'] ?? ($details['external_ids']['imdb_id'] ?? null);

    $update = $pdo->prepare(
        "UPDATE movies SET
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
            source = 'tmdb',
            updated_at = NOW()
         WHERE id = :id"
    );

    $update->execute([
        'tmdb_id' => $details['id'] ?? null,
        'imdb_id' => $imdbId,
        'original_title' => $details['original_title'] ?? $title,
        'overview' => $details['overview'] ?? null,
        'release_date' => $releaseDate,
        'release_year' => $releaseYear,
        'genres' => $genres,
        'runtime' => $runtime,
        'rating_average' => $rating,
        'vote_count' => $voteCount,
        'poster_url' => $poster,
        'backdrop_url' => $backdrop,
        'id' => $id,
    ]);
}

$pdo = db();
$movies = $pdo->query('SELECT id, title, release_year, tmdb_id FROM movies ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
$total = count($movies);
$ok = 0; $fail = 0;

foreach ($movies as $movie) {
    try {
        enrich_movie($pdo, $movie);
        $ok++;
        fwrite(STDOUT, "[OK] #{$movie['id']} {$movie['title']}\n");
    } catch (Throwable $e) {
        $fail++;
        fwrite(STDERR, "[FAIL] #{$movie['id']} {$movie['title']}: {$e->getMessage()}\n");
    }
}

fwrite(STDOUT, "\nGata. Actualizate: {$ok}, eșecuri: {$fail}, total: {$total}.\n");
