<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

require_method('GET');

$pdo = db();
require_admin($pdo);

// Get all users (keep it simple; small dataset expected)
$usersStmt = $pdo->query('SELECT id, username, email, role, created_at FROM users ORDER BY id ASC');
$users = $usersStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

// Pending submissions per user
$pendingMap = [];
$pendingStmt = $pdo->query("SELECT submitted_by AS user_id, COUNT(*) AS pending_count FROM movies WHERE status = 'pending' AND submitted_by IS NOT NULL GROUP BY submitted_by");
foreach ($pendingStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $pendingMap[(int) $row['user_id']] = (int) $row['pending_count'];
}

$baseDir = dirname(__DIR__, 2); // points to /studenti
$results = [];

foreach ($users as $user) {
    $userId = (int) $user['id'];
    $username = (string) $user['username'];
    $watchlistFile = $baseDir . '/watchlist_' . $username . '.json';
    $watchlistCount = 0;

    if (is_readable($watchlistFile)) {
        $data = json_decode((string) file_get_contents($watchlistFile), true);
        if (is_array($data)) {
            $watchlistCount = count($data);
        }
    }

    $pendingCount = $pendingMap[$userId] ?? 0;

    $results[] = [
        'id' => $userId,
        'username' => $username,
        'email' => $user['email'],
        'role' => $user['role'],
        'created_at' => $user['created_at'],
        'watchlist_count' => $watchlistCount,
        'pending_count' => $pendingCount,
    ];
}

json_response([
    'data' => $results,
    'total' => count($results),
]);
