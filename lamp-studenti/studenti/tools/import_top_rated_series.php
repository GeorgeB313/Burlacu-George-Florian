#!/usr/bin/env php
<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

const TMDB_IMAGE_BASE = 'https://image.tmdb.org/t/p/w500';
const TMDB_BACKDROP_BASE = 'https://image.tmdb.org/t/p/w1280';

$options = getopt('', [
    'pages::',       // number of pages to import (each page ~20 items)
    'start-page::',  // default 1
    'all',           // import all pages reported by TMDb
    'max-pages::',   // optional safety cap when using --all
    'language::',
    'sleep-ms::',
]) ?: [];

$startPage = max(1, (int) ($options['start-page'] ?? 1));
$pages = (int) ($options['pages'] ?? 0);
$importAll = array_key_exists('all', $options);
$maxPages = isset($options['max-pages']) ? max(1, (int) $options['max-pages']) : null;
$languageOpt = $options['language'] ?? 'ro-RO';
$language = is_string($languageOpt) ? $languageOpt : 'ro-RO';
$sleepMs = max(0, (int) ($options['sleep-ms'] ?? 0));

$apiKey = getenv('TMDB_API_KEY') ?: 'c155c2567ed5d4f60645bdcbaf286670';
if (!$apiKey || $apiKey === 'YOUR_TMDB_KEY') {
    fwrite(STDERR, "TMDb API key is missing. Set TMDB_API_KEY env var.\n");
    exit(1);
}

if (!$importAll && $pages <= 0) {
    // Default: import a reasonable batch if user didn't specify.
    $pages = 10;
}

// Fetch TV genres once so we can map genre_ids -> names without extra requests per title.
$genreMap = [];
try {
    $genreResponse = tmdbFetch('genre/tv/list', [
        'language' => $language,
        'api_key' => $apiKey,
    ]);
    foreach (($genreResponse['genres'] ?? []) as $genre) {
        if (isset($genre['id'], $genre['name'])) {
            $genreMap[(int) $genre['id']] = (string) $genre['name'];
        }
    }
} catch (RuntimeException $e) {
    fwrite(STDERR, "Warning: could not fetch TV genres ({$e->getMessage()}); storing genres as NULL.\n");
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
    category = VALUES(category),
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
$processedPages = 0;

// First request: determines total_pages if --all
$firstPageResponse = tmdbFetch('tv/top_rated', [
    'page' => $startPage,
    'language' => $language,
    'api_key' => $apiKey,
]);
$totalPages = (int) ($firstPageResponse['total_pages'] ?? 0);

if ($importAll) {
    if ($totalPages <= 0) {
        fwrite(STDERR, "TMDb did not return total_pages; cannot use --all.\n");
        exit(1);
    }
    $pages = $totalPages - ($startPage - 1);
    if ($maxPages !== null) {
        $pages = min($pages, $maxPages);
    }
    fwrite(STDOUT, "Importing top rated series: start page {$startPage}, pages {$pages}" . ($maxPages !== null ? " (capped by --max-pages={$maxPages})" : "") . ".\n");
} else {
    fwrite(STDOUT, "Importing top rated series: start page {$startPage}, pages {$pages}.\n");
}

$pageResponse = $firstPageResponse;
for ($page = $startPage; $processedPages < $pages; $page++, $processedPages++) {
    if ($processedPages > 0) {
        $pageResponse = tmdbFetch('tv/top_rated', [
            'page' => $page,
            'language' => $language,
            'api_key' => $apiKey,
        ]);
    }

    $results = $pageResponse['results'] ?? [];
    if (!is_array($results) || !$results) {
        fwrite(STDOUT, "No results on page {$page}; stopping.\n");
        break;
    }

    foreach ($results as $tv) {
        $tmdbId = (int) ($tv['id'] ?? 0);
        if (!$tmdbId) {
            continue;
        }

        $firstAirDate = !empty($tv['first_air_date']) ? (string) $tv['first_air_date'] : null;
        $releaseYear = $firstAirDate ? (int) substr($firstAirDate, 0, 4) : null;

        $genreNames = null;
        if (!empty($tv['genre_ids']) && is_array($tv['genre_ids']) && $genreMap) {
            $names = [];
            foreach ($tv['genre_ids'] as $genreId) {
                $gid = (int) $genreId;
                if (isset($genreMap[$gid])) {
                    $names[] = $genreMap[$gid];
                }
            }
            if ($names) {
                $genreNames = implode(', ', array_values(array_unique($names)));
            }
        }

        $rating = isset($tv['vote_average']) ? round((float) $tv['vote_average'], 1) : null;
        $poster = !empty($tv['poster_path']) ? TMDB_IMAGE_BASE . $tv['poster_path'] : null;
        $backdrop = !empty($tv['backdrop_path']) ? TMDB_BACKDROP_BASE . $tv['backdrop_path'] : null;

        $payload = [
            'tmdb_id' => $tmdbId,
            // IMDb IDs for TV exist but require extra calls; keep null to avoid rate-limit.
            'imdb_id' => null,
            'title' => $tv['name'] ?? 'Unknown Series',
            'original_title' => $tv['original_name'] ?? null,
            'category' => 'series',
            'overview' => $tv['overview'] ?? null,
            'release_date' => $firstAirDate,
            'release_year' => $releaseYear,
            'genres' => $genreNames,
            // Episode runtime varies; leave null (not required for listing).
            'runtime' => null,
            'rating_average' => $rating,
            'vote_count' => $tv['vote_count'] ?? null,
            'poster_url' => $poster,
            'backdrop_url' => $backdrop,
            'accent_color' => '#0d1117',
        ];

        $stmt->execute($payload);
        $imported++;

        $name = (string) ($payload['title'] ?? '');
        fwrite(STDOUT, "Imported/updated: {$name} (TMDb {$tmdbId})\n");
    }

    if ($sleepMs > 0) {
        usleep($sleepMs * 1000);
    }
}

fwrite(STDOUT, "Done. {$imported} series processed.\n");
exit(0);

function tmdbFetch(string $path, array $params): array
{
    $url = 'https://api.themoviedb.org/3/' . ltrim($path, '/');
    $query = http_build_query($params);
    $ch = curl_init("{$url}?{$query}");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
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
        $message = is_array($decoded) ? ($decoded['status_message'] ?? ('HTTP ' . $status)) : ('HTTP ' . $status);
        throw new RuntimeException($message);
    }
    if (!is_array($decoded)) {
        throw new RuntimeException('Unexpected TMDb payload');
    }
    return $decoded;
}
