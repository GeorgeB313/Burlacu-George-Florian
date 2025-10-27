<?php
session_start();

define('REMEMBER_SECRET', 'NZcJe9lFUck5pNBEOhT2yM805nzRRyISKb195KMDHzt2hsg7h2');

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
?>
<!DOCTYPE html>
<html lang="ro">
<head>
  <meta charset="UTF-8">
  <title><?php echo $title; ?> - MovieHub</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
  <header class="main-header">
    <h1 class="logo">🎬 MovieHub</h1>
    <nav>
      <a href="index.php" class="nav-link">Home</a>
      <a href="watchlist.php" class="nav-link">Watchlist</a>
      
      <div class="dropdown">
        <a href="#" class="nav-link active">Top Rated ▾</a>
        <div class="dropdown-menu">
          <a href="top-rated.php?type=movies" <?php if($type === 'movies') echo 'class="active"'; ?>>Top Rated Movies</a>
          <a href="top-rated.php?type=series" <?php if($type === 'series') echo 'class="active"'; ?>>Top Rated Series</a>
        </div>
      </div>
      
      <input type="text" id="search" placeholder="Caută filme...">
      <button id="addMovieBtn">+ Adaugă film</button>
      <a href="logout.php" class="logout-btn">Logout</a>
    </nav>
  </header>

  <main class="content">
    <h2 class="page-title"><?php echo $icon . ' ' . $title; ?></h2>
    
    <section class="cards-grid">
      <?php if ($type === 'movies'): ?>
        <!-- Top Rated Movies -->
        <article class="film-card">
          <div class="poster" style="background-color: #2d1b00;">
            <span class="fallback-title">The Shawshank Redemption</span>
          </div>
          <div class="info">
            <h3>The Shawshank Redemption</h3>
            <p>1994 • Dramă • 9.3/10</p>
          </div>
          <div class="hover">
            <p>Povestea unei prietenii în închisoare și a speranței.</p>
          </div>
        </article>

        <article class="film-card">
          <div class="poster" style="background-color: #1a1a1a;">
            <span class="fallback-title">The Godfather</span>
          </div>
          <div class="info">
            <h3>The Godfather</h3>
            <p>1972 • Crimă • 9.2/10</p>
          </div>
          <div class="hover">
            <p>Povestea familiei mafiei Corleone.</p>
          </div>
        </article>

        <article class="film-card">
          <div class="poster" style="background-color: #0d1117;">
            <span class="fallback-title">The Dark Knight</span>
          </div>
          <div class="info">
            <h3>The Dark Knight</h3>
            <p>2008 • Acțiune • 9.0/10</p>
          </div>
          <div class="hover">
            <p>Batman contra Joker.</p>
          </div>
        </article>

      <?php else: ?>
        <!-- Top Rated Series -->
        <article class="film-card">
          <div class="poster" style="background-color: #1c1c1c;">
            <span class="fallback-title">Breaking Bad</span>
          </div>
          <div class="info">
            <h3>Breaking Bad</h3>
            <p>2008-2013 • Crimă • 9.5/10</p>
          </div>
          <div class="hover">
            <p>Un profesor de chimie devine producător de metamfetamină.</p>
          </div>
        </article>

        <article class="film-card">
          <div class="poster" style="background-color: #2a1810;">
            <span class="fallback-title">Game of Thrones</span>
          </div>
          <div class="info">
            <h3>Game of Thrones</h3>
            <p>2011-2019 • Fantasy • 9.2/10</p>
          </div>
          <div class="hover">
            <p>Luptă pentru Tronul de Fier în Westeros.</p>
          </div>
        </article>

        <article class="film-card">
          <div class="poster" style="background-color: #1a0f0f;">
            <span class="fallback-title">The Last of Us</span>
          </div>
          <div class="info">
            <h3>The Last of Us</h3>
            <p>2023 • Post-apocaliptic • 8.9/10</p>
          </div>
          <div class="hover">
            <p>Joel și Ellie supraviețuiesc într-o lume post-apocaliptică.</p>
          </div>
        </article>
      <?php endif; ?>
    </section>
  </main>
</body>
</html>