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

if (!empty($_SESSION['user'])) {
    header('Location: index.php');
    exit;
}

// Dacă există cookie de remember valid, logăm automat și redirect
if (empty($_SESSION['user']) && !empty($_COOKIE['remember'])) {
    $cookie = base64_decode($_COOKIE['remember']);
    if ($cookie !== false) {
        list($user, $expiry, $hmac) = array_pad(explode('|', $cookie), 3, '');
        $data = $user . '|' . $expiry;
        if ($expiry >= time() && hash_equals(hash_hmac('sha256', $data, REMEMBER_SECRET), $hmac)) {
            session_regenerate_id(true);
            $_SESSION['user'] = $user;
            header('Location: index.php');
            exit;
        } else {
            // cookie invalid/expirat -> ștergem
            setcookie('remember', '', time() - 3600, '/');
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);

    // Exemplu simplu de verificare (înlocuiește cu verificare în baza de date)
    $validUser = 'admin';
    $validPass = 'parola123';

    if ($username === $validUser && $password === $validPass) {
        session_regenerate_id(true);
        $_SESSION['user'] = $username;

        if ($remember) {
            $expiry = time() + 30 * 24 * 3600; // 30 zile
            $data = $username . '|' . $expiry;
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
  <link rel="stylesheet" href="style.css">
</head>
<body class="auth-body">
  <div class="auth-container">
    <h1 class="logo">🎬 MovieHub</h1>
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
