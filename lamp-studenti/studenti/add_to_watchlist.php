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

// Salvează în watchlist (folosind fișier JSON simplu)
$watchlist_file = __DIR__ . '/watchlist_' . $_SESSION['user'] . '.json';
$watchlist = [];

if (file_exists($watchlist_file)) {
    $watchlist = json_decode(file_get_contents($watchlist_file), true) ?: [];
}

// Verifică dacă filmul există deja
foreach ($watchlist as $item) {
    if ($item['title'] === $movie) {
        echo json_encode(['success' => false, 'message' => 'Filmul este deja în watchlist']);
        exit;
    }
}

// Adaugă filmul
$watchlist[] = [
    'title' => $movie,
    'added_at' => date('Y-m-d H:i:s'),
    'watched' => false
];

file_put_contents($watchlist_file, json_encode($watchlist, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

echo json_encode(['success' => true, 'message' => 'Film adăugat cu succes']);
?>