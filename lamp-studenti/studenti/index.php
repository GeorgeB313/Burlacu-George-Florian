<?php
session_start();

// aceeași cheie ca în login.php
define('REMEMBER_SECRET', 'NZcJe9lFUck5pNBEOhT2yM805nzRRyISKb195KMDHzt2hsg7h2');

// Dacă nu e sesiune, încercăm cookie remember (permite acces fără login dacă cookie-ul e valid)
if (empty($_SESSION['user']) && !empty($_COOKIE['remember'])) {
    $cookie = base64_decode($_COOKIE['remember']);
    if ($cookie !== false) {
        list($user, $expiry, $hmac) = array_pad(explode('|', $cookie), 3, '');
        $data = $user . '|' . $expiry;
        if ($expiry >= time() && hash_equals(hash_hmac('sha256', $data, REMEMBER_SECRET), $hmac)) {
            session_regenerate_id(true);
            $_SESSION['user'] = $user;
            // continua afișarea paginii
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
  <title>MovieHub - Home</title>
  <link rel="stylesheet" href="style.css">
  <script defer src="script.js"></script>
</head>
<body>
  <header class="main-header">
    <h1 class="logo">🎬 MovieHub</h1>
    <nav>
      <a href="index.php" class="nav-link active">Home</a>
      <a href="watchlist.php" class="nav-link">Watchlist</a>
      
      <div class="dropdown">
        <a href="#" class="nav-link">Top Rated ▾</a>
        <div class="dropdown-menu">
          <a href="top-rated.php?type=movies">Top Rated Movies</a>
          <a href="top-rated.php?type=series">Top Rated Series</a>
        </div>
      </div>
      
      <!-- Search bar cu recomandări -->
      <div class="search-container">
        <input type="text" id="search" placeholder="🔍 Caută filme...">
        <div class="search-suggestions" id="searchSuggestions">
          <div class="suggestion-item" data-movie="Inception">
            <span class="suggestion-icon">🎬</span>
            <span class="suggestion-text">Inception</span>
            <span class="suggestion-year">2010</span>
          </div>
          <div class="suggestion-item" data-movie="Interstellar">
            <span class="suggestion-icon">🌌</span>
            <span class="suggestion-text">Interstellar</span>
            <span class="suggestion-year">2014</span>
          </div>
          <div class="suggestion-item" data-movie="The Dark Knight">
            <span class="suggestion-icon">🦇</span>
            <span class="suggestion-text">The Dark Knight</span>
            <span class="suggestion-year">2008</span>
          </div>
          <div class="suggestion-item" data-movie="The Matrix">
            <span class="suggestion-icon">💊</span>
            <span class="suggestion-text">The Matrix</span>
            <span class="suggestion-year">1999</span>
          </div>
          <div class="suggestion-item" data-movie="Fight Club">
            <span class="suggestion-icon">👊</span>
            <span class="suggestion-text">Fight Club</span>
            <span class="suggestion-year">1999</span>
          </div>
          <div class="suggestion-item" data-movie="Parasite">
            <span class="suggestion-icon">🏆</span>
            <span class="suggestion-text">Parasite</span>
            <span class="suggestion-year">2019</span>
          </div>
        </div>
      </div>
      
      <button id="addMovieBtn">+ Adaugă film</button>
      <a href="logout.php" class="logout-btn">Logout</a>
    </nav>
  </header>

  <main class="content">
    <h2 class="page-title">🔥 Hot Movies</h2>
    
    <!-- Home cards -->
    <section class="cards-grid">
      <!-- Card 1 -->
      <article class="film-card">
        <div class="poster" style="background-color: #1a1d24;">
          <span class="fallback-title">Inception</span>
        </div>
        <div class="info">
          <h3>Inception</h3>
          <p>2010 • Sci‑Fi • 8.8/10</p>
        </div>
        <div class="hover">
          <p>Heist SF în lumea viselor, regizat de Christopher Nolan.</p>
        </div>
      </article>

      <!-- Card 2 -->
      <article class="film-card">
        <div class="poster" style="background-color: #1a2536;">
          <span class="fallback-title">Interstellar</span>
        </div>
        <div class="info">
          <h3>Interstellar</h3>
          <p>2014 • Aventură/SF • 8.6/10</p>
        </div>
        <div class="hover">
          <p>O echipă pornește într-o misiune printr-o gaură de vierme pentru a salva omenirea.</p>
        </div>
      </article>

      <!-- Card 3 -->
      <article class="film-card">
        <div class="poster" style="background-color: #111318;">
          <span class="fallback-title">The Dark Knight</span>
        </div>
        <div class="info">
          <h3>The Dark Knight</h3>
          <p>2008 • Acțiune • 9.0/10</p>
        </div>
        <div class="hover">
          <p>Batman se confruntă cu Joker într-o luptă pentru sufletul orașului Gotham.</p>
        </div>
      </article>

      <!-- Card 4 -->
      <article class="film-card">
        <div class="poster" style="background-color: #0c1e22;">
          <span class="fallback-title">The Matrix</span>
        </div>
        <div class="info">
          <h3>The Matrix</h3>
          <p>1999 • SF • 8.7/10</p>
        </div>
        <div class="hover">
          <p>Un hacker descoperă adevărul despre realitate și își găsește destinul.</p>
        </div>
      </article>

      <!-- Card 5 -->
      <article class="film-card">
        <div class="poster" style="background-color: #222222;">
          <span class="fallback-title">Fight Club</span>
        </div>
        <div class="info">
          <h3>Fight Club</h3>
          <p>1999 • Dramă • 8.8/10</p>
        </div>
        <div class="hover">
          <p>Un insomniac și un vânzător de săpun pornesc un club subteran.</p>
        </div>
      </article>

      <!-- Card 6 -->
      <article class="film-card">
        <div class="poster" style="background-color: #252210;">
          <span class="fallback-title">Parasite</span>
        </div>
        <div class="info">
          <h3>Parasite</h3>
          <p>2019 • Thriller • 8.5/10</p>
        </div>
        <div class="hover">
          <p>Două familii din lumi diferite se intersectează cu consecințe neașteptate.</p>
        </div>
      </article>
    </section>
  </main>

  <!-- Modal pentru adăugare film -->
  <div class="modal" id="movieModal">
    <div class="modal-content">
      <h2>Adaugă film</h2>
      <form id="movieForm">
        <input type="text" id="title" placeholder="Titlu" required>
        <input type="text" id="director" placeholder="Regizor">
        <input type="number" id="year" placeholder="Anul lansării">
        <input type="number" id="rating" placeholder="Rating (0-10)" min="0" max="10" step="0.1">
        <textarea id="description" placeholder="Descriere scurtă"></textarea>
        <button type="submit">Salvează</button>
        <button type="button" class="cancel-btn" id="cancelModal">Anulează</button>
      </form>
    </div>
  </div>

  <!-- Modal Adaugă Film -->
  <div id="addMovieModal" class="modal">
    <div class="modal-content">
      <h2>🎬 Adaugă Film Nou</h2>
      <form method="POST" action="index.php">
        <input type="text" name="titlu" placeholder="Titlu film *" required>
        <input type="text" name="regizor" placeholder="Regizor" required>
        <input type="number" name="an_lansare" placeholder="Anul lansării (ex: 2024)" min="1900" max="2099" required>
        <input type="number" name="rating" placeholder="Rating (0-10)" step="0.1" min="0" max="10" required>
        <textarea name="descriere" placeholder="Descriere scurtă a filmului" required></textarea>
        
        <div class="modal-buttons">
          <button type="submit" name="adauga_film">✓ Salvează</button>
          <button type="button" id="cancelBtn">✕ Anulează</button>
        </div>
      </form>
    </div>
  </div>
</body>
</html>
