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
  <!-- Lucide Icons -->
  <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body>
  <!-- Ancoră top simplă -->
  <div id="top"></div>

  <header class="main-header">
    <h1 class="logo">🎬 MovieHub</h1>
    <nav>
      <a href="index.php" class="nav-link nav-home active">
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>
      </a>
      <a href="watchlist.php" class="nav-link nav-watchlist">
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m19 21-7-4-7 4V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"></path></svg>
      </a>
      
      <div class="dropdown">
        <a href="#" class="nav-link nav-toprated">
          <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>
        </a>
        <div class="dropdown-menu">
          <a href="top-rated.php?type=movies">Top Rated Movies</a>
          <a href="top-rated.php?type=series">Top Rated Series</a>
        </div>
      </div>      <!-- Search bar cu recomandări -->
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
      
      <button id="addMovieBtn">
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"></path><path d="M12 5v14"></path></svg>
      </button>
      <a href="logout.php" class="logout-btn">
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" x2="9" y1="12" y2="12"></line></svg>
      </a>
    </nav>
  </header>
  <main class="content">
    <h2 class="page-title">🔥 Hot Movies</h2>
    
    <!-- Home cards -->
    <section class="cards-grid">
      <article class="film-card"
               data-title="Inception" data-imdb="tt1375666"
               data-year="2010" data-genre="Sci‑Fi" data-rating="8.8"
               data-description="Dom Cobb este un hoț care fură secrete valoroase din subconștientul oamenilor în timpul somnului, când mintea este cea mai vulnerabilă."
               data-color="#1a1d24" data-trailer-id="YoHD9XEInc0">
        <div class="poster" style="background-color:#1a1d24;"><span class="fallback-title">Inception</span></div>
        <div class="info"><h3>Inception</h3><p>2010 • Sci‑Fi • 8.8/10</p></div>
        <div class="hover"><p>Dom Cobb este un hoț care fură secrete valoroase din subconștientul oamenilor în timpul somnului.</p></div>
      </article>

      <article class="film-card"
               data-title="Interstellar" data-imdb="tt0816692"
               data-year="2014" data-genre="Aventură/SF" data-rating="8.6"
               data-description="O echipă de exploratori călătorește prin o gaură de vierme în spațiu într-o încercare de a asigura supraviețuirea omanității."
               data-color="#1a2536" data-trailer-id="zSWdZVtXT7E">
        <div class="poster" style="background-color:#1a2536;"><span class="fallback-title">Interstellar</span></div>
        <div class="info"><h3>Interstellar</h3><p>2014 • Aventură/SF • 8.6/10</p></div>
        <div class="hover"><p>O echipă de exploratori călătorește prin o gaură de vierme în spațiu.</p></div>
      </article>

      <article class="film-card"
               data-title="The Dark Knight" data-imdb="tt0468569"
               data-year="2008" data-genre="Acțiune" data-rating="9.0"
               data-description="Când amenințarea cunoscută sub numele de Joker face ravagii în Gotham, Batman trebuie să accepte una dintre cele mai mari provocări."
               data-color="#111318" data-trailer-id="EXeTwQWrcwY">
        <div class="poster" style="background-color:#111318;"><span class="fallback-title">The Dark Knight</span></div>
        <div class="info"><h3>The Dark Knight</h3><p>2008 • Acțiune • 9.0/10</p></div>
        <div class="hover"><p>Batman se confruntă cu Joker într-o luptă pentru sufletul orașului Gotham.</p></div>
      </article>

      <article class="film-card"
               data-title="The Matrix" data-imdb="tt0133093"
               data-year="1999" data-genre="SF" data-rating="8.7"
               data-description="Un hacker descoperă adevărul despre realitate și își găsește destinul."
               data-color="#0c1e22" data-trailer-id="vKQi3bBA1y8">
        <div class="poster" style="background-color:#0c1e22;"><span class="fallback-title">The Matrix</span></div>
        <div class="info"><h3>The Matrix</h3><p>1999 • SF • 8.7/10</p></div>
        <div class="hover"><p>Un hacker descoperă adevărul despre realitate și își găsește destinul.</p></div>
      </article>

  <!-- FIX Fight Club: atributul data-description era întrerupt -->
  <article class="film-card"
       data-title="Fight Club" data-imdb="tt0137523"
       data-year="1999" data-genre="Dramă" data-rating="8.8"
       data-description="Un insomniac și un vânzător de săpun pornesc un club subteran."
       data-color="#222222" data-trailer-id="SUXWAEX2jlg">
        <div class="poster" style="background-color:#222222;"><span class="fallback-title">Fight Club</span></div>
        <div class="info"><h3>Fight Club</h3><p>1999 • Dramă • 8.8/10</p></div>
        <div class="hover"><p>Un insomniac și un vânzător de săpun pornesc un club subteran.</p></div>
      </article>

      <article class="film-card"
               data-title="Parasite" data-imdb="tt6751668"
               data-year="2019" data-genre="Thriller" data-rating="8.5"
               data-description="Două familii din lumi diferite se intersectează cu consecințe neașteptate."
               data-color="#252210" data-trailer-id="5xH0HfJHsaY">
        <div class="poster" style="background-color:#252210;"><span class="fallback-title">Parasite</span></div>
        <div class="info"><h3>Parasite</h3><p>2019 • Thriller • 8.5/10</p></div>
        <div class="hover"><p>Două familii din lumi diferite se intersectează cu consecințe neașteptate.</p></div>
      </article>

      <!-- Noi carduri -->
      <article class="film-card"
               data-title="The Godfather" data-imdb="tt0068646"
               data-year="1972" data-genre="Crimă" data-rating="9.2"
               data-description="Saga familiei Corleone în lumea crimei organizate."
               data-color="#1b1b1b" data-trailer-id="UaVTIH8mujA">
        <div class="poster" style="background-color:#1b1b1b;"><span class="fallback-title">The Godfather</span></div>
        <div class="info"><h3>The Godfather</h3><p>1972 • Crimă • 9.2/10</p></div>
        <div class="hover"><p>Saga familiei Corleone în lumea crimei organizate.</p></div>
      </article>

      <article class="film-card"
               data-title="Pulp Fiction" data-imdb="tt0110912"
               data-year="1994" data-genre="Crimă" data-rating="8.9"
               data-description="Povești interconectate despre crimă, răscumpărare și hazul situațiilor."
               data-color="#2a1a1a" data-trailer-id="s7EdQ4FqbhY">
        <div class="poster" style="background-color:#2a1a1a;"><span class="fallback-title">Pulp Fiction</span></div>
        <div class="info"><h3>Pulp Fiction</h3><p>1994 • Crimă • 8.9/10</p></div>
        <div class="hover"><p>Povești interconectate despre crimă, răscumpărare și hazul situațiilor.</p></div>
      </article>

      <article class="film-card"
               data-title="The Shawshank Redemption" data-imdb="tt0111161"
               data-year="1994" data-genre="Dramă" data-rating="9.3"
               data-description="Prietenia a doi deținuți și speranța în mijlocul greutăților."
               data-color="#1b263b" data-trailer-id="6hB3S9bIaco">
        <div class="poster" style="background-color:#1b263b;"><span class="fallback-title">Shawshank Redemption</span></div>
        <div class="info"><h3>The Shawshank Redemption</h3><p>1994 • Dramă • 9.3/10</p></div>
        <div class="hover"><p>Povestea prieteniei a doi deținuți și a speranței.</p></div>
      </article>

      <article class="film-card"
               data-title="Gladiator" data-imdb="tt0172495"
               data-year="2000" data-genre="Acțiune" data-rating="8.5"
               data-description="Un general roman devine gladiator pentru a se răzbuna."
               data-color="#231a14" data-trailer-id="owK1qxDselE">
        <div class="poster" style="background-color:#231a14;"><span class="fallback-title">Gladiator</span></div>
        <div class="info"><h3>Gladiator</h3><p>2000 • Acțiune • 8.5/10</p></div>
        <div class="hover"><p>Un general roman devine gladiator pentru a se răzbuna.</p></div>
      </article>

      <article class="film-card"
               data-title="Joker" data-imdb="tt7286456"
               data-year="2019" data-genre="Dramă" data-rating="8.4"
               data-description="Originea unui personaj emblematic – transformarea în Joker."
               data-color="#1a1f1d" data-trailer-id="zAGVQLHvwOY">
        <div class="poster" style="background-color:#1a1f1d;"><span class="fallback-title">Joker</span></div>
        <div class="info"><h3>Joker</h3><p>2019 • Dramă • 8.4/10</p></div>
        <div class="hover"><p>Originea unui personaj emblematic – transformarea în Joker.</p></div>
      </article>

      <article class="film-card"
               data-title="Forrest Gump" data-imdb="tt0109830"
               data-year="1994" data-genre="Dramă" data-rating="8.8"
               data-description="Povestea extraordinară a unui om simplu care trăiește momente istorice."
               data-color="#1c2428" data-trailer-id="bLvqoHBptjg">
        <div class="poster" style="background-color:#1c2428;"><span class="fallback-title">Forrest Gump</span></div>
        <div class="info"><h3>Forrest Gump</h3><p>1994 • Dramă • 8.8/10</p></div>
        <div class="hover"><p>Povestea extraordinară a unui om simplu care trăiește momente istorice.</p></div>
      </article>

      <article class="film-card"
               data-title="Avatar" data-imdb="tt0499549"
               data-year="2009" data-genre="SF/Aventură" data-rating="7.9"
               data-description="Un paraplecic este trimis pe luna Pandora într-o misiune unică."
               data-color="#0d1f2d" data-trailer-id="5PSNL1qE6VY">
        <div class="poster" style="background-color:#0d1f2d;"><span class="fallback-title">Avatar</span></div>
        <div class="info"><h3>Avatar</h3><p>2009 • SF/Aventură • 7.9/10</p></div>
        <div class="hover"><p>Un paraplecic este trimis pe luna Pandora într-o misiune unică.</p></div>
      </article>

      <article class="film-card"
               data-title="The Prestige" data-imdb="tt0482571"
               data-year="2006" data-genre="Thriller/Mister" data-rating="8.5"
               data-description="Doi magicieni rivali se angajează într-o competiție periculoasă."
               data-color="#1a1410" data-trailer-id="ijXruSzfGEc">
        <div class="poster" style="background-color:#1a1410;"><span class="fallback-title">The Prestige</span></div>
        <div class="info"><h3>The Prestige</h3><p>2006 • Thriller/Mister • 8.5/10</p></div>
        <div class="hover"><p>Doi magicieni rivali se angajează într-o competiție periculoasă.</p></div>
      </article>

      <article class="film-card"
               data-title="Dune: Part Two" data-imdb="tt15239678"
               data-year="2024" data-genre="SF" data-rating="8.6"
               data-description="Paul Atreides își unește forțele cu fremenii pentru a-și răzbuna familia."
               data-color="#0f1d21" data-trailer-id="U2Qp5pL3ovA">
        <div class="poster" style="background-color:#0f1d21;"><span class="fallback-title">Dune: Part Two</span></div>
        <div class="info"><h3>Dune: Part Two</h3><p>2024 • SF • 8.6/10</p></div>
        <div class="hover"><p>Paul Atreides își unește forțele cu fremenii.</p></div>
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

  <!-- Modal Detalii Film -->
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
          <p class="details-description" id="detailsDescription">Se încarcă...</p>
          
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
            <button class="detail-btn share-btn">
              <span>🔗 Share</span>
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
      </div>
    </div>
  </div>

  <!-- Buton simplu Mergi sus -->
  <a href="#top" id="backToTop">Sus</a>
</body>
</html>
