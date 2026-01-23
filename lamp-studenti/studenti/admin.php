<?php
session_start();

define('REMEMBER_SECRET', 'NZcJe9lFUck5pNBEOhT2yM805nzRRyISKb195KMDHzt2hsg7h2');
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/session_helpers.php';

if (empty($_SESSION['user']) && !empty($_COOKIE['remember'])) {
    $cookie = base64_decode($_COOKIE['remember']);
    if ($cookie !== false) {
        list($user, $expiry, $hmac) = array_pad(explode('|', $cookie), 3, '');
        $data = $user . '|' . $expiry;
        if ($expiry >= time() && hash_equals(hash_hmac('sha256', $data, REMEMBER_SECRET), $hmac)) {
            session_regenerate_id(true);
            $_SESSION['user'] = $user;
        } else {
            setcookie('remember', '', time() - 3600, '/');
        }
    }
}

if (empty($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

$pdo = db();
hydrateUserSession($pdo);

$currentUserId = $_SESSION['user_id'] ?? null;
$currentUserRole = $_SESSION['user_role'] ?? 'user';

if ($currentUserRole !== 'admin') {
  header('Location: index.php');
  exit;
}

$stats = [
    'movies' => 0,
    'pending' => 0,
    'users' => 0,
];

$createAdminErrors = [];
$createAdminSuccess = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_admin'])) {
  $newUser = trim($_POST['new_username'] ?? '');
  $newEmail = trim($_POST['new_email'] ?? '');
  $newPass = $_POST['new_password'] ?? '';

  if (strlen($newUser) < 3) {
    $createAdminErrors['new_username'] = 'Username minim 3 caractere.';
  }
  if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
    $createAdminErrors['new_email'] = 'Introduce un email valid.';
  }
  if (strlen($newPass) < 6) {
    $createAdminErrors['new_password'] = 'Parola minim 6 caractere.';
  }

  if (!$createAdminErrors) {
    try {
      $checkStmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE username = :u OR email = :e');
      $checkStmt->execute(['u' => $newUser, 'e' => $newEmail]);
      $exists = (int) $checkStmt->fetchColumn();

      if ($exists > 0) {
        $createAdminErrors['general'] = 'Există deja un utilizator cu acest username sau email.';
      } else {
        $hash = password_hash($newPass, PASSWORD_BCRYPT);
        $ins = $pdo->prepare('INSERT INTO users (username, email, password_hash, role) VALUES (:u, :e, :p, "admin")');
        $ins->execute(['u' => $newUser, 'e' => $newEmail, 'p' => $hash]);
        $createAdminSuccess = 'Cont admin creat: ' . htmlspecialchars($newUser, ENT_QUOTES);
      }
    } catch (PDOException $e) {
      $createAdminErrors['general'] = 'Eroare la salvare: ' . $e->getMessage();
    }
  }
}

try {
    $stats['movies'] = (int) $pdo->query('SELECT COUNT(*) FROM movies')->fetchColumn();
    $stats['pending'] = (int) $pdo->query("SELECT COUNT(*) FROM movies WHERE status = 'pending'")->fetchColumn();
    $stats['users'] = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
} catch (PDOException $e) {
    // leave defaults
}

$pendingMovies = [];
$recentUsers = [];
$contactMessages = [];
$contactError = '';
$assetVersionCss = file_exists(__DIR__ . '/style.css') ? filemtime(__DIR__ . '/style.css') : time();
$assetVersionJs = file_exists(__DIR__ . '/script.js') ? filemtime(__DIR__ . '/script.js') : time();

try {
    $pendingStmt = $pdo->query("
        SELECT m.id, m.title, m.category, m.status, m.created_at, u.username AS submitted_by
        FROM movies m
        LEFT JOIN users u ON u.id = m.submitted_by
        WHERE m.status = 'pending'
        ORDER BY m.created_at DESC
        LIMIT 10
    ");
    $pendingMovies = $pendingStmt->fetchAll(PDO::FETCH_ASSOC);

    $userStmt = $pdo->query('
        SELECT id, username, email, role, created_at
        FROM users
        ORDER BY created_at DESC
        LIMIT 10
    ');
    $recentUsers = $userStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // keep arrays empty
}

  // Mesaje contact
  try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS contact_messages (
      id INT AUTO_INCREMENT PRIMARY KEY,
      nume VARCHAR(255) NOT NULL,
      email VARCHAR(255) NOT NULL,
      mesaj TEXT NOT NULL,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $stmt = $pdo->query('SELECT id, nume, email, mesaj, created_at FROM contact_messages ORDER BY created_at DESC LIMIT 50');
    $contactMessages = $stmt->fetchAll(PDO::FETCH_ASSOC);
  } catch (PDOException $e) {
    $contactError = 'Nu am putut încărca mesajele de contact.';
  }
?>
<!DOCTYPE html>
<html lang="ro">
<head>
  <meta charset="UTF-8">
  <title>MovieHub - Panou Admin</title>
  <link rel="icon" href="data:,">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="style.css?v=<?php echo $assetVersionCss; ?>">
  <script defer src="script.js?v=<?php echo $assetVersionJs; ?>"></script>
</head>
<body data-page="admin" data-user-id="<?php echo $currentUserId ?? 0; ?>" data-user-role="<?php echo htmlspecialchars($currentUserRole, ENT_QUOTES); ?>">
  <header class="main-header">
    <a href="index.php" class="logo" aria-label="Înapoi la Home">🎬 <span class="logo-movie">Movie</span><span class="logo-hub">Hub</span></a>
    <nav>
      <a href="index.php" class="nav-link nav-home">
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>
      </a>
      <a href="watchlist.php" class="nav-link nav-watchlist">
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m19 21-7-4-7 4V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"></path></svg>
      </a>
      <div class="dropdown">
        <a href="javascript:void(0)" class="nav-link nav-toprated">
          <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>
        </a>
        <div class="dropdown-menu">
          <a href="top-rated.php?type=movies">Top Rated Movies</a>
          <a href="top-rated.php?type=series">Top Rated Series</a>
        </div>
      </div>
      <div class="search-container">
        <input type="text" id="search" placeholder="🔍 Caută filme...">
        <div class="search-suggestions" id="searchSuggestions"></div>
      </div>
      <button id="addMovieBtn">
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"></path><path d="M12 5v14"></path></svg>
      </button>
      <a href="admin.php" class="nav-link nav-admin active" title="Panou administrator">
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
      </a>
      <a href="settings.php" class="nav-link nav-settings">
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"></path><circle cx="12" cy="12" r="3"></circle></svg>
      </a>
      <a href="logout.php" class="logout-btn">
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" x2="9" y1="12" y2="12"></line></svg>
      </a>
    </nav>
  </header>

  <main class="content admin-content">
    <h2 class="page-title">🛠️ Panou Administrator</h2>

    <section class="admin-stats">
      <div class="stat-card" id="totalMoviesCard" role="button" tabindex="0">
        <p>Total titluri</p>
        <strong id="totalMoviesCount"><?php echo $stats['movies']; ?></strong>
      </div>
      <div class="stat-card">
        <p>În așteptare</p>
        <strong id="pendingMoviesCount"><?php echo $stats['pending']; ?></strong>
      </div>
      <div class="stat-card" id="usersCard" role="button" tabindex="0">
        <p>Utilizatori</p>
        <strong id="usersCount"><?php echo $stats['users']; ?></strong>
      </div>
    </section>

    <section class="admin-section">
      <div class="admin-create-card">
        <div class="admin-create-header">
          <div class="admin-create-icon">👤</div>
          <div>
            <h3>Creează cont admin</h3>
            <p>Adaugă rapid un nou utilizator cu rol de administrator.</p>
          </div>
          <div class="admin-create-meta">Acces complet la panou</div>
        </div>

        <?php if ($createAdminSuccess): ?>
          <div class="admin-create-alert success">✅ <?= $createAdminSuccess ?></div>
        <?php endif; ?>
        <?php if (!empty($createAdminErrors['general'])): ?>
          <div class="admin-create-alert error">⚠️ <?= htmlspecialchars($createAdminErrors['general']) ?></div>
        <?php endif; ?>

        <form method="POST" class="admin-create-form">
          <input type="hidden" name="create_admin" value="1">
          <div class="field">
            <label for="new_username">Username</label>
            <input type="text" id="new_username" name="new_username" value="<?= htmlspecialchars($_POST['new_username'] ?? '') ?>" required minlength="3" placeholder="ex: superadmin">
            <?php if (!empty($createAdminErrors['new_username'])): ?><div class="error"><?= htmlspecialchars($createAdminErrors['new_username']) ?></div><?php endif; ?>
          </div>
          <div class="field">
            <label for="new_email">Email</label>
            <input type="email" id="new_email" name="new_email" value="<?= htmlspecialchars($_POST['new_email'] ?? '') ?>" required placeholder="admin@example.com">
            <?php if (!empty($createAdminErrors['new_email'])): ?><div class="error"><?= htmlspecialchars($createAdminErrors['new_email']) ?></div><?php endif; ?>
          </div>
          <div class="field">
            <label for="new_password">Parolă</label>
            <input type="password" id="new_password" name="new_password" required minlength="6" placeholder="minim 6 caractere">
            <?php if (!empty($createAdminErrors['new_password'])): ?><div class="error"><?= htmlspecialchars($createAdminErrors['new_password']) ?></div><?php endif; ?>
          </div>
          <div class="field submit">
            <button type="submit" class="admin-action wide">Creează admin</button>
          </div>
        </form>
      </div>
    </section>

    <section class="admin-section">
      <div class="section-header">
        <h3>🎬 Titluri în așteptarea aprobării</h3>
        <p>Revizuiește propunerile manuale și aprobă-le pentru a apărea în catalog.</p>
      </div>
      <?php if (empty($pendingMovies)): ?>
        <div class="empty-state" id="pendingEmptyState">Nu există titluri în așteptare.</div>
      <?php else: ?>
      <table class="admin-table" id="pendingMoviesTable">
        <thead>
          <tr>
            <th>ID</th>
            <th>Titlu</th>
            <th>Tip</th>
            <th>Trimis de</th>
            <th>Data</th>
            <th>Acțiuni</th>
          </tr>
        </thead>
        <tbody id="pendingMoviesBody">
          <?php foreach ($pendingMovies as $movie): ?>
          <tr>
            <td>#<?php echo (int) $movie['id']; ?></td>
            <td><?php echo htmlspecialchars($movie['title']); ?></td>
            <td><?php echo $movie['category'] === 'series' ? 'Serial' : 'Film'; ?></td>
            <td><?php echo htmlspecialchars($movie['submitted_by'] ?? 'Anonim'); ?></td>
            <td><?php echo htmlspecialchars(date('d.m.Y H:i', strtotime($movie['created_at']))); ?></td>
            <td>
              <button class="admin-action" data-action="approve" data-movie-id="<?php echo (int) $movie['id']; ?>">✅</button>
              <button class="admin-action" data-action="reject" data-movie-id="<?php echo (int) $movie['id']; ?>">🗑️</button>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <div class="empty-state" id="pendingEmptyState" style="display:none;">Nu există titluri în așteptare.</div>
      <?php endif; ?>
    </section>

    <section class="admin-section">
      <div class="section-header">
        <h3>📩 Mesaje de contact</h3>
        <p>Ultimele mesaje trimise prin formularul public.</p>
      </div>
      <?php if ($contactError): ?>
        <div class="admin-create-alert error">⚠️ <?= htmlspecialchars($contactError) ?></div>
      <?php elseif (empty($contactMessages)): ?>
        <div class="empty-state">Nu există mesaje încă.</div>
      <?php else: ?>
      <table class="admin-table">
        <thead>
          <tr>
            <th>ID</th>
            <th>Nume</th>
            <th>Email</th>
            <th>Mesaj</th>
            <th>Data</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($contactMessages as $msg): ?>
          <tr>
            <td>#<?php echo (int) $msg['id']; ?></td>
            <td><?php echo htmlspecialchars($msg['nume']); ?></td>
            <td><?php echo htmlspecialchars($msg['email']); ?></td>
            <td><?php echo nl2br(htmlspecialchars($msg['mesaj'])); ?></td>
            <td><?php echo htmlspecialchars(date('d.m.Y H:i', strtotime($msg['created_at']))); ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>
    </section>

    <section class="admin-section">
      <div class="section-header">
        <h3>👥 Utilizatori recent înregistrați</h3>
        <p>Monitorizează ultimele conturi create și rolurile acestora.</p>
      </div>
      <?php if (empty($recentUsers)): ?>
        <div class="empty-state">Nu există utilizatori disponibili.</div>
      <?php else: ?>
      <table class="admin-table">
        <thead>
          <tr>
            <th>ID</th>
            <th>Username</th>
            <th>Email</th>
            <th>Rol</th>
            <th>Creat la</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($recentUsers as $user): ?>
          <tr>
            <td>#<?php echo (int) $user['id']; ?></td>
            <td><?php echo htmlspecialchars($user['username']); ?></td>
            <td><?php echo htmlspecialchars($user['email']); ?></td>
            <td><?php echo $user['role'] === 'admin' ? 'Admin' : 'User'; ?></td>
            <td><?php echo htmlspecialchars(date('d.m.Y', strtotime($user['created_at']))); ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>
    </section>
  </main>
  <div id="allTitlesModal" class="modal">
    <div class="modal-content all-titles-modal">
      <div class="all-titles-header">
        <h2>📚 Toate titlurile din catalog</h2>
        <button type="button" id="closeAllTitlesBtn" aria-label="Închide lista">Închide Lista</button>
      </div>
      <div class="all-titles-controls">
        <input type="search" id="allTitlesSearch" placeholder="Filtrează după nume...">
        <select id="allTitlesStatusFilter">
          <option value="">Status: Toate</option>
          <option value="published">Publicate</option>
          <option value="pending">În așteptare</option>
          <option value="archived">Arhivate</option>
        </select>
        <select id="allTitlesCategoryFilter">
          <option value="">Tip: Toate</option>
          <option value="movie">Filme</option>
          <option value="series">Seriale</option>
        </select>
        <div class="all-titles-meta" id="allTitlesMeta">—</div>
      </div>
      <div class="all-titles-table-wrapper">
        <table class="admin-table" id="allTitlesTable">
          <thead>
            <tr>
              <th>ID</th>
              <th>Titlu</th>
              <th>Tip</th>
              <th>Status</th>
              <th>An</th>
              <th>Rating</th>
            </tr>
          </thead>
          <tbody id="allTitlesBody"></tbody>
        </table>
        <div class="empty-state" id="allTitlesEmpty" style="display:none;">Nu există titluri pentru filtrul selectat.</div>
      </div>
      <div class="all-titles-pagination">
        <button type="button" id="allTitlesPrev" aria-label="Pagina anterioară">‹</button>
        <span id="allTitlesPage">Pagina 1</span>
        <button type="button" id="allTitlesNext" aria-label="Pagina următoare">›</button>
      </div>
    </div>
  </div>

  <div id="allUsersModal" class="modal">
    <div class="modal-content all-users-modal">
      <div class="all-titles-header">
        <h2>👥 Toți utilizatorii</h2>
        <button type="button" id="closeAllUsersBtn" aria-label="Închide lista">Închide Lista</button>
      </div>
      <div class="all-users-meta" id="allUsersMeta">—</div>
      <div class="all-titles-table-wrapper">
        <table class="admin-table" id="allUsersTable">
          <thead>
            <tr>
              <th>ID</th>
              <th>Username</th>
              <th>Email</th>
              <th>Rol</th>
              <th>Watchlist</th>
              <th>În așteptare</th>
              <th>Creat la</th>
            </tr>
          </thead>
          <tbody id="allUsersBody"></tbody>
        </table>
        <div class="empty-state" id="allUsersEmpty" style="display:none;">Nu există utilizatori.</div>
      </div>
    </div>
  </div>
  <div id="addMovieModal" class="modal">
    <div class="modal-content">
      <h2>🎬 Adaugă Film Nou</h2>
      <form method="POST" action="index.php">
        <input type="text" name="titlu" placeholder="Titlul filmului *" required>
        <input type="number" name="an_lansare" placeholder="Anul lansării (ex: 2024)" min="1900" max="2099" required>

        <div class="modal-buttons">
          <button type="submit" name="adauga_film">✓ Salvează</button>
          <button type="button" id="cancelBtn">✕ Anulează</button>
        </div>

        <div class="review-alert" id="addMovieConfirmation" role="status" aria-live="polite" style="display:none;">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
          <p>
            Filmul va fi verificat de un administrator înainte de a fi publicat. Vei primi o notificare dacă este aprobat și adăugat în catalog.
          </p>
        </div>
      </form>
    </div>
  </div>

  <div id="globalToast" class="toast-notice" role="status" aria-live="polite">
    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
      <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
    </svg>
    <p id="globalToastMessage">Film trimis spre verificare.</p>
  </div>

  <!-- Modal Detalii Film (pentru popup din search/admin) -->
  <div id="movieDetailsModal" class="modal">
    <div class="movie-details-content">
      <button class="close-details" id="closeDetailsBtn">✕</button>

      <div class="trailer-container" id="trailerContainer">
        <div class="loading-spinner"></div>
      </div>

      <div class="details-header">
        <div class="details-poster" id="detailsPoster">
          <span class="details-title" id="detailsPosterTitle"></span>
        </div>
        <div class="details-info">
          <h1 id="detailsTitle">Se încarcă...</h1>
          <div class="details-meta">
            <span class="meta-item" id="detailsYear">-</span>
            <span class="meta-item" id="detailsGenre">-</span>
            <span class="meta-item" id="detailsRuntime">⏱ -</span>
            <span class="meta-rating" id="detailsRating">-</span>
          </div>

          <div class="add-review-section">
            <h3>📝 Scrie un review</h3>
            <div class="rating-input">
              <label>Rating:</label>
              <div class="star-rating">
                <span class="star" data-rating="1">☆</span>
                <span class="star" data-rating="2">☆</span>
                <span class="star" data-rating="3">☆</span>
                <span class="star" data-rating="4">☆</span>
                <span class="star" data-rating="5">☆</span>
                <span class="star" data-rating="6">☆</span>
                <span class="star" data-rating="7">☆</span>
                <span class="star" data-rating="8">☆</span>
                <span class="star" data-rating="9">☆</span>
                <span class="star" data-rating="10">☆</span>
              </div>
              <span class="rating-value" id="ratingValue">0/10</span>
            </div>
            <textarea id="reviewText" placeholder="Scrie părerea ta despre acest film..." rows="4"></textarea>
            <button class="submit-review-btn" id="submitReviewBtn">Trimite Review</button>
          </div>

          <div class="crew-info">
            <div class="crew-item">
              <span class="crew-label">🎬 Director:</span>
              <span class="crew-value" id="directorName">-</span>
            </div>
            <div class="crew-item">
              <span class="crew-label">🎭 Actori:</span>
              <span class="crew-value" id="actorsList">-</span>
            </div>
          </div>

          <div class="details-actions">
            <button class="detail-btn watchlist-detail-btn" id="detailsWatchlistBtn">
              <span>+ Adaugă în Watchlist</span>
            </button>
          </div>
        </div>
      </div>

      <div class="details-sections">
        <div class="details-section">
          <h3>📊 Statistici</h3>
          <div class="stats-grid">
            <div class="stat-item">
              <span class="stat-label">⭐ Rating IMDB</span>
              <span class="stat-value" id="statRating">-</span>
            </div>
            <div class="stat-item">
              <span class="stat-label">📅 An lansare</span>
              <span class="stat-value" id="statYear">-</span>
            </div>
            <div class="stat-item">
              <span class="stat-label">🎬 Gen</span>
              <span class="stat-value" id="statGenre">-</span>
            </div>
            <div class="stat-item">
              <span class="stat-label">⏱ Durată</span>
              <span class="stat-value" id="statRuntime">-</span>
            </div>
            <div class="stat-item">
              <span class="stat-label">👥 Voturi</span>
              <span class="stat-value" id="statVotes">-</span>
            </div>
          </div>
        </div>

        <div class="details-section">
          <h3>💬 Despre film</h3>
          <p id="aboutMovie" class="about-text">Se încarcă...</p>
        </div>

        <div class="details-section">
          <h3>💭 Review</h3>
          <div id="userReviewsSection" class="user-reviews-section">
            <p style="color: #8b949e;">Se încarcă review-uri...</p>
          </div>
        </div>
      </div>

    </div>
  </div>

  <div id="adminToast" class="toast-notice" role="status" aria-live="polite" style="display:none;">
    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
      <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
    </svg>
    <p id="adminToastMessage">Acțiunea a fost executată.</p>
  </div>
</body>
</html>
