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
      <button type="submit">Conectează-te</button>
      <p class="alt-link">Nu ai cont? <a href="register.php">Înregistrează-te</a></p>
    </form>
  </div>
</body>
</html>
