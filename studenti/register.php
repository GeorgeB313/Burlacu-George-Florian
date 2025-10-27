<!DOCTYPE html>
<html lang="ro">
<head>
  <meta charset="UTF-8">
  <title>Înregistrare - MovieHub</title>
  <link rel="stylesheet" href="style.css">
</head>
<body class="auth-body">
  <div class="auth-container">
    <h1 class="logo">🎬 MovieHub</h1>
    <h2>Creează cont</h2>
    <form method="POST" action="register.php">
      <input type="text" name="username" placeholder="Nume utilizator" required>
      <input type="email" name="email" placeholder="Email" required>
      <input type="password" name="password" placeholder="Parolă" required>
      <button type="submit">Înregistrează-te</button>
      <p class="alt-link">Ai deja cont? <a href="login.php">Conectează-te</a></p>
    </form>
  </div>
</body>
</html>
