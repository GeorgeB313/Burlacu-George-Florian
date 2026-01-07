<?php
session_set_cookie_params([
  'lifetime' => 0,
  'path' => '/',
  'secure' => isset($_SERVER['HTTPS']),
  'httponly' => true,
  'samesite' => 'Lax',
]);

session_start();

define('REMEMBER_SECRET', 'NZcJe9lFUck5pNBEOhT2yM805nzRRyISKb195KMDHzt2hsg7h2');

require_once __DIR__ . '/config/database.php';

$pdo = db();

$error = '';

$syncSession = function (array $user): void {
  $_SESSION['user_id'] = (int) $user['id'];
  $_SESSION['user'] = $user['username'];
  $_SESSION['user_role'] = $user['role'] ?? 'user';
};

if (!empty($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

// Dacă există cookie de remember valid, logăm automat și redirect
if (empty($_SESSION['user_id']) && !empty($_COOKIE['remember'])) {
    $cookie = base64_decode($_COOKIE['remember']);
    if ($cookie !== false) {
        list($user, $expiry, $hmac) = array_pad(explode('|', $cookie), 3, '');
        $data = $user . '|' . $expiry;
        if ($expiry >= time() && hash_equals(hash_hmac('sha256', $data, REMEMBER_SECRET), $hmac)) {
      $stmt = $pdo->prepare('SELECT id, username, role FROM users WHERE username = :username LIMIT 1');
      $stmt->execute(['username' => $user]);
      if ($dbUser = $stmt->fetch()) {
        session_regenerate_id(true);
        $syncSession($dbUser);
        header('Location: index.php');
        exit;
      } else {
        setcookie('remember', '', time() - 3600, '/');
      }
        } else {
            // cookie invalid/expirat -> ștergem
            setcookie('remember', '', time() - 3600, '/');
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $identifier = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);

  $stmt = $pdo->prepare('SELECT id, username, password_hash, role FROM users WHERE username = :username OR email = :email LIMIT 1');
  $stmt->execute(['username' => $identifier, 'email' => $identifier]);
  $user = $stmt->fetch();

  if ($user && password_verify($password, $user['password_hash'])) {
        session_regenerate_id(true);
    $syncSession($user);

        if ($remember) {
      $expiry = time() + 30 * 24 * 3600; // 30 zile
      $data = $user['username'] . '|' . $expiry;
            $hmac = hash_hmac('sha256', $data, REMEMBER_SECRET);
            $cookie = base64_encode($data . '|' . $hmac);
            setcookie('remember', $cookie, $expiry, '/', '', isset($_SERVER['HTTPS']), true);
        } else {
            // asigurăm că nu rămâne cookie remember
            if (!empty($_COOKIE['remember'])) {
                setcookie('remember', '', time() - 3600, '/');
            }
        }

        // Marchează faptul că tocmai te-ai logat (valabil scurt) pentru a nu te deloga imediat în acest tab
        setcookie('just_logged_in', '1', [
          'expires' => time() + 60, // 1 minut e suficient
          'path' => '/',
          'secure' => isset($_SERVER['HTTPS']),
          'httponly' => false,     // trebuie accesibil din JS ca să îl ștergem
          'samesite' => 'Lax',
        ]);

        header('Location: index.php');
        exit;
    } else {
        $error = 'Credentiale invalide.';
    }
}
?>
<!DOCTYPE html>
<html lang="ro">
<head>
  <meta charset="UTF-8">
  <title>Login - MovieHub</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="style.css">
</head>
<body class="auth-body">
  <div class="auth-container">
    <h1 class="logo">🎬<span class="logo-movie">Movie</span><span class="logo-hub">Hub</span></h1>
    <h2>Autentificare</h2>
    <form method="POST" action="login.php">
      <input type="text" name="username" placeholder="Nume utilizator" required>
      <input type="password" name="password" placeholder="Parolă" required>

      <label class="remember">
        <input type="checkbox" name="remember" aria-label="Ține-mă minte">
        <span class="remember-text">Ține-mă minte (Remember me)</span>
      </label>

      <button type="submit">Conectează-te</button>
      <p class="alt-link">Nu ai cont? <a href="register.php">Înregistrează-te</a></p>
    </form>
    <?php if (!empty($error)) echo '<p style="color:red;">' . htmlspecialchars($error) . '</p>'; ?>
  </div>
</body>
</html>
