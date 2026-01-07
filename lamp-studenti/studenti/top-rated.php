<?php
session_start();

define('REMEMBER_SECRET', 'NZcJe9lFUck5pNBEOhT2yM805nzRRyISKb195KMDHzt2hsg7h2');
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/session_helpers.php';

// Autologin din cookie remember
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

$type = $_GET['type'] ?? 'movies';
$title = $type === 'movies' ? 'Top Rated Movies' : 'Top Rated Series';
$icon = $type === 'movies' ? '🎬' : '📺';

$pdo = db();
hydrateUserSession($pdo);

$currentUserId = $_SESSION['user_id'] ?? null;
$currentUserRole = $_SESSION['user_role'] ?? 'user';
$assetVersionCss = file_exists(__DIR__ . '/style.css') ? filemtime(__DIR__ . '/style.css') : time();
$assetVersionJs = file_exists(__DIR__ . '/script.js') ? filemtime(__DIR__ . '/script.js') : time();
?>
<!DOCTYPE html>
<html lang="ro">
<head>
  <meta charset="UTF-8">
  <title><?php echo $title; ?> - MovieHub</title>
  <link rel="icon" href="data:,">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="style.css?v=<?php echo $assetVersionCss; ?>">
  <script defer src="script.js?v=<?php echo $assetVersionJs; ?>"></script>
</head>
<body data-page="top-rated" data-top-type="<?php echo htmlspecialchars($type, ENT_QUOTES); ?>" data-user-id="<?php echo $currentUserId ?? 0; ?>" data-user-role="<?php echo htmlspecialchars($currentUserRole, ENT_QUOTES); ?>">
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
        <a href="javascript:void(0)" class="nav-link nav-toprated active">
          <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>
        </a>
        <div class="dropdown-menu">
          <a href="top-rated.php?type=movies" <?php if($type === 'movies') echo 'class="active"'; ?>>Top Rated Movies</a>
          <a href="top-rated.php?type=series" <?php if($type === 'series') echo 'class="active"'; ?>>Top Rated Series</a>
        </div>
      </div>
      
      <!-- Search bar cu recomandări -->
      <div class="search-container">
        <input type="text" id="search" placeholder="🔍 Caută filme...">
        <div class="search-suggestions" id="searchSuggestions"></div>
      </div>
      
      <button id="addMovieBtn">
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"></path><path d="M12 5v14"></path></svg>
      </button>
      <?php if ($currentUserRole === 'admin'): ?>
      <a href="admin.php" class="nav-link nav-admin" title="Panou administrator">
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
      </a>
      <?php endif; ?>
      <a href="settings.php" class="nav-link nav-settings">
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"></path><circle cx="12" cy="12" r="3"></circle></svg>
      </a>
      <a href="logout.php" class="logout-btn">
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" x2="9" y1="12" y2="12"></line></svg>
      </a>
    </nav>
  </header>

  <main class="content">
    <h2 class="page-title"><?php echo $icon . ' ' . $title; ?></h2>
    
    <section class="cards-grid" id="topRatedGrid" aria-live="polite">
      <div class="grid-placeholder">Se încarcă titlurile cu cele mai bune ratinguri...</div>
    </section>
  </main>

  <!-- Modal Adaugă Film reutilizat pe toate paginile -->
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

  <!-- Modal Detalii Film (necesar pentru popup la click pe card) -->
  <div id="movieDetailsModal" class="modal">
    <div class="movie-details-content">
      <button class="close-details" id="closeDetailsBtn">✕</button>
      
      <!-- Trailer YouTube -->
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
</body>
</html>