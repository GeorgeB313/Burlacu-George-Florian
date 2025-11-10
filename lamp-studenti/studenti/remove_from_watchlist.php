<?php
session_start();

header('Content-Type: application/json');

if (empty($_SESSION['user'])) {
    echo json_encode(['success' => false, 'message' => 'Nu ești autentificat']);
    exit;
}

$movie = $_POST['movie'] ?? '';

if (empty($movie)) {
    echo json_encode(['success' => false, 'message' => 'Film invalid']);
    exit;
}

// Șterge din watchlist
$watchlist_file = __DIR__ . '/watchlist_' . $_SESSION['user'] . '.json';

if (!file_exists($watchlist_file)) {
    echo json_encode(['success' => false, 'message' => 'Watchlist-ul nu există']);
    exit;
}

$watchlist = json_decode(file_get_contents($watchlist_file), true) ?: [];

// Găsește și șterge filmul
$found = false;
$new_watchlist = [];

foreach ($watchlist as $item) {
    if ($item['title'] !== $movie) {
        $new_watchlist[] = $item;
    } else {
        $found = true;
    }
}

if (!$found) {
    echo json_encode(['success' => false, 'message' => 'Filmul nu este în watchlist']);
    exit;
}

file_put_contents($watchlist_file, json_encode($new_watchlist, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

echo json_encode(['success' => true, 'message' => 'Film șters cu succes']);
?>
