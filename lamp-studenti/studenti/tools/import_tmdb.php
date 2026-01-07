#!/usr/bin/env php
<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

const TMDB_IMAGE_BASE = 'https://image.tmdb.org/t/p/w500';
const TMDB_BACKDROP_BASE = 'https://image.tmdb.org/t/p/w1280';

$options = getopt('', [
    'scope::',      // trending, popular, upcoming
    'page::',
    'limit::',
    'language::',
    'region::',
]) ?: [];

$scopeOption = $options['scope'] ?? 'trending';
$scope = is_string($scopeOption) ? strtolower($scopeOption) : 'trending';
$page = max(1, (int) ($options['page'] ?? 1));
$limit = max(1, min(50, (int) ($options['limit'] ?? 20)));
$languageOption = $options['language'] ?? 'ro-RO';
$language = is_string($languageOption) ? $languageOption : 'ro-RO';
$regionOption = $options['region'] ?? null;
$region = is_string($regionOption) ? strtoupper($regionOption) : null;

$apiKey = getenv('TMDB_API_KEY') ?: 'c155c2567ed5d4f60645bdcbaf286670';
if (!$apiKey || $apiKey === 'YOUR_TMDB_KEY') {
    fwrite(STDERR, "TMDb API key is missing. Set TMDB_API_KEY env var.\n");
    exit(1);
}

$endpoint = 'trending/movie/week';
$listParams = [
    'page' => $page,
    'language' => $language,
    'api_key' => $apiKey,
];

switch ($scope) {
    case 'popular':
        $endpoint = 'movie/popular';
        if ($region) {
            $listParams['region'] = $region;
        }
        break;
    case 'upcoming':
        // Use discover with release filters to retrieve genuine future releases
        $endpoint = 'discover/movie';
        $listParams['primary_release_date.gte'] = date('Y-m-d');
        $listParams['sort_by'] = 'primary_release_date.asc';
        $listParams['with_release_type'] = '2|3';
        $listParams['region'] = $region ?: 'RO';
        break;
    default:
        // trending default already set
        break;
}

try {
    $listResponse = tmdbFetch($endpoint, $listParams);
} catch (RuntimeException $e) {
    fwrite(STDERR, "TMDb request failed: {$e->getMessage()}\n");
    exit(1);
}

$results = $listResponse['results'] ?? [];
if (!$results) {
    fwrite(STDERR, "TMDb returned no titles for scope '{$scope}'.\n");
    exit(1);
}

$pdo = db();
$sql = <<<'SQL'
INSERT INTO movies
    (tmdb_id, imdb_id, title, original_title, category, overview, release_date,
     release_year, genres, runtime, rating_average, vote_count, poster_url,
     backdrop_url, accent_color, status, source)
VALUES
    (:tmdb_id, :imdb_id, :title, :original_title, :category, :overview, :release_date,
     :release_year, :genres, :runtime, :rating_average, :vote_count, :poster_url,
     :backdrop_url, :accent_color, 'published', 'tmdb')
ON DUPLICATE KEY UPDATE
    title = VALUES(title),
    original_title = VALUES(original_title),
    overview = VALUES(overview),
    release_date = VALUES(release_date),
    release_year = VALUES(release_year),
    genres = VALUES(genres),
    runtime = VALUES(runtime),
    rating_average = VALUES(rating_average),
    vote_count = VALUES(vote_count),
    poster_url = VALUES(poster_url),
    backdrop_url = VALUES(backdrop_url),
    accent_color = VALUES(accent_color),
    status = VALUES(status),
    source = VALUES(source),
    updated_at = NOW();
SQL;
$stmt = $pdo->prepare($sql);

$imported = 0;
foreach ($results as $movie) {
    if ($imported >= $limit) {
        break;
    }
    if (($movie['media_type'] ?? 'movie') !== 'movie') {
        continue; // skip TV entries when pulling trending
    }
    $tmdbId = (int) ($movie['id'] ?? 0);
    if (!$tmdbId) {
        continue;
    }

    try {
        $details = tmdbFetch("movie/{$tmdbId}", [
            'language' => $language,
            'append_to_response' => 'external_ids',
            'api_key' => $apiKey,
        ]);
    } catch (RuntimeException $e) {
        fwrite(STDERR, "Skipping TMDb #{$tmdbId}: {$e->getMessage()}\n");
        continue;
    }

    if ($language !== 'en-US' && shouldFallbackToEnglish($details)) {
        try {
            $fallbackDetails = tmdbFetch("movie/{$tmdbId}", [
                'language' => 'en-US',
                'append_to_response' => 'external_ids',
                'api_key' => $apiKey,
            ]);
            $details = mergeDetails($details, $fallbackDetails);
        } catch (RuntimeException $fallbackError) {
            fwrite(STDERR, "Fallback fetch failed for TMDb #{$tmdbId}: {$fallbackError->getMessage()}\n");
        }
    }

    $releaseDate = $details['release_date'] ?? null;
    $releaseYear = $releaseDate ? (int) substr($releaseDate, 0, 4) : null;
    $genres = isset($details['genres']) ? implode(', ', array_map(static fn($g) => $g['name'], $details['genres'])) : null;
    $runtime = $details['runtime'] ?? null;
    $rating = isset($details['vote_average']) ? round((float) $details['vote_average'], 1) : null;
    $poster = !empty($details['poster_path']) ? TMDB_IMAGE_BASE . $details['poster_path'] : null;
    $backdrop = !empty($details['backdrop_path']) ? TMDB_BACKDROP_BASE . $details['backdrop_path'] : null;
    $imdbId = $details['imdb_id'] ?? ($details['external_ids']['imdb_id'] ?? null);

    $payload = [
        'tmdb_id' => $tmdbId,
        'imdb_id' => $imdbId,
        'title' => $details['title'] ?? $details['name'] ?? 'Unknown Title',
        'original_title' => $details['original_title'] ?? $details['original_name'] ?? null,
        'category' => 'movie',
        'overview' => $details['overview'] ?? null,
        'release_date' => $releaseDate,
        'release_year' => $releaseYear,
        'genres' => $genres,
        'runtime' => $runtime,
        'rating_average' => $rating,
        'vote_count' => $details['vote_count'] ?? null,
        'poster_url' => $poster,
        'backdrop_url' => $backdrop,
        'accent_color' => '#0d1117',
    ];

    $stmt->execute($payload);
    $imported++;
    fwrite(STDOUT, "Imported/updated: {$payload['title']} (TMDb {$tmdbId})\n");
}

fwrite(STDOUT, "Done. {$imported} movie(s) processed.\n");
exit(0);

function tmdbFetch(string $path, array $params): array
{
    $url = 'https://api.themoviedb.org/3/' . ltrim($path, '/');
    $query = http_build_query($params);
    $ch = curl_init("{$url}?{$query}");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
    ]);
    $body = curl_exec($ch);
    if ($body === false) {
        $err = curl_error($ch);
        curl_close($ch);
        throw new RuntimeException($err ?: 'Unknown cURL error');
    }
    $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    $decoded = json_decode($body, true);
    if ($status >= 400) {
        $message = $decoded['status_message'] ?? ('HTTP ' . $status);
        throw new RuntimeException($message);
    }
    if (!is_array($decoded)) {
        throw new RuntimeException('Unexpected TMDb payload');
    }
    return $decoded;
}

function shouldFallbackToEnglish(array $details): bool
{
    $overviewEmpty = empty(trim($details['overview'] ?? ''));
    $titleEmpty = empty(trim($details['title'] ?? $details['name'] ?? ''));
    return $overviewEmpty || $titleEmpty;
}

function mergeDetails(array $primary, array $fallback): array
{
    $fields = ['title', 'name', 'original_title', 'original_name', 'overview', 'genres', 'runtime', 'release_date'];
    foreach ($fields as $field) {
        if (empty($primary[$field]) && !empty($fallback[$field])) {
            $primary[$field] = $fallback[$field];
        }
    }

    if (empty($primary['vote_average']) && !empty($fallback['vote_average'])) {
        $primary['vote_average'] = $fallback['vote_average'];
    }
    if (empty($primary['vote_count']) && !empty($fallback['vote_count'])) {
        $primary['vote_count'] = $fallback['vote_count'];
    }
    if (empty($primary['poster_path']) && !empty($fallback['poster_path'])) {
        $primary['poster_path'] = $fallback['poster_path'];
    }
    if (empty($primary['backdrop_path']) && !empty($fallback['backdrop_path'])) {
        $primary['backdrop_path'] = $fallback['backdrop_path'];
    }
    if (empty($primary['imdb_id']) && !empty($fallback['imdb_id'])) {
        $primary['imdb_id'] = $fallback['imdb_id'];
    }
    if (empty($primary['external_ids']['imdb_id'] ?? null) && !empty($fallback['external_ids']['imdb_id'] ?? null)) {
        $primary['external_ids']['imdb_id'] = $fallback['external_ids']['imdb_id'];
    }

    return $primary;
}
