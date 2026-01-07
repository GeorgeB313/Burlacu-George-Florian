<?php
declare(strict_types=1);

function json_response(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function require_method(string $method): void
{
    if (strtoupper($_SERVER['REQUEST_METHOD'] ?? '') !== strtoupper($method)) {
        json_response(['error' => 'Method Not Allowed'], 405);
    }
}

function current_user_id(\PDO $pdo): ?int
{
    if (!empty($_SESSION['user_id'])) {
        return (int) $_SESSION['user_id'];
    }

    if (function_exists('hydrateUserSession')) {
        hydrateUserSession($pdo);
        if (!empty($_SESSION['user_id'])) {
            return (int) $_SESSION['user_id'];
        }
    }

    if (!empty($_SESSION['user'])) {
        $stmt = $pdo->prepare('SELECT id, role FROM users WHERE username = :username LIMIT 1');
        $stmt->execute(['username' => $_SESSION['user']]);
        $user = $stmt->fetch(\PDO::FETCH_ASSOC);
        if ($user) {
            $_SESSION['user_id'] = (int) $user['id'];
            if (!empty($user['role'])) {
                $_SESSION['user_role'] = $user['role'];
            }
            return (int) $user['id'];
        }
    }

    return null;
}

function require_auth(\PDO $pdo): int
{
    $userId = current_user_id($pdo);
    if (!$userId) {
        json_response(['error' => 'Unauthenticated'], 401);
    }

    return $userId;
}

function current_user_role(\PDO $pdo): ?string
{
    if (!empty($_SESSION['user_role'])) {
        return $_SESSION['user_role'];
    }

    $userId = current_user_id($pdo);
    if (!$userId) {
        return null;
    }

    $stmt = $pdo->prepare('SELECT role FROM users WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $userId]);
    $role = $stmt->fetchColumn();
    if ($role) {
        $_SESSION['user_role'] = $role;
        return $role;
    }

    return null;
}

function require_admin(\PDO $pdo): int
{
    $userId = require_auth($pdo);
    $role = current_user_role($pdo);
    if ($role !== 'admin') {
        json_response(['error' => 'Forbidden'], 403);
    }

    return $userId;
}
