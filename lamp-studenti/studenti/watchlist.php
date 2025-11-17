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
?>
<!DOCTYPE html>
<html lang="ro">
<head>
  <meta charset="UTF-8">
  <title>Watchlist - MovieHub</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
  <header class="main-header">
    <div class="logo">
      <img src="logo.jpeg" alt="MovieHub Logo">
    </div>
    <nav>
      <a href="index.php" class="nav-link">Home</a>
      <a href="watchlist.php" class="nav-link active">Watchlist</a>
      
      <div class="dropdown">
        <a href="#" class="nav-link">Top Rated ▾</a>
        <div class="dropdown-menu">
          <a href="top-rated.php?type=movies">Top Rated Movies</a>
          <a href="top-rated.php?type=series">Top Rated Series</a>
        </div>
      </div>
      
      <input type="text" id="search" placeholder="Caută filme...">
      <button id="addMovieBtn">+ Adaugă film</button>
      <a href="logout.php" class="logout-btn">Logout</a>
    </nav>
  </header>

  <main class="content">
    <h2 class="page-title">📋 Watchlist-ul meu</h2>
    
    <section class="cards-grid">
      <article class="film-card">
        <div class="poster" style="background-color: #2c1810;">
          <span class="fallback-title">Dune: Part Two</span>
        </div>
        <div class="info">
          <h3>Dune: Part Two</h3>
          <p>2024 • Sci-Fi • 8.9/10</p>
        </div>
        <div class="hover">
          <p>Paul Atreides își unește forțele cu Chani pentru a se răzbuna.</p>
        </div>
      </article>

      <article class="film-card">
        <div class="poster" style="background-color: #1a1a2e;">
          <span class="fallback-title">Oppenheimer</span>
        </div>
        <div class="info">
          <h3>Oppenheimer</h3>
          <p>2023 • Biografie • 8.7/10</p>
        </div>
        <div class="hover">
          <p>Povestea fizicianului J. Robert Oppenheimer și bomba atomică.</p>
        </div>
      </article>

      <article class="film-card">
        <div class="poster" style="background-color: #0f3460;">
          <span class="fallback-title">The Batman</span>
        </div>
        <div class="info">
          <h3>The Batman</h3>
          <p>2022 • Acțiune • 8.3/10</p>
        </div>
        <div class="hover">
          <p>Batman investighează corupția din Gotham City.</p>
        </div>
      </article>
    </section>
  </main>
</body>
</html>