<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

require_method('POST');

$pdo = db();
require_admin($pdo);

$payload = json_decode(file_get_contents('php://input'), true) ?? [];
$reviewId = isset($payload['review_id']) ? (int) $payload['review_id'] : 0;

if ($reviewId <= 0) {
    json_response(['success' => false, 'error' => 'ID review invalid'], 422);
}

try {
    $stmt = $pdo->prepare('DELETE FROM reviews WHERE id = :id');
    $stmt->execute(['id' => $reviewId]);

    json_response(['success' => true]);
} catch (Throwable $e) {
    json_response(['success' => false, 'error' => 'Eroare la ștergere: ' . $e->getMessage()], 500);
}
