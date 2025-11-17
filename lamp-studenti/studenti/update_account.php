<?php
session_start();
header('Content-Type: application/json');

if (empty($_SESSION['user'])) {
    echo json_encode(['success' => false, 'message' => 'Nu ești autentificat']);
    exit;
}

$currentUser = $_SESSION['user'];
$data = json_decode(file_get_contents('php://input'), true);
$action = $data['action'] ?? '';

// Fișier pentru utilizatori (simplu - poți folosi baza de date mai târziu)
$usersFile = 'users.json';

function getUsers() {
    global $usersFile;
    if (!file_exists($usersFile)) {
        return [];
    }
    $content = file_get_contents($usersFile);
    return json_decode($content, true) ?: [];
}

function saveUsers($users) {
    global $usersFile;
    file_put_contents($usersFile, json_encode($users, JSON_PRETTY_PRINT));
}

// Schimbare Username
if ($action === 'change_username') {
    $newUsername = trim($data['newUsername'] ?? '');
    
    if (empty($newUsername)) {
        echo json_encode(['success' => false, 'message' => 'Username-ul nu poate fi gol']);
        exit;
    }
    
    if ($newUsername === $currentUser) {
        echo json_encode(['success' => false, 'message' => 'Acest username este deja folosit']);
        exit;
    }
    
    $users = getUsers();
    
    // Verifică dacă noul username există deja
    if (isset($users[$newUsername])) {
        echo json_encode(['success' => false, 'message' => 'Username-ul este deja luat']);
        exit;
    }
    
    // Actualizează username
    if (isset($users[$currentUser])) {
        $users[$newUsername] = $users[$currentUser];
        unset($users[$currentUser]);
        saveUsers($users);
        
        // Actualizează sesiunea
        $_SESSION['user'] = $newUsername;
        
        // Actualizează watchlist-ul (redenumește fișierul)
        $oldWatchlist = 'watchlist_' . $currentUser . '.json';
        $newWatchlist = 'watchlist_' . $newUsername . '.json';
        if (file_exists($oldWatchlist)) {
            rename($oldWatchlist, $newWatchlist);
        }
        
        echo json_encode(['success' => true, 'message' => 'Username actualizat cu succes']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Utilizatorul nu există']);
    }
    exit;
}

// Schimbare Parolă
if ($action === 'change_password') {
    $currentPassword = $data['currentPassword'] ?? '';
    $newPassword = $data['newPassword'] ?? '';
    
    if (empty($currentPassword) || empty($newPassword)) {
        echo json_encode(['success' => false, 'message' => 'Toate câmpurile sunt obligatorii']);
        exit;
    }
    
    if (strlen($newPassword) < 6) {
        echo json_encode(['success' => false, 'message' => 'Parola trebuie să aibă minimum 6 caractere']);
        exit;
    }
    
    $users = getUsers();
    
    if (!isset($users[$currentUser])) {
        echo json_encode(['success' => false, 'message' => 'Utilizatorul nu există']);
        exit;
    }
    
    // Verifică parola curentă
    if (!password_verify($currentPassword, $users[$currentUser])) {
        echo json_encode(['success' => false, 'message' => 'Parola curentă este incorectă']);
        exit;
    }
    
    // Actualizează parola
    $users[$currentUser] = password_hash($newPassword, PASSWORD_DEFAULT);
    saveUsers($users);
    
    echo json_encode(['success' => true, 'message' => 'Parola actualizată cu succes']);
    exit;
}

// Ștergere Cont
if ($action === 'delete_account') {
    $users = getUsers();
    
    if (isset($users[$currentUser])) {
        // Șterge utilizatorul
        unset($users[$currentUser]);
        saveUsers($users);
        
        // Șterge watchlist-ul
        $watchlistFile = 'watchlist_' . $currentUser . '.json';
        if (file_exists($watchlistFile)) {
            unlink($watchlistFile);
        }
        
        // Șterge sesiunea
        session_destroy();
        
        echo json_encode(['success' => true, 'message' => 'Contul a fost șters']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Utilizatorul nu există']);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Acțiune invalidă']);
?>
