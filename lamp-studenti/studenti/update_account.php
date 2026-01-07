<?php
session_start();
header('Content-Type: application/json');

define('REMEMBER_SECRET', 'NZcJe9lFUck5pNBEOhT2yM805nzRRyISKb195KMDHzt2hsg7h2');
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/session_helpers.php';

if (empty($_SESSION['user'])) {
    echo json_encode(['success' => false, 'message' => 'Nu ești autentificat']);
    exit;
}

$pdo = db();
hydrateUserSession($pdo);

$currentUserId = $_SESSION['user_id'] ?? null;
$currentUsername = $_SESSION['user'] ?? '';

if (!$currentUserId) {
    echo json_encode(['success' => false, 'message' => 'Sesiune invalidă']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true) ?: [];
$action = $data['action'] ?? '';

$respond = static function (bool $success, string $message, array $extra = []) {
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $extra));
    exit;
};

try {
    if ($action === 'change_username') {
        $newUsername = trim($data['newUsername'] ?? '');

        if ($newUsername === '') {
            $respond(false, 'Username-ul nu poate fi gol.');
        }

        if ($newUsername === $currentUsername) {
            $respond(false, 'Acest username este deja folosit.');
        }

        if (!preg_match('/^[A-Za-z0-9_.-]{3,30}$/', $newUsername)) {
            $respond(false, 'Username-ul poate conține doar litere, cifre sau .-_ și minim 3 caractere.');
        }

        $checkStmt = $pdo->prepare('SELECT id FROM users WHERE username = :username LIMIT 1');
        $checkStmt->execute(['username' => $newUsername]);
        if ($checkStmt->fetch()) {
            $respond(false, 'Username-ul este deja folosit.');
        }

        $updateStmt = $pdo->prepare('UPDATE users SET username = :username WHERE id = :id LIMIT 1');
        $updateStmt->execute([
            'username' => $newUsername,
            'id' => $currentUserId,
        ]);

        $_SESSION['user'] = $newUsername;

        if (!empty($_COOKIE['remember'])) {
            $expiry = time() + 60 * 60 * 24 * 30;
            $cookieData = $newUsername . '|' . $expiry;
            $hmac = hash_hmac('sha256', $cookieData, REMEMBER_SECRET);
            setcookie('remember', base64_encode($cookieData . '|' . $hmac), $expiry, '/', '', isset($_SERVER['HTTPS']), true);
        }

        $respond(true, 'Username actualizat cu succes.', ['username' => $newUsername]);
    }

    if ($action === 'change_password') {
        $currentPassword = (string) ($data['currentPassword'] ?? '');
        $newPassword = (string) ($data['newPassword'] ?? '');

        if ($currentPassword === '' || $newPassword === '') {
            $respond(false, 'Toate câmpurile sunt obligatorii.');
        }

        if (strlen($newPassword) < 6) {
            $respond(false, 'Parola trebuie să aibă minimum 6 caractere.');
        }

        $userStmt = $pdo->prepare('SELECT password_hash FROM users WHERE id = :id LIMIT 1');
        $userStmt->execute(['id' => $currentUserId]);
        $userRow = $userStmt->fetch(PDO::FETCH_ASSOC);

        if (!$userRow || !password_verify($currentPassword, $userRow['password_hash'])) {
            $respond(false, 'Parola curentă este incorectă.');
        }

        $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
        $updateStmt = $pdo->prepare('UPDATE users SET password_hash = :hash WHERE id = :id LIMIT 1');
        $updateStmt->execute([
            'hash' => $newHash,
            'id' => $currentUserId,
        ]);

        $respond(true, 'Parola a fost actualizată.');
    }

    if ($action === 'delete_account') {
        $pdo->beginTransaction();
        $deleteStmt = $pdo->prepare('DELETE FROM users WHERE id = :id LIMIT 1');
        $deleteStmt->execute(['id' => $currentUserId]);

        if ($deleteStmt->rowCount() === 0) {
            $pdo->rollBack();
            $respond(false, 'Contul nu a putut fi șters.');
        }

        $pdo->commit();
        setcookie('remember', '', time() - 3600, '/');
        session_destroy();

        $respond(true, 'Contul a fost șters.');
    }

    $respond(false, 'Acțiune invalidă.');
} catch (PDOException $e) {
    $respond(false, 'A apărut o eroare neașteptată.');
}

