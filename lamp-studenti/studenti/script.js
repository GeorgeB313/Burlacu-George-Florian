document.addEventListener("DOMContentLoaded", () => {
  const addBtn = document.getElementById("addMovieBtn");
  const modal = document.getElementById("addMovieModal");
  const cancelBtn = document.getElementById("cancelBtn");
  const movieList = document.getElementById("movieList");

  addBtn.addEventListener("click", () => modal.classList.add("active"));
  cancelBtn.addEventListener("click", () => modal.classList.remove("active"));

  // Închide modal la click în afara lui
  modal.addEventListener("click", (e) => {
    if (e.target === modal) {
      modal.classList.remove("active");
    }
  });

  // Închide modal cu tasta ESC
  document.addEventListener("keydown", (e) => {
    if (e.key === "Escape" && modal.classList.contains("active")) {
      modal.classList.remove("active");
    }
  });

  // Exemplu simplu: salvăm filmele local (temporar)
  const form = document.getElementById("movieForm");
  form.addEventListener("submit", (e) => {
    e.preventDefault();
    const movie = {
      title: form.title.value,
      director: form.director.value,
      year: form.year.value,
      rating: form.rating.value,
      description: form.description.value
    };
    addMovieCard(movie);
    form.reset();
    modal.classList.remove("active");
  });

  function addMovieCard(movie) {
    const card = document.createElement("div");
    card.className = "movie-card";
    card.innerHTML = `
      <h3>${movie.title}</h3>
      <p>${movie.director ? `Regizor: ${movie.director}` : ""}</p>
      <p>${movie.year ? `An: ${movie.year}` : ""}</p>
      <p>${movie.rating ? `⭐ ${movie.rating}/10` : ""}</p>
      <p>${movie.description}</p>
    `;
    movieList.prepend(card);
  }

  // ---------- Search cu recomandări ----------
  const searchInput = document.getElementById('search');
  const suggestions = document.getElementById('searchSuggestions');
  
  if (searchInput && suggestions) {
    // Click pe o recomandare
    document.querySelectorAll('.suggestion-item').forEach(item => {
      item.addEventListener('click', function() {
        const movieName = this.dataset.movie;
        searchInput.value = movieName;
        
        // Scroll la filmul respectiv (dacă există pe pagină)
        const cards = document.querySelectorAll('.film-card');
        cards.forEach(card => {
          const title = card.querySelector('h3').textContent;
          if (title === movieName) {
            card.scrollIntoView({ behavior: 'smooth', block: 'center' });
            card.style.animation = 'highlight 1s ease';
          }
        });
        
        // Ascunde sugestiile
        searchInput.blur();
      });
    });
    
    // Filtrare recomandări în timp real
    searchInput.addEventListener('input', function() {
      const searchTerm = this.value.toLowerCase();
      const items = document.querySelectorAll('.suggestion-item');
      
      items.forEach(item => {
        const movieName = item.dataset.movie.toLowerCase();
        if (movieName.includes(searchTerm) || searchTerm === '') {
          item.style.display = 'flex';
        } else {
          item.style.display = 'none';
        }
      });
    });
    
    // Închide sugestiile când dai click în afara lor
    document.addEventListener('click', function(e) {
      if (!searchInput.contains(e.target) && !suggestions.contains(e.target)) {
        searchInput.blur();
      }
    });
  }

  // Animație highlight pentru card-ul găsit
  const style = document.createElement('style');
  style.textContent = `
    @keyframes highlight {
      0%, 100% { transform: scale(1); box-shadow: 0 8px 24px rgba(0,0,0,0.3); }
      50% { transform: scale(1.05); box-shadow: 0 12px 32px rgba(229, 185, 10, 0.5); }
    }
  `;
  document.head.appendChild(style);
});
