<?php

if (!function_exists('hydrateUserSession')) {
    /**
     * Populate session with database-backed identifiers when only username is present (e.g., via remember-me cookies).
     */
    function hydrateUserSession(PDO $pdo): void
    {
        if (empty($_SESSION['user'])) {
            return;
        }

        if (!empty($_SESSION['user_id']) && !empty($_SESSION['user_role'])) {
            return;
        }

        $stmt = $pdo->prepare('SELECT id, role FROM users WHERE username = :username LIMIT 1');
        $stmt->execute(['username' => $_SESSION['user']]);
        if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $_SESSION['user_id'] = (int) $row['id'];
            $_SESSION['user_role'] = $row['role'] ?? 'user';
        }
    }
}
