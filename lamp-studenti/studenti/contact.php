<?php
require_once __DIR__ . '/config/database.php';

$errors = ['nume' => '', 'email' => '', 'mesaj' => ''];
$submitted = false;
$dbError = '';
$name = '';
$email = '';
$message = '';

try {
  $pdo = db();
  $pdo->exec("CREATE TABLE IF NOT EXISTS contact_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nume VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    mesaj TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
} catch (Throwable $e) {
  $dbError = 'Nu m-am putut conecta la baza de date.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $name = trim($_POST['nume'] ?? '');
  $email = trim($_POST['email'] ?? '');
  $message = trim($_POST['mesaj'] ?? '');

  if ($name === '' || strlen($name) < 3) {
    $errors['nume'] = 'Introduceți un nume cu minim 3 caractere.';
  }

  if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors['email'] = 'Introduceți un email valid.';
  }

  if ($message === '' || strlen($message) < 10) {
    $errors['mesaj'] = 'Mesajul trebuie să aibă cel puțin 10 caractere.';
  }

  $submitted = empty(array_filter($errors)) && !$dbError;

  if ($submitted) {
    try {
      $stmt = $pdo->prepare('INSERT INTO contact_messages (nume, email, mesaj) VALUES (:nume, :email, :mesaj)');
      $stmt->execute([
        'nume' => $name,
        'email' => $email,
        'mesaj' => $message,
      ]);
    } catch (Throwable $e) {
      $dbError = 'Nu am putut salva mesajul.';
      $submitted = false;
    }
  }
}

?>
<!DOCTYPE html>
<html lang="ro">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Formular Contact</title>
  <style>
    body { font-family: Arial, sans-serif; background:#0d1117; color:#e6edf3; display:flex; justify-content:center; align-items:flex-start; min-height:100vh; padding:40px; }
    .page { width:100%; max-width:1080px; display:grid; gap:20px; }
    .card { background:#161b22; border:1px solid #30363d; border-radius:12px; padding:24px; width:100%; box-shadow:0 10px 30px rgba(0,0,0,0.4); }
    h1 { margin-top:0; margin-bottom:16px; }
    label { display:block; margin-bottom:6px; font-weight:600; }
    input, textarea { width:100%; padding:10px 12px; border-radius:8px; border:1px solid #30363d; background:#0d1117; color:#e6edf3; box-sizing:border-box; }
    textarea { resize:vertical; min-height:120px; }
    .field { margin-bottom:16px; }
    .error { margin-top:6px; color:#f85149; font-size:0.9rem; }
    .success { padding:12px; border-radius:8px; background:rgba(35,134,54,0.15); border:1px solid rgba(35,134,54,0.4); color:#8ae58a; margin-bottom:16px; }
    .alert { padding:12px; border-radius:8px; background:rgba(248,113,113,0.12); border:1px solid rgba(248,113,113,0.35); color:#fecdd3; margin-bottom:16px; }
    button { background:#e5b90a; color:#0d1117; border:none; padding:12px 18px; border-radius:8px; font-weight:700; cursor:pointer; width:100%; }
    button:hover { background:#f0c419; }
    table { width:100%; border-collapse:collapse; margin-top:10px; }
    th, td { border-bottom:1px solid #30363d; padding:10px 12px; text-align:left; vertical-align:top; }
    th { color:#9ca3af; text-transform:uppercase; font-size:12px; letter-spacing:0.02em; }
    tr:last-child td { border-bottom:none; }
    .table-wrapper { overflow:auto; border:1px solid #30363d; border-radius:12px; }
    .badge { display:inline-block; padding:4px 8px; border-radius:999px; font-size:12px; background:rgba(229,185,10,0.15); color:#f1c40f; border:1px solid rgba(229,185,10,0.4); }
    .muted { color:#9ca3af; font-size:13px; }
  </style>
</head>
<body>
  <div class="page">
    <div class="card">
      <h1>Contact</h1>

      <?php if ($dbError): ?>
        <div class="alert"><?= htmlspecialchars($dbError) ?></div>
      <?php elseif ($submitted): ?>
        <div class="success" id="phpSuccess">Mulțumim, <?= htmlspecialchars($name) ?>! Mesajul tău a fost înregistrat.</div>
      <?php endif; ?>

      <form id="contactForm" method="POST" novalidate>
        <div class="field">
          <label for="nume">Nume</label>
          <input type="text" id="nume" name="nume" value="<?= htmlspecialchars($name) ?>" minlength="3" required>
          <?php if ($errors['nume']): ?><div class="error"><?= htmlspecialchars($errors['nume']) ?></div><?php endif; ?>
        </div>

        <div class="field">
          <label for="email">Email</label>
          <input type="email" id="email" name="email" value="<?= htmlspecialchars($email) ?>" required>
          <?php if ($errors['email']): ?><div class="error"><?= htmlspecialchars($errors['email']) ?></div><?php endif; ?>
        </div>

        <div class="field">
          <label for="mesaj">Mesaj</label>
          <textarea id="mesaj" name="mesaj" minlength="10" required><?= htmlspecialchars($message) ?></textarea>
          <?php if ($errors['mesaj']): ?><div class="error"><?= htmlspecialchars($errors['mesaj']) ?></div><?php endif; ?>
        </div>

        <button type="submit">Trimite</button>
      </form>
    </div>

  </div>

  <script>
    (function() {
      const form = document.getElementById('contactForm');

      const showError = (el, msg) => {
        let err = el.parentElement.querySelector('.error.js');
        if (!err) {
          err = document.createElement('div');
          err.className = 'error js';
          el.parentElement.appendChild(err);
        }
        err.textContent = msg || '';
      };

      const clearErrors = () => {
        document.querySelectorAll('.error.js').forEach(e => e.remove());
      };

      form.addEventListener('submit', (e) => {
        clearErrors();
        const name = form.nume.value.trim();
        const email = form.email.value.trim();
        const msg = form.mesaj.value.trim();
        let valid = true;

        if (name.length < 3) {
          showError(form.nume, 'Introduceți un nume cu minim 3 caractere.');
          valid = false;
        }

        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(email)) {
          showError(form.email, 'Introduceți un email valid.');
          valid = false;
        }

        if (msg.length < 10) {
          showError(form.mesaj, 'Mesajul trebuie să aibă cel puțin 10 caractere.');
          valid = false;
        }

        if (!valid) {
          e.preventDefault();
        }
      });
    })();
  </script>
</body>
</html>
