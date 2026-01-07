<?php
session_start();

require_once __DIR__ . '/config/database.php';

$errors = [];
$username = '';
$email = '';

if (!empty($_SESSION['user_id'])) {
  header('Location: index.php');
  exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $username = trim($_POST['username'] ?? '');
  $email = trim($_POST['email'] ?? '');
  $password = $_POST['password'] ?? '';

  if (strlen($username) < 3) {
    $errors[] = 'Username-ul trebuie să aibă minim 3 caractere.';
  }
  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Email invalid.';
  }
  if (strlen($password) < 6) {
    $errors[] = 'Parola trebuie să aibă cel puțin 6 caractere.';
  }

  if (!$errors) {
    try {
      $pdo = db();
      $stmt = $pdo->prepare('SELECT id FROM users WHERE username = :username OR email = :email LIMIT 1');
      $stmt->execute(['username' => $username, 'email' => $email]);
      if ($stmt->fetch()) {
        $errors[] = 'Username-ul sau emailul sunt deja folosite.';
      } else {
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        $insert = $pdo->prepare('INSERT INTO users (username, email, password_hash, role) VALUES (:username, :email, :hash, "user")');
        $insert->execute(['username' => $username, 'email' => $email, 'hash' => $passwordHash]);

        $_SESSION['user_id'] = (int) $pdo->lastInsertId();
        $_SESSION['user'] = $username;
        $_SESSION['user_role'] = 'user';

        header('Location: index.php');
        exit;
      }
    } catch (PDOException $e) {
      $errors[] = 'Eroare la crearea contului. Încearcă din nou.';
    }
  }
}
?>
<!DOCTYPE html>
<html lang="ro">
<head>
  <meta charset="UTF-8">
  <title>Înregistrare - MovieHub</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="style.css">
</head>
<body class="auth-body">
  <div class="auth-container">
    <h1 class="logo">🎬<span class="logo-movie">Movie</span><span class="logo-hub">Hub</span></h1>
    <h2>Creează cont</h2>
    <form method="POST" action="register.php">
      <input type="text" name="username" placeholder="Nume utilizator" value="<?php echo htmlspecialchars($username); ?>" required>
      <input type="email" name="email" placeholder="Email" value="<?php echo htmlspecialchars($email); ?>" required>
      <input type="password" name="password" placeholder="Parolă" required>
      <button type="submit">Înregistrează-te</button>
      <p class="alt-link">Ai deja cont? <a href="login.php">Conectează-te</a></p>
    </form>
    <?php if (!empty($errors)): ?>
      <div class="auth-error">
        <?php foreach ($errors as $error): ?>
          <p><?php echo htmlspecialchars($error); ?></p>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</body>
</html>
