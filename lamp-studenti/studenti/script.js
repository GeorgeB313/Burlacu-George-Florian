document.addEventListener("DOMContentLoaded", () => {
  const addBtn = document.getElementById("addMovieBtn");
  const modal = document.getElementById("movieModal");
  const cancelBtn = document.getElementById("cancelModal");
  const movieList = document.getElementById("movieList");

  addBtn.addEventListener("click", () => modal.style.display = "flex");
  cancelBtn.addEventListener("click", () => modal.style.display = "none");

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
    modal.style.display = "none";
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
});
