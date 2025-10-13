<!DOCTYPE html>
<html lang="ro">
<head>
  <meta charset="UTF-8">
  <title>MovieHub - Filmele mele</title>
  <link rel="stylesheet" href="style.css">
  <script defer src="script.js"></script>
</head>
<body>
  <header class="main-header">
    <h1 class="logo">🎬 MovieHub</h1>
    <nav>
      <input type="text" id="search" placeholder="Caută filme...">
      <button id="addMovieBtn">+ Adaugă film</button>
      <a href="logout.php" class="logout-btn">Logout</a>
    </nav>
  </header>

  <main class="content">
    <section class="movies-grid" id="movieList">
      <!-- Filmele vor fi adăugate dinamic -->
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
</body>
</html>
