document.addEventListener('DOMContentLoaded', () => {
  // ================== CHEI API ==================
  const TMDB_API_KEY = 'c155c2567ed5d4f60645bdcbaf286670';      // https://www.themoviedb.org/settings/api
  const YOUTUBE_API_KEY = 'AIzaSyC7SNpgFU5inyLUHc6GQYnIwvZJLkrDzIg';// https://console.cloud.google.com/apis/library/youtube.googleapis.com
  const POSTER_BASE = 'https://image.tmdb.org/t/p/w500';
  const BACKDROP_BASE = 'https://image.tmdb.org/t/p/w1280';

  // Fallback trailere (opțional)
  const fallbackTrailerIds = {
    'Inception': 'YoHD9XEInc0',
    'Interstellar': 'zSWdZVtXT7E',
    'The Dark Knight': 'EXeTwQWrcwY',
    'The Matrix': 'vKQi3bBA1y8',
    'Fight Club': 'SUXWAEX2jlg',
    'Parasite': '5xH0HfJHsaY'
  };

  const apiBase = '/api';
  const pageContext = document.body?.dataset?.page || 'home';
  const userId = Number(document.body?.dataset?.userId || 0);
  const userRole = document.body?.dataset?.userRole || 'user';
  const topRatedType = document.body?.dataset?.topType || 'movies';
  const movieCache = new Map();
  let activeCardRef = null;
  let syncWatchlistButtonState = () => {};
  let defaultSuggestionCache = null;
  let defaultSuggestionsLoading = false;
  const tabInstanceId = `${Date.now().toString(36)}-${Math.random().toString(36).slice(2, 8)}`;
  const WATCHLIST_SYNC_KEY = 'moviehub-watchlist-sync';
  let broadcastChannel = null;
  if (typeof BroadcastChannel !== 'undefined') {
    try {
      broadcastChannel = new BroadcastChannel(WATCHLIST_SYNC_KEY);
    } catch (err) {
      console.warn('BroadcastChannel unavailable, falling back to storage events.', err);
      broadcastChannel = null;
    }
  }

  const escapeHtml = (value = '') => String(value)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');

  const metaFromMovie = (movie) => ({
    movieId: movie.id,
    title: movie.title,
    imdb: movie.imdb_id,
    tmdb: movie.tmdb_id,
    year: movie.release_year,
    color: movie.accent_color,
    description: movie.overview,
  });

  const jsonFetch = async (url, options = {}) => {
    const config = { credentials: 'same-origin', ...options };
    config.headers = { Accept: 'application/json', ...(options.headers || {}) };
    const response = await fetch(url, config);
    if (!response.ok) {
      let message = 'Eroare la interogarea serverului';
      try {
        const payload = await response.json();
        message = payload.error || payload.message || message;
      } catch (_) {}
      throw new Error(message);
    }
    return response.json();
  };

  const buildFilmCardMarkup = (movie) => {
    const rating = movie.rating_average ? Number(movie.rating_average).toFixed(1) : '—';
    const genre = movie.genres ? movie.genres.split(',')[0].trim() : 'Gen necunoscut';
    const year = movie.release_year || '—';
    const hoverText = movie.overview ? `${movie.overview.slice(0, 180)}${movie.overview.length > 180 ? '…' : ''}` : 'Descoperă mai multe detalii în fereastra de informații.';
    const accent = movie.accent_color || '#0d1117';

    const category = movie.category === 'series' ? 'series' : 'movie';
    const posterUrl = movie.poster_url ? String(movie.poster_url) : '';
    const posterStyle = posterUrl
      ? `background-color:${accent};background-image:url('${escapeHtml(posterUrl)}');background-size:cover;background-position:center;`
      : `background-color:${accent};`;

    return `
      <article class="film-card"
        data-movie-id="${movie.id || ''}"
        data-category="${category}"
        data-title="${escapeHtml(movie.title || '')}"
        data-imdb="${movie.imdb_id || ''}"
        data-tmdb="${movie.tmdb_id || ''}"
        data-year="${year}"
        data-genre="${escapeHtml(genre)}"
        data-rating="${rating !== '—' ? rating : ''}"
        data-description="${escapeHtml(movie.overview || '')}"
        data-color="${accent}"
        data-poster-url="${escapeHtml(posterUrl)}"
        data-in-watchlist="${movie.in_watchlist ? '1' : '0'}"
      >
        <div class="poster" style="${posterStyle}">
          <span class="fallback-title">${escapeHtml(movie.title || '')}</span>
        </div>
        <div class="info">
          <h3>${escapeHtml(movie.title || '')}</h3>
          <p>${year} • ${escapeHtml(genre)} • ${rating}/10</p>
        </div>
        <div class="hover">
          <p>${escapeHtml(hoverText)}</p>
        </div>
      </article>
    `;
  };

  const renderMovieGrid = (grid, movies, emptyMessage, options = {}) => {
    if (!grid) return;
    const { padToRow = 0 } = options;
    if (!movies?.length) {
      grid.innerHTML = `<div class="grid-placeholder empty">${emptyMessage}</div>`;
      return;
    }

    let markup = movies.map((movie) => {
      if (typeof movie.in_watchlist === 'undefined') {
        movie.in_watchlist = false;
      }
      movieCache.set(movie.id, movie);
      return buildFilmCardMarkup(movie);
    }).join('');

    if (padToRow > 1) {
      const remainder = movies.length % padToRow;
      if (remainder) {
        const placeholdersNeeded = padToRow - remainder;
        const placeholderMarkup = '<article class="film-card placeholder" data-placeholder="1" aria-hidden="true"></article>';
        markup += placeholderMarkup.repeat(placeholdersNeeded);
      }
    }

    grid.innerHTML = markup;

    wireFilmCards(grid);
    hydrateCardPosters(grid);
  };

  const showGridError = (grid, message) => {
    if (!grid) return;
    grid.innerHTML = `<div class="grid-placeholder error">${escapeHtml(message)}</div>`;
  };

  const loadHomeGrid = async () => {
    const grid = document.getElementById('trendingGrid');
    if (!grid) return;
    try {
      const { data } = await jsonFetch(`${apiBase}/movies.php?scope=trending&limit=12`);
      renderMovieGrid(grid, data, 'Încă nu avem filme populare disponibile.');
    } catch (err) {
      showGridError(grid, err.message);
    }
  };

  const loadTopRatedGrid = async () => {
    const grid = document.getElementById('topRatedGrid');
    if (!grid) return;
    const typeParam = topRatedType === 'series' ? 'series' : 'movie';
    try {
      const { data } = await jsonFetch(`${apiBase}/movies.php?scope=top-rated&type=${typeParam}&limit=50`);
      const emptyMessage = typeParam === 'series'
        ? 'Încă nu avem seriale în baza de date. Adaugă câteva din panoul de admin.'
        : 'Nu s-au găsit filme cu rating înalt.';
      renderMovieGrid(grid, data, emptyMessage, { padToRow: 3 });
    } catch (err) {
      showGridError(grid, err.message);
    }
  };

  const loadWatchlistGrid = async () => {
    const grid = document.getElementById('watchlistGrid');
    if (!grid) return;

    if (!userId) {
      showGridError(grid, 'Autentifică-te pentru a-ți vedea watchlist-ul.');
      return;
    }

    try {
      const { data } = await jsonFetch(`${apiBase}/watchlist.php`);
      const normalized = data.map(item => ({
        id: item.movie_id,
        tmdb_id: item.tmdb_id,
        imdb_id: item.imdb_id,
        title: item.title,
        release_year: item.release_year,
        genres: item.genres,
        rating_average: item.rating_average,
        overview: item.overview,
        accent_color: item.accent_color,
        category: item.category === 'series' ? 'series' : 'movie',
        poster_url: item.poster_url,
        in_watchlist: true,
      }));
      renderMovieGrid(grid, normalized, 'Watchlist-ul tău este gol. Adaugă filme din lista principală.');
    } catch (err) {
      showGridError(grid, err.message);
    }
  };

  const bootstrapPageData = () => {
    if (pageContext === 'home') {
      loadHomeGrid();
    } else if (pageContext === 'top-rated') {
      loadTopRatedGrid();
    } else if (pageContext === 'watchlist') {
      loadWatchlistGrid();
    }
  };

  const wireFilmCards = (scope = document) => {
    scope.querySelectorAll('.film-card').forEach(card => {
      if (card.dataset.placeholder === '1') {
        return;
      }
      if (card.dataset.bound === '1') return;
      card.dataset.bound = '1';
      card.addEventListener('click', () => handleFilmCardSelection(card));
    });
  };

  // ---------- UI ----------
  const detailsModal = document.getElementById('movieDetailsModal');
  const closeDetailsBtn = document.getElementById('closeDetailsBtn');
  const watchlistBtn = document.getElementById('detailsWatchlistBtn');
  const addMovieBtn = document.getElementById('addMovieBtn');
  const addMovieModal = document.getElementById('addMovieModal');
  const cancelAddMovieBtn = document.getElementById('cancelBtn');
  const adminToast = document.getElementById('adminToast');
  const adminToastMessage = document.getElementById('adminToastMessage');
  const totalMoviesCard = document.getElementById('totalMoviesCard');
  const allTitlesModal = document.getElementById('allTitlesModal');
  const closeAllTitlesBtn = document.getElementById('closeAllTitlesBtn');
  const allTitlesSearch = document.getElementById('allTitlesSearch');
  const allTitlesStatusFilter = document.getElementById('allTitlesStatusFilter');
  const allTitlesCategoryFilter = document.getElementById('allTitlesCategoryFilter');
  const allTitlesBody = document.getElementById('allTitlesBody');
  const allTitlesMeta = document.getElementById('allTitlesMeta');
  const allTitlesEmpty = document.getElementById('allTitlesEmpty');
  const allTitlesTable = document.getElementById('allTitlesTable');
  const allTitlesPrev = document.getElementById('allTitlesPrev');
  const allTitlesNext = document.getElementById('allTitlesNext');
  const allTitlesPage = document.getElementById('allTitlesPage');
  const usersCard = document.getElementById('usersCard');
  const allUsersModal = document.getElementById('allUsersModal');
  const closeAllUsersBtn = document.getElementById('closeAllUsersBtn');
  const allUsersBody = document.getElementById('allUsersBody');
  const allUsersEmpty = document.getElementById('allUsersEmpty');
  const allUsersTable = document.getElementById('allUsersTable');
  const allUsersMeta = document.getElementById('allUsersMeta');

  const refreshBodyScrollLock = () => {
    const hasActiveModal = document.querySelector('.modal.active');
    document.body.style.overflow = hasActiveModal ? 'hidden' : 'auto';
  };

  const showAddMovieConfirmation = (message) => {
    const toast = document.getElementById('globalToast');
    const toastMessage = document.getElementById('globalToastMessage');
    if (!toast || !toastMessage) return;
    toastMessage.textContent = message;
    toast.classList.add('show');
    setTimeout(() => toast.classList.remove('show'), 4000);
  };

  const refreshWatchlistViewIfNeeded = async () => {
    if (pageContext === 'watchlist') {
      await loadWatchlistGrid();
    }
  };

  const updateAllCardsWatchlistState = (movieId, state) => {
    const numericId = Number(movieId);
    if (!numericId) return;
    const normalizedState = state ? '1' : '0';
    document.querySelectorAll(`.film-card[data-movie-id="${numericId}"]`).forEach((card) => {
      card.dataset.inWatchlist = normalizedState;
      if (card === activeCardRef && typeof syncWatchlistButtonState === 'function') {
        syncWatchlistButtonState(card);
      }
    });

    if (movieCache.has(numericId)) {
      const cached = movieCache.get(numericId);
      movieCache.set(numericId, { ...cached, in_watchlist: state });
    }
  };

  const emitWatchlistSync = (movieId, state) => {
    if (!movieId) return;
    const payload = {
      movieId: Number(movieId),
      inWatchlist: Boolean(state),
      originId: tabInstanceId,
      timestamp: Date.now(),
    };

    if (broadcastChannel) {
      broadcastChannel.postMessage(payload);
    } else {
      try {
        localStorage.setItem(WATCHLIST_SYNC_KEY, JSON.stringify(payload));
        // Removing the key triggers the storage event in other tabs while keeping local storage clean
        localStorage.removeItem(WATCHLIST_SYNC_KEY);
      } catch (_) {}
    }
  };

  const handleIncomingWatchlistSync = (payload) => {
    if (!payload || payload.originId === tabInstanceId) {
      return;
    }
    const { movieId, inWatchlist } = payload;
    updateAllCardsWatchlistState(movieId, inWatchlist);
    if (pageContext === 'watchlist') {
      loadWatchlistGrid();
    }
  };

  if (broadcastChannel) {
    broadcastChannel.addEventListener('message', (event) => handleIncomingWatchlistSync(event.data));
  } else {
    window.addEventListener('storage', (event) => {
      if (event.key === WATCHLIST_SYNC_KEY && event.newValue) {
        try {
          const payload = JSON.parse(event.newValue);
          handleIncomingWatchlistSync(payload);
        } catch (_) {}
      }
    });
  }

  function setText(id, val) { const el = document.getElementById(id); if (el) el.textContent = val; }
  function showLoading() {
    setText('detailsTitle', 'Se încarcă...');
    setText('aboutMovie', 'Așteptați...');
    setText('detailsYear', '-'); setText('detailsGenre', '-'); setText('detailsRuntime', '⏱ -'); setText('detailsRating', '-');
    setText('directorName', '-'); setText('actorsList', '-');
    setText('statRating', '-'); setText('statYear', '-'); setText('statGenre', '-'); setText('statRuntime', '-'); setText('statVotes', '-');
    const tc = document.getElementById('trailerContainer'); if (tc) tc.innerHTML = '<div class="loading-spinner"></div>';
  }
  function openModal() { if (!detailsModal) return; detailsModal.classList.add('active'); refreshBodyScrollLock(); }
  function closeModal() { if (!detailsModal) return; detailsModal.classList.remove('active'); refreshBodyScrollLock(); const tc = document.getElementById('trailerContainer'); if (tc) tc.innerHTML = ''; }
  if (closeDetailsBtn) closeDetailsBtn.addEventListener('click', closeModal);
  if (detailsModal) detailsModal.addEventListener('click', e => { if (e.target === detailsModal) closeModal(); });
  document.addEventListener('keydown', e => { if (e.key === 'Escape' && detailsModal?.classList.contains('active')) closeModal(); });

  if (addMovieBtn && addMovieModal) {
    const openAddModal = () => {
      addMovieModal.classList.add('active');
      refreshBodyScrollLock();
      const firstInput = addMovieModal.querySelector('input, textarea');
      firstInput?.focus();
    };

    const closeAddModal = () => {
      addMovieModal.classList.remove('active');
      refreshBodyScrollLock();
    };

    addMovieBtn.addEventListener('click', (e) => {
      e.preventDefault();
      openAddModal();
    });

    if (cancelAddMovieBtn) {
      cancelAddMovieBtn.addEventListener('click', (e) => {
        e.preventDefault();
        closeAddModal();
      });
    }

    addMovieModal.addEventListener('click', (e) => {
      if (e.target === addMovieModal) closeAddModal();
    });

    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && addMovieModal.classList.contains('active')) {
        closeAddModal();
      }
    });

    const addMovieForm = addMovieModal.querySelector('form');
    if (addMovieForm) {
      addMovieForm.addEventListener('submit', (e) => {
        e.preventDefault();
        const submitBtn = addMovieForm.querySelector('button[type="submit"]');
        const formData = new FormData(addMovieForm);
        const payload = {
          title: formData.get('titlu')?.toString().trim() || '',
          director: '',
          year: formData.get('an_lansare') ? Number(formData.get('an_lansare')) : null,
          rating: null,
          description: '',
        };

        const sendProposal = async () => {
          submitBtn?.classList.add('loading');
          try {
            await jsonFetch(`${apiBase}/submit_movie.php`, {
              method: 'POST',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify(payload),
            });
            showAddMovieConfirmation('Film trimis spre verificare. Un administrator îl va analiza înainte de publicare.');
            addMovieForm.reset();
            closeAddModal();
          } catch (err) {
            showAddMovieConfirmation(err.message || 'Nu am putut trimite propunerea.', 'error');
          } finally {
            submitBtn?.classList.remove('loading');
          }
        };

        sendProposal();
      });
    }
  }

  // ---------- TMDb helpers ----------
  async function tmdbFindByImdb(imdbId) {
    if (!imdbId) return null;
    const url = new URL(`https://api.themoviedb.org/3/find/${encodeURIComponent(imdbId)}`);
    url.searchParams.set('api_key', TMDB_API_KEY);
    url.searchParams.set('external_source', 'imdb_id');
    const r = await fetch(url.toString());
    if (!r.ok) throw new Error('TMDb find error');
    const j = await r.json();
    return (j.movie_results || [])[0] || null;
  }
  async function tmdbSearch(title, year) {
    const url = new URL('https://api.themoviedb.org/3/search/movie');
    url.searchParams.set('api_key', TMDB_API_KEY);
    url.searchParams.set('query', title);
    url.searchParams.set('include_adult', 'false');
    if (year) url.searchParams.set('year', year);
    const r = await fetch(url.toString());
    if (!r.ok) throw new Error('TMDb search error');
    const j = await r.json();
    return j.results || [];
  }

  async function tmdbTvSearch(title, year) {
    const url = new URL('https://api.themoviedb.org/3/search/tv');
    url.searchParams.set('api_key', TMDB_API_KEY);
    url.searchParams.set('query', title);
    url.searchParams.set('include_adult', 'false');
    if (year) url.searchParams.set('first_air_date_year', year);
    const r = await fetch(url.toString());
    if (!r.ok) throw new Error('TMDb search error');
    const j = await r.json();
    return j.results || [];
  }

  async function tmdbDetailsGeneric(id, mediaType = 'movie', lang = 'ro-RO') {
    const path = mediaType === 'tv' ? 'tv' : 'movie';
    const url = new URL(`https://api.themoviedb.org/3/${path}/${id}`);
    url.searchParams.set('api_key', TMDB_API_KEY);
    url.searchParams.set('append_to_response', 'credits,videos');
    url.searchParams.set('language', lang);
    url.searchParams.set('include_video_language', 'en,null,ro-RO');
    const r = await fetch(url.toString());
    if (!r.ok) throw new Error('TMDb details error');
    const d = await r.json();

    const titleField = mediaType === 'tv' ? 'name' : 'title';
    const originalTitleField = mediaType === 'tv' ? 'original_name' : 'original_title';
    const overviewEmpty = !d.overview || String(d.overview).trim() === '';
    const titleEmpty = !d[titleField] || String(d[titleField]).trim() === '';

    if (lang === 'ro-RO' && (overviewEmpty || titleEmpty)) {
      try {
        const en = await tmdbDetailsGeneric(id, mediaType, 'en-US');
        d.overview = d.overview || en.overview;
        d[titleField] = d[titleField] || en[titleField] || en[originalTitleField];
        d.genres = d.genres?.length ? d.genres : en.genres;
        if (mediaType === 'tv') {
          d.episode_run_time = (d.episode_run_time?.length ? d.episode_run_time : en.episode_run_time);
        } else {
          d.runtime = d.runtime || en.runtime;
        }
        d.vote_average = d.vote_average || en.vote_average;
        d.vote_count = d.vote_count || en.vote_count;
        d.credits = d.credits?.cast?.length ? d.credits : en.credits;
        d.videos = d.videos?.results?.length ? d.videos : en.videos;
      } catch {}
    }

    return d;
  }
  // Detalii cu fallback de limbă + includere video languages
  async function tmdbDetails(id, lang = 'ro-RO') {
    return tmdbDetailsGeneric(id, 'movie', lang);
  }

  async function tmdbTvDetails(id, lang = 'ro-RO') {
    return tmdbDetailsGeneric(id, 'tv', lang);
  }
  
  // Funcție pentru a obține review-uri
  async function tmdbReviews(id) {
    return tmdbReviewsGeneric(id, 'movie');
  }

  async function tmdbReviewsGeneric(id, mediaType = 'movie') {
    const path = mediaType === 'tv' ? 'tv' : 'movie';
    const url = new URL(`https://api.themoviedb.org/3/${path}/${id}/reviews`);
    url.searchParams.set('api_key', TMDB_API_KEY);
    url.searchParams.set('language', 'en-US');
    const r = await fetch(url.toString());
    if (!r.ok) throw new Error('TMDb reviews error');
    const j = await r.json();
    return j.results || [];
  }
  
  async function tmdbVideos(id, lang) {
    return tmdbVideosGeneric(id, 'movie', lang);
  }

  async function tmdbVideosGeneric(id, mediaType = 'movie', lang) {
    const path = mediaType === 'tv' ? 'tv' : 'movie';
    const url = new URL(`https://api.themoviedb.org/3/${path}/${id}/videos`);
    url.searchParams.set('api_key', TMDB_API_KEY);
    if (lang) url.searchParams.set('language', lang);
    url.searchParams.set('include_video_language', 'en,null,ro-RO');
    const r = await fetch(url.toString());
    if (!r.ok) return [];
    const j = await r.json();
    return j.results || [];
  }
  function pickTrailerKey(arr = []) {
    const yt = arr.filter(v => (v.site || '').toLowerCase() === 'youtube');
    const t1 = yt.find(v => (v.type || '').toLowerCase() === 'trailer' && v.official);
    if (t1?.key) return t1.key;
    const t2 = yt.find(v => (v.type || '').toLowerCase() === 'trailer');
    if (t2?.key) return t2.key;
    const t3 = yt.find(v => (v.type || '').toLowerCase() === 'teaser');
    if (t3?.key) return t3.key;
    return yt[0]?.key || null;
  }

  // ---------- Etapa 1: YouTube API, Etapa 2: TMDb videos ----------
  async function resolveTrailerKey(title, year, tmdbId, card, mediaType = 'movie') {
    // 1) YouTube API
    if (YOUTUBE_API_KEY && YOUTUBE_API_KEY !== 'YOUR_YOUTUBE_KEY') {
      try {
        const q1 = `${title} ${year || ''} official trailer`.trim();
        const url = new URL('https://www.googleapis.com/youtube/v3/search');
        url.searchParams.set('key', YOUTUBE_API_KEY);
        url.searchParams.set('part', 'snippet');
        url.searchParams.set('type', 'video');
        url.searchParams.set('maxResults', '1');
        url.searchParams.set('videoEmbeddable', 'true');
        url.searchParams.set('q', q1);
        const r = await fetch(url.toString());
        const j = await r.json();
        const id = j?.items?.[0]?.id?.videoId;
        if (id) return id;

        // a doua încercare cu query alternativ
        const url2 = new URL('https://www.googleapis.com/youtube/v3/search');
        url2.searchParams.set('key', YOUTUBE_API_KEY);
        url2.searchParams.set('part', 'snippet');
        url2.searchParams.set('type', 'video');
        url2.searchParams.set('maxResults', '1');
        url2.searchParams.set('videoEmbeddable', 'true');
        url2.searchParams.set('q', `${title} trailer`);
        const r2 = await fetch(url2.toString());
        const j2 = await r2.json();
        const id2 = j2?.items?.[0]?.id?.videoId;
        if (id2) return id2;
      } catch (_) {/* fallback mai jos */}
    }

    // 2) TMDb videos (ro-RO -> en-US -> fără limbă)
    try {
      const v1 = await tmdbVideosGeneric(tmdbId, mediaType, 'ro-RO');
      let key = pickTrailerKey(v1);
      if (key) return key;
      const v2 = await tmdbVideosGeneric(tmdbId, mediaType, 'en-US');
      key = pickTrailerKey(v2);
      if (key) return key;
      const v3 = await tmdbVideosGeneric(tmdbId, mediaType);
      key = pickTrailerKey(v3);
      if (key) return key;
    } catch (_) {}

    // 3) Fallback local/din card
    return card?.dataset?.trailerId || fallbackTrailerIds[title] || null;
  }

  function fmt(n) { try { return Number(n).toLocaleString('ro-RO'); } catch { return n; } }
  function preload(src) { return new Promise((res, rej) => { const i = new Image(); i.onload = () => res(src); i.onerror = rej; i.src = src; }); }

  // ---------- Postere pe Home ----------
  let posterObserver = null;
  async function setCardPoster(card) {
    if (!TMDB_API_KEY || TMDB_API_KEY === 'YOUR_TMDB_KEY') return;
    if (card.dataset.placeholder === '1') return;

    const dbPosterUrl = card.dataset.posterUrl || '';
    if (dbPosterUrl) {
      const el = card.querySelector('.poster');
      if (!el) return;
      el.style.backgroundImage = `url(${dbPosterUrl})`;
      el.style.backgroundSize = 'cover';
      el.style.backgroundPosition = 'center';
      const ft = el.querySelector('.fallback-title');
      if (ft) ft.style.opacity = '0';
      return;
    }

    const imdbId = card.dataset.imdb;
    const title = card.dataset.title || card.querySelector('h3')?.textContent?.trim();
    const year = (card.dataset.year || '').slice(0, 4);
    const mediaType = card.dataset.category === 'series' ? 'tv' : 'movie';

    try {
      // If the card already has a TMDb id from the DB, do not re-discover it.
      let tmdbId = card.dataset.tmdb ? Number(card.dataset.tmdb) : 0;
      let tmdbObj = null;

      if (!tmdbId) {
        if (mediaType === 'movie') {
          tmdbObj = await tmdbFindByImdb(imdbId).catch(() => null);
          if (!tmdbObj) {
            const list = await tmdbSearch(title, year);
            tmdbObj = list
              .sort((a,b)=>(b.vote_count||0)-(a.vote_count||0))
              .find(r => (r.title||'').toLowerCase() === (title||'').toLowerCase() ||
                         (r.release_date||'').startsWith(year)) || list[0];
          }
        } else {
          const list = await tmdbTvSearch(title, year);
          tmdbObj = list
            .sort((a,b)=>(b.vote_count||0)-(a.vote_count||0))
            .find(r => (r.name||'').toLowerCase() === (title||'').toLowerCase() ||
                       (r.first_air_date||'').startsWith(year)) || list[0];
        }

        if (!tmdbObj?.id) return;
        tmdbId = tmdbObj.id;
        card.dataset.tmdb = String(tmdbId);
      }

      if (!tmdbObj) {
        const d = mediaType === 'tv' ? await tmdbTvDetails(tmdbId) : await tmdbDetails(tmdbId);
        tmdbObj = d;
      }

      const posterUrl = tmdbObj.poster_path ? `${POSTER_BASE}${tmdbObj.poster_path}`
                       : tmdbObj.backdrop_path ? `${BACKDROP_BASE}${tmdbObj.backdrop_path}` : null;
      if (!posterUrl) return;

      await preload(posterUrl);
      const el = card.querySelector('.poster');
      if (!el) return;
      el.style.backgroundImage = `url(${posterUrl})`;
      el.style.backgroundSize = 'cover';
      el.style.backgroundPosition = 'center';
      const ft = el.querySelector('.fallback-title');
      if (ft) ft.style.opacity = '0';
    } catch (e) {
      console.warn('TMDb card poster failed:', title, e);
    }
  }

  function hydrateCardPosters(scope = document) {
    const cards = scope.querySelectorAll('.film-card');
    if (!('IntersectionObserver' in window)) {
      cards.forEach(setCardPoster);
      return;
    }

    if (!posterObserver) {
      posterObserver = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
          if (!entry.isIntersecting) return;
          const card = entry.target;
          posterObserver.unobserve(card);
          setCardPoster(card);
        });
      }, { rootMargin: '300px 0px', threshold: 0.01 });
    }

    cards.forEach((card) => posterObserver.observe(card));
  }

  // ---------- Click pe suggestion => DETALII + TRAILER ----------
  document.querySelectorAll('.suggestion-item').forEach(item => {
    item.addEventListener('click', async (e) => {
      e.preventDefault();
      e.stopPropagation();
      
      // Hide search suggestions
      const searchSuggestions = document.getElementById('searchSuggestions');
      if (searchSuggestions) searchSuggestions.style.display = 'none';
      
      const title = item.dataset.movie || item.querySelector('.suggestion-text')?.textContent?.trim();
      const yearHint = item.querySelector('.suggestion-year')?.textContent?.trim() || '';
      const posterEl = document.getElementById('detailsPoster');
      const posterTitle = document.getElementById('detailsPosterTitle');

      // pre-loader + fallback culoare
      showLoading();
      const color = '#0d1117';
      if (posterEl && posterTitle) {
        posterEl.style.background = color;
        posterEl.style.backgroundImage = 'none';
        posterTitle.textContent = title || '';
        posterTitle.style.display = 'block';
      }
      openModal();

      try {
        if (!TMDB_API_KEY || TMDB_API_KEY === 'YOUR_TMDB_KEY') throw new Error('Lipsește TMDb key');

        // Caută filmul pe TMDb
        const results = await tmdbSearch(title, yearHint);
        const best = results
          .sort((a,b)=>(b.vote_count||0)-(a.vote_count||0))
          .find(r => (r.title||'').toLowerCase() === (title||'').toLowerCase() ||
                     (r.release_date||'').startsWith(yearHint)) || results[0];
        
        if (!best?.id) throw new Error('TMDb search: no result');
        
        const tmdbId = best.id;
        const d = await tmdbDetails(tmdbId);

        // Poster în modal (din TMDb)
        if (d.poster_path) {
          posterEl.style.backgroundImage = `url(${POSTER_BASE}${d.poster_path})`;
          posterEl.style.backgroundSize = 'cover';
          posterEl.style.backgroundPosition = 'center';
          if (posterTitle) posterTitle.style.display = 'none';
        }

        // Mapare câmpuri
        const year = d.release_date ? String(d.release_date).slice(0, 4) : '-';
        const genres = Array.isArray(d.genres) ? d.genres.map(g => g.name).join(', ') : '-';
        const runtime = d.runtime ? `${d.runtime} min` : 'N/A';
        const rating = d.vote_average ? d.vote_average.toFixed(1) : '-';
        const votes = d.vote_count ? fmt(d.vote_count) : 'N/A';
        const desc = d.overview && d.overview.trim() !== '' ? d.overview : 'Fără descriere.';
        const directors = (d.credits?.crew || []).filter(p => p.job === 'Director').map(p => p.name);
        const actors = (d.credits?.cast || []).slice(0, 6).map(p => p.name);

        setText('detailsTitle', d.title || title || '');
        setText('detailsYear', year);
        setText('detailsGenre', genres);
        setText('detailsRuntime', `⏱ ${runtime}`);
        setText('detailsRating', `${rating}/10`);
        setText('aboutMovie', desc);
        setText('directorName', directors.length ? directors.join(', ') : 'N/A');
        setText('actorsList', actors.length ? actors.join(', ') : 'N/A');
        setText('statRating', `${rating}/10`);
        setText('statYear', year);
        setText('statGenre', genres);
        setText('statRuntime', runtime);
        setText('statVotes', votes);

        detailsModal.dataset.currentMovie = d.title || title || '';
        detailsModal.dataset.tmdbId = String(tmdbId);
        detailsModal.dataset.mediaType = 'movie';

        // Încărcare review-uri
        loadReviews(tmdbId);
        
        // Reset rating și textarea
        resetReviewForm();

        // Etapa 1: YouTube -> Etapa 2: TMDb -> Fallback local
        const trailerKey = await resolveTrailerKey(d.title || title, year, tmdbId, item);
        const tc = document.getElementById('trailerContainer');
        if (tc) {
          tc.innerHTML = trailerKey
            ? `<iframe width="100%" height="100%" src="https://www.youtube.com/embed/${trailerKey}?autoplay=0" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>`
            : '<p style="text-align:center; color:#c9d1d9; padding:50px;">Trailer indisponibil</p>';
        }

        // memo
        detailsModal.dataset.currentMovie = d.title || title || '';
        detailsModal.dataset.tmdbId = String(tmdbId);
        detailsModal.dataset.mediaType = 'movie';
        hideLoading();
      } catch (err) {
        console.error('TMDb/Trailer error:', err);
        hideLoading();
        setText('detailsTitle', title || 'Eroare');
        setText('aboutMovie', 'Nu s-au putut încărca detaliile filmului.');
      }
    });
  });

  // Funcție pentru a încărca și afișa review-uri
  async function loadReviews(tmdbId, mediaType = 'movie') {
    const userReviewsSection = document.getElementById('userReviewsSection');
    if (!userReviewsSection) return;

    const movieId = Number(detailsModal?.dataset?.movieId || 0);
    const modalTmdbId = Number(detailsModal?.dataset?.tmdbId || 0);

    try {
      let localReviews = [];
      if (movieId || modalTmdbId) {
        const query = movieId ? `movie_id=${movieId}` : `tmdb_id=${modalTmdbId}`;
        const { data } = await jsonFetch(`${apiBase}/reviews.php?${query}`);
        localReviews = Array.isArray(data) ? data : [];
      }

      let tmdbReviews = [];
      if (tmdbId) {
        try {
          tmdbReviews = await tmdbReviewsGeneric(tmdbId, mediaType);
        } catch (_) {
          tmdbReviews = [];
        }
      }

      const normalizedLocal = localReviews.map(review => ({
        id: review.id,
        author: review.username || 'Utilizator',
        rating: review.rating ? `⭐ ${review.rating}/10` : '',
        content: review.content || '',
        created_at: review.created_at || null,
        source: 'local',
      }));

      const normalizedTmdb = tmdbReviews.map(review => ({
        author: review.author || 'Anonymous',
        rating: review.author_details?.rating ? `⭐ ${review.author_details.rating}/10` : '',
        content: review.content || '',
        created_at: review.created_at || null,
        source: 'tmdb',
      }));

      const allReviews = [...normalizedLocal, ...normalizedTmdb];

      if (allReviews.length === 0) {
        userReviewsSection.innerHTML = '<p style="color: #8b949e;">Nu există review-uri disponibile.</p>';
        return;
      }

      userReviewsSection.innerHTML = allReviews.map(review => {
        const author = escapeHtml(review.author || 'Anonymous');
        const rating = review.rating || '';
        const content = escapeHtml(review.content || '');
        const date = review.created_at ? new Date(review.created_at).toLocaleDateString('ro-RO') : '';
        const initial = author.charAt(0).toUpperCase();
        const sourceLabel = review.source === 'local' ? '<span class="user-review-source">Local</span>' : '';
        const canDelete = userRole === 'admin' && review.source === 'local' && review.id;
        const deleteButton = canDelete
          ? `<button class="review-delete-btn" data-review-id="${review.id}" type="button">Șterge</button>`
          : '';

        return `
          <div class="user-review-item">
            <div class="user-review-header">
              <div class="user-review-author">
                <div class="user-review-avatar">${initial}</div>
                <span class="user-review-name">${author}</span>
              </div>
              <div class="user-review-meta">
                ${sourceLabel}
                ${rating ? `<span class="user-review-rating">${rating}</span>` : ''}
                ${deleteButton}
              </div>
            </div>
            <div class="user-review-content">${content}</div>
            ${date ? `<div class="user-review-date">Publicat pe ${date}</div>` : ''}
          </div>
        `;
      }).join('');
    } catch (err) {
      console.error('Error loading reviews:', err);
      userReviewsSection.innerHTML = '<p style="color: #8b949e;">Eroare la încărcarea review-urilor.</p>';
    }
  }
  
  const starElements = document.querySelectorAll('.star');
  const starRatingContainer = document.querySelector('.star-rating');
  const ratingValueEl = document.getElementById('ratingValue');
  const reviewTextInput = document.getElementById('reviewText');
  const submitReviewBtn = document.getElementById('submitReviewBtn');

  // Funcție pentru a reseta formularul de review
  function resetReviewForm() {
    starElements.forEach(star => {
      star.classList.remove('active');
      star.textContent = '☆';
    });
    if (ratingValueEl) {
      ratingValueEl.textContent = '0/10';
    }
    if (reviewTextInput) {
      reviewTextInput.value = '';
    }
  }
  
  // Sistem de rating cu stele
  let selectedRating = 0;
  
  if (starElements.length && starRatingContainer && ratingValueEl) {
    starElements.forEach(star => {
      star.addEventListener('click', function() {
        selectedRating = parseInt(this.dataset.rating, 10);
        updateStars(selectedRating);
        ratingValueEl.textContent = `${selectedRating}/10`;
      });
      
      star.addEventListener('mouseenter', function() {
        const rating = parseInt(this.dataset.rating, 10);
        updateStars(rating, true);
      });
    });
    
    starRatingContainer.addEventListener('mouseleave', function() {
      updateStars(selectedRating);
    });
  }
  
  function updateStars(rating, isHover = false) {
    const stars = document.querySelectorAll('.star');
    stars.forEach((star, index) => {
      const starRating = parseInt(star.dataset.rating);
      if (starRating <= rating) {
        if (!isHover) star.classList.add('active');
        star.textContent = '★';
      } else {
        if (!isHover) star.classList.remove('active');
        star.textContent = '☆';
      }
    });
  }
  
  // Buton submit review
  if (submitReviewBtn && reviewTextInput) {
    submitReviewBtn.addEventListener('click', async function() {
      const reviewText = reviewTextInput.value.trim();

      if (!userId) {
        alert('Trebuie să fii autentificat pentru a trimite un review.');
        return;
      }

      const movieId = Number(detailsModal?.dataset?.movieId || 0);
      const tmdbId = Number(detailsModal?.dataset?.tmdbId || 0);
      const title = detailsModal?.dataset?.currentMovie || '';
      const mediaType = detailsModal?.dataset?.mediaType || 'movie';
      const yearText = document.getElementById('detailsYear')?.textContent?.trim() || '';
      const releaseYear = Number.parseInt(yearText, 10);
      const overview = document.getElementById('aboutMovie')?.textContent?.trim() || '';

      if (!movieId && !tmdbId) {
        alert('Nu pot identifica filmul pentru acest review.');
        return;
      }
      
      if (selectedRating === 0) {
        alert('Te rugăm să selectezi un rating!');
        return;
      }
      
      if (reviewText === '') {
        alert('Te rugăm să scrii un review!');
        return;
      }

      submitReviewBtn.disabled = true;

      try {
        await jsonFetch(`${apiBase}/reviews.php`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            movie_id: movieId,
            tmdb_id: tmdbId,
            title,
            category: mediaType === 'tv' ? 'series' : 'movie',
            release_year: Number.isFinite(releaseYear) ? releaseYear : null,
            overview,
            rating: selectedRating,
            content: reviewText,
          }),
        });

        showNotification('Review trimis cu succes!', 'success');
        await loadReviews(detailsModal?.dataset?.tmdbId || detailsModal?.dataset?.currentTmdbId || null, detailsModal?.dataset?.mediaType || 'movie');
        resetReviewForm();
        selectedRating = 0;
      } catch (err) {
        alert(err?.message || 'Eroare la salvarea review-ului.');
      } finally {
        submitReviewBtn.disabled = false;
      }
    });
  }

  if (document.getElementById('userReviewsSection')) {
    document.getElementById('userReviewsSection').addEventListener('click', async (event) => {
      const target = event.target;
      if (!(target instanceof HTMLElement)) return;
      if (!target.classList.contains('review-delete-btn')) return;
      if (userRole !== 'admin') return;

      const reviewId = Number(target.dataset.reviewId || 0);
      if (!reviewId) return;

      const confirmed = await confirmAction('Sigur vrei să ștergi acest review?', 'Șterge');
      if (!confirmed) return;

      try {
        await jsonFetch(`${apiBase}/admin/reviews.php`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ review_id: reviewId }),
        });
        await loadReviews(detailsModal?.dataset?.tmdbId || null, detailsModal?.dataset?.mediaType || 'movie');
        showNotification('Review șters.', 'success');
      } catch (err) {
        showNotification(err?.message || 'Eroare la ștergere.', 'error');
      }
    });
  }
  
  // Funcție pentru a adăuga review-ul utilizatorului în listă
  function addUserReview(content, rating) {
    const userReviewsSection = document.getElementById('userReviewsSection');
    if (!userReviewsSection) return;
    
    // Obține numele utilizatorului din elementul userData
    const userDataEl = document.getElementById('userData');
    const userName = userDataEl ? userDataEl.dataset.username : 'Utilizator';
    const initial = userName.charAt(0).toUpperCase();
    const currentDate = new Date().toLocaleDateString('ro-RO');
    
    // Creează HTML-ul pentru noul review
    const newReviewHTML = `
      <div class="user-review-item" style="animation: slideIn 0.3s ease;">
        <div class="user-review-header">
          <div class="user-review-author">
            <div class="user-review-avatar">${initial}</div>
            <span class="user-review-name">${userName}</span>
          </div>
          <span class="user-review-rating">⭐ ${rating}/10</span>
        </div>
        <div class="user-review-content">${content}</div>
        <div class="user-review-date">Publicat pe ${currentDate}</div>
      </div>
    `;
    
    // Verifică dacă există mesaj "Nu există review-uri"
    const noReviewsMsg = userReviewsSection.querySelector('p');
    if (noReviewsMsg) {
      userReviewsSection.innerHTML = newReviewHTML;
    } else {
      // Adaugă la început
      userReviewsSection.insertAdjacentHTML('afterbegin', newReviewHTML);
    }
    
    // Notificare de succes
    showNotification('Review trimis cu succes!', 'success');
  }
  
  // Funcție pentru notificări
  function showNotification(message, type = 'success') {
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.textContent = message;
    notification.style.cssText = `
      position: fixed;
      top: 20px;
      right: 20px;
      background: ${type === 'success' ? '#238636' : '#da3633'};
      color: white;
      padding: 15px 25px;
      border-radius: 8px;
      font-weight: 600;
      z-index: 10000;
      animation: slideInRight 0.3s ease;
      box-shadow: 0 4px 12px rgba(0,0,0,0.3);
    `;
    
    document.body.appendChild(notification);
    
    setTimeout(() => {
      notification.style.animation = 'slideOutRight 0.3s ease';
      setTimeout(() => notification.remove(), 300);
    }, 3000);
  }

  function ensureConfirmDialog() {
    let overlay = document.getElementById('confirmOverlay');
    if (overlay) return overlay;

    overlay = document.createElement('div');
    overlay.id = 'confirmOverlay';
    overlay.className = 'confirm-overlay';
    overlay.innerHTML = `
      <div class="confirm-dialog" role="dialog" aria-modal="true" aria-labelledby="confirmTitle">
        <h4 id="confirmTitle">Confirmare</h4>
        <p id="confirmMessage">Ești sigur?</p>
        <div class="confirm-actions">
          <button type="button" class="confirm-btn confirm-cancel" id="confirmCancelBtn">Anulează</button>
          <button type="button" class="confirm-btn confirm-danger" id="confirmOkBtn">Șterge</button>
        </div>
      </div>
    `;
    document.body.appendChild(overlay);
    return overlay;
  }

  function confirmAction(message, okLabel = 'Confirmă') {
    return new Promise((resolve) => {
      const overlay = ensureConfirmDialog();
      const msg = overlay.querySelector('#confirmMessage');
      const okBtn = overlay.querySelector('#confirmOkBtn');
      const cancelBtn = overlay.querySelector('#confirmCancelBtn');

      if (msg) msg.textContent = message;
      if (okBtn) okBtn.textContent = okLabel;

      const cleanup = (result) => {
        overlay.classList.remove('active');
        okBtn?.removeEventListener('click', onOk);
        cancelBtn?.removeEventListener('click', onCancel);
        overlay.removeEventListener('click', onBackdrop);
        document.removeEventListener('keydown', onKey);
        resolve(result);
      };

      const onOk = () => cleanup(true);
      const onCancel = () => cleanup(false);
      const onBackdrop = (e) => {
        if (e.target === overlay) cleanup(false);
      };
      const onKey = (e) => {
        if (e.key === 'Escape') cleanup(false);
      };

      okBtn?.addEventListener('click', onOk);
      cancelBtn?.addEventListener('click', onCancel);
      overlay.addEventListener('click', onBackdrop);
      document.addEventListener('keydown', onKey);

      requestAnimationFrame(() => overlay.classList.add('active'));
      okBtn?.focus();
    });
  }

  async function handleFilmCardSelection(card) {
    const title = card.dataset.title || card.querySelector('h3')?.textContent?.trim();
    const yearHint = (card.dataset.year || '').slice(0, 4);
    const posterEl = document.getElementById('detailsPoster');
    const posterTitle = document.getElementById('detailsPosterTitle');
    const mediaType = card.dataset.category === 'series' ? 'tv' : 'movie';

    showLoading();
    const color = card.dataset.color || card.querySelector('.poster')?.style.backgroundColor || '#0d1117';
    if (posterEl && posterTitle) {
      posterEl.style.background = color;
      posterEl.style.backgroundImage = 'none';
      posterTitle.textContent = title || '';
      posterTitle.style.display = 'block';
    }
    openModal();
    if (typeof syncWatchlistButtonState === 'function') {
      syncWatchlistButtonState(card);
    }
    activeCardRef = card;

    try {
      if (!TMDB_API_KEY || TMDB_API_KEY === 'YOUR_TMDB_KEY') throw new Error('Lipsește TMDb key');

      // găsește TMDb id (IMDb -> search)
      const imdbId = card.dataset.imdb;
      let tmdbId = card.dataset.tmdb || null;
      if (imdbId && !tmdbId) { const f = await tmdbFindByImdb(imdbId).catch(()=>null); if (f) tmdbId = f.id; }
      if (!tmdbId) {
        const results = mediaType === 'tv' ? await tmdbTvSearch(title, yearHint) : await tmdbSearch(title, yearHint);
        const best = results
          .sort((a,b)=>(b.vote_count||0)-(a.vote_count||0))
          .find(r => {
            if (mediaType === 'tv') {
              return (r.name||'').toLowerCase() === (title||'').toLowerCase() || (r.first_air_date||'').startsWith(yearHint);
            }
            return (r.title||'').toLowerCase() === (title||'').toLowerCase() || (r.release_date||'').startsWith(yearHint);
          }) || results[0];
        tmdbId = best?.id;
      }
      if (!tmdbId) throw new Error('TMDb search: no result');

      const d = mediaType === 'tv' ? await tmdbTvDetails(tmdbId) : await tmdbDetails(tmdbId);

      if (d.poster_path) {
        posterEl.style.backgroundImage = `url(${POSTER_BASE}${d.poster_path})`;
        posterEl.style.backgroundSize = 'cover';
        posterEl.style.backgroundPosition = 'center';
        if (posterTitle) posterTitle.style.display = 'none';
      }

      const year = mediaType === 'tv'
        ? (d.first_air_date ? String(d.first_air_date).slice(0, 4) : '-')
        : (d.release_date ? String(d.release_date).slice(0, 4) : '-');
      const genres = Array.isArray(d.genres) ? d.genres.map(g => g.name).join(', ') : '-';
      const runtime = mediaType === 'tv'
        ? (Array.isArray(d.episode_run_time) && d.episode_run_time.length ? `${d.episode_run_time[0]} min/ep` : 'N/A')
        : (d.runtime ? `${d.runtime} min` : 'N/A');
      const rating = d.vote_average ? d.vote_average.toFixed(1) : '-';
      const votes = d.vote_count ? fmt(d.vote_count) : 'N/A';
      const desc = d.overview && d.overview.trim() !== '' ? d.overview : 'Fără descriere.';
      const directors = mediaType === 'tv'
        ? (Array.isArray(d.created_by) ? d.created_by.map(p => p.name).filter(Boolean) : [])
        : (d.credits?.crew || []).filter(p => p.job === 'Director').map(p => p.name);
      const actors = (d.credits?.cast || []).slice(0, 6).map(p => p.name);

      setText('detailsTitle', (mediaType === 'tv' ? (d.name || d.original_name) : (d.title || d.original_title)) || title || '');
      setText('detailsYear', year);
      setText('detailsGenre', genres);
      setText('detailsRuntime', `⏱ ${runtime}`);
      setText('detailsRating', `${rating}/10`);
      setText('aboutMovie', desc);
      setText('directorName', directors.length ? directors.join(', ') : 'N/A');
      setText('actorsList', actors.length ? actors.join(', ') : 'N/A');
      setText('statRating', `${rating}/10`);
      setText('statYear', year);
      setText('statGenre', genres);
      setText('statRuntime', runtime);
      setText('statVotes', votes);

      detailsModal.dataset.currentMovie = (mediaType === 'tv' ? (d.name || title) : (d.title || title)) || '';
      detailsModal.dataset.mediaType = mediaType;
      detailsModal.dataset.tmdbId = String(tmdbId);
      if (card.dataset.movieId) {
        detailsModal.dataset.movieId = card.dataset.movieId;
      }

      loadReviews(tmdbId, mediaType);
      resetReviewForm();

      const trailerKey = await resolveTrailerKey((mediaType === 'tv' ? (d.name || title) : (d.title || title)), year, tmdbId, card, mediaType);
      const tc = document.getElementById('trailerContainer');
      if (tc) {
        tc.innerHTML = trailerKey
          ? `<iframe width="100%" height="100%" src="https://www.youtube.com/embed/${trailerKey}?autoplay=0" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>`
          : '<p style="text-align:center; color:#c9d1d9; padding:50px;">Trailer indisponibil</p>';
      }

      card.dataset.tmdb = tmdbId;
    } catch (err) {
      console.error('TMDb/Trailer error:', err);
      const info = card.querySelector('.info p')?.textContent || '';
      const parts = info.split('•').map(s => s.trim());
      const year = card.dataset.year || parts[0] || 'N/A';
      const genre = card.dataset.genre || parts[1] || 'N/A';
      const rating = card.dataset.rating || (parts[2]?.replace('/10', '') || 'N/A');
      const desc = card.dataset.description || card.querySelector('.hover p')?.textContent || 'Fără descriere.';
      setText('detailsTitle', title || '');
      setText('detailsYear', year);
      setText('detailsGenre', genre);
      setText('detailsRuntime', '⏱ N/A');
      setText('detailsRating', `${rating}/10`);
      setText('aboutMovie', desc);
      setText('directorName', 'N/A');
      setText('actorsList', 'N/A');
      setText('statRating', `${rating}/10`);
      setText('statYear', year);
      setText('statGenre', genre);
      setText('statRuntime', 'N/A');
      setText('statVotes', 'N/A');
      const tc = document.getElementById('trailerContainer');
      const fb = card.dataset.trailerId || fallbackTrailerIds[title] || null;
      if (tc) {
        tc.innerHTML = fb
          ? `<iframe width="100%" height="100%" src="https://www.youtube.com/embed/${fb}?autoplay=0" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>`
          : '<p style="text-align:center; color:#c9d1d9; padding:50px;">Trailer indisponibil</p>';
      }
      if (card.dataset.movieId) {
        detailsModal.dataset.movieId = card.dataset.movieId;
      }
    }
  }

  wireFilmCards();

  // Watchlist (dacă ai endpoint)
  if (watchlistBtn) {
    let isInWatchlist = false;
    let currentMovieId = null;

    const refreshWatchlistButton = () => {
      watchlistBtn.innerHTML = isInWatchlist
        ? '<span>✓ În Watchlist</span>'
        : '<span>+ Adaugă în Watchlist</span>';
      watchlistBtn.classList.toggle('in-watchlist', isInWatchlist);
    };

    const setWatchlistState = (state, movieId) => {
      isInWatchlist = Boolean(state);
      if (movieId) {
        currentMovieId = movieId;
      }
      refreshWatchlistButton();
      detailsModal.dataset.inWatchlist = isInWatchlist ? '1' : '0';
    };

    watchlistBtn.addEventListener('click', async () => {
      const movieId = Number(detailsModal?.dataset.movieId || currentMovieId || 0);
      if (!movieId) {
        alert('Selectează un film pentru a-l adăuga în watchlist.');
        return;
      }

      try {
        if (isInWatchlist) {
          await jsonFetch(`${apiBase}/watchlist.php`, {
            method: 'DELETE',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ movie_id: movieId })
          });
          setWatchlistState(false, movieId);
          updateAllCardsWatchlistState(movieId, false);
          emitWatchlistSync(movieId, false);
          showAddMovieConfirmation('Filmul a fost eliminat din watchlist.');
          await refreshWatchlistViewIfNeeded();
        } else {
          await jsonFetch(`${apiBase}/watchlist.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ movie_id: movieId })
          });
          setWatchlistState(true, movieId);
          updateAllCardsWatchlistState(movieId, true);
          emitWatchlistSync(movieId, true);
          showAddMovieConfirmation('Filmul a fost adăugat în watchlist.');
          await refreshWatchlistViewIfNeeded();
        }
      } catch (err) {
        alert(err.message || 'Eroare la actualizarea watchlist-ului');
      }
    });

    syncWatchlistButtonState = (card) => {
      if (!card) return;
      const state = card.dataset.inWatchlist === '1';
      currentMovieId = Number(card.dataset.movieId || 0) || currentMovieId;
      setWatchlistState(state, currentMovieId);
    };
  }

  // Pornește încărcarea posterelor cardurilor & bootstrap din API
  hydrateCardPosters();
  bootstrapPageData();

  const showAdminToast = (message, variant = 'success') => {
    const toast = adminToast || document.getElementById('globalToast');
    const toastMessage = adminToastMessage || document.getElementById('globalToastMessage');
    if (!toast || !toastMessage) return;
    toastMessage.textContent = message;
    toast.dataset.variant = variant;
    toast.style.display = 'flex';
    requestAnimationFrame(() => toast.classList.add('show'));
    setTimeout(() => {
      toast.classList.remove('show');
      setTimeout(() => {
        toast.style.display = 'none';
      }, 250);
    }, 3500);
  };

  const initAdminPanel = () => {
    if (pageContext !== 'admin') return;
    const actionButtons = document.querySelectorAll('.admin-action[data-movie-id]');
    const table = document.getElementById('pendingMoviesTable');
    const tableBody = document.getElementById('pendingMoviesBody');
    const emptyState = document.getElementById('pendingEmptyState');
    const pendingCountEl = document.getElementById('pendingMoviesCount');
    const totalMoviesCountEl = document.getElementById('totalMoviesCount');
    const usersCountEl = document.getElementById('usersCount');

    const toggleEmptyState = () => {
      const hasRows = !!tableBody?.querySelector('tr');
      if (table) {
        table.style.display = hasRows ? 'table' : 'none';
      }
      if (emptyState) {
        emptyState.style.display = hasRows ? 'none' : 'block';
      }
    };

    const updateStats = (stats = {}) => {
      if (typeof stats.pending !== 'undefined' && pendingCountEl) {
        pendingCountEl.textContent = stats.pending;
      }
      if (typeof stats.total !== 'undefined' && totalMoviesCountEl) {
        totalMoviesCountEl.textContent = stats.total;
      }
    };

    const hasAllTitlesUI = Boolean(totalMoviesCard && allTitlesModal && allTitlesBody);
    const allTitlesState = {
      page: 1,
      perPage: 50,
      totalPages: 1,
      totalRows: 0,
      search: '',
      status: '',
      category: '',
      loading: false,
      debounce: null,
    };

    const hasAllUsersUI = Boolean(usersCard && allUsersModal && allUsersBody);
    const allUsersState = {
      loading: false,
      loaded: false,
      totalRows: 0,
    };

    const syncAllTitlesMeta = () => {
      if (allTitlesMeta) {
        const totalText = allTitlesState.totalRows === 1
          ? '1 titlu în total'
          : `${allTitlesState.totalRows} titluri în total`;
        allTitlesMeta.textContent = totalText;
      }
      if (allTitlesPage) {
        const totalPages = Math.max(1, allTitlesState.totalPages || 1);
        allTitlesPage.textContent = `Pagina ${allTitlesState.page} / ${totalPages}`;
      }
      if (allTitlesPrev) {
        allTitlesPrev.disabled = allTitlesState.page <= 1 || allTitlesState.loading;
      }
      if (allTitlesNext) {
        allTitlesNext.disabled = allTitlesState.page >= allTitlesState.totalPages || allTitlesState.loading;
      }
    };

    const setAllTitlesLoading = (isLoading) => {
      allTitlesState.loading = isLoading;
      if (isLoading && allTitlesBody) {
        allTitlesBody.innerHTML = '<tr><td colspan="6">Se încarcă lista...</td></tr>';
        if (allTitlesTable) {
          allTitlesTable.style.display = 'table';
        }
        if (allTitlesEmpty) {
          allTitlesEmpty.style.display = 'none';
        }
      }
      syncAllTitlesMeta();
    };

    const renderAllTitles = (rows = []) => {
      if (!allTitlesBody) return;
      if (!rows.length) {
        allTitlesBody.innerHTML = '';
        if (allTitlesTable) {
          allTitlesTable.style.display = 'none';
        }
        if (allTitlesEmpty) {
          allTitlesEmpty.style.display = 'block';
        }
        return;
      }

      if (allTitlesTable) {
        allTitlesTable.style.display = 'table';
      }
      if (allTitlesEmpty) {
        allTitlesEmpty.style.display = 'none';
      }

      allTitlesBody.innerHTML = rows.map((row) => {
        const year = row.release_year || '—';
        const rating = row.rating_average ? Number(row.rating_average).toFixed(1) : '—';
        const category = row.category === 'series' ? 'Serial' : 'Film';
        const statusLabel = row.status === 'pending'
          ? 'În așteptare'
          : (row.status === 'archived' ? 'Arhivat' : 'Publicat');
        return `
          <tr>
            <td>#${row.id}</td>
            <td>${escapeHtml(row.title || row.original_title || '—')}</td>
            <td>${category}</td>
            <td>${statusLabel}</td>
            <td>${year}</td>
            <td>${rating}</td>
          </tr>
        `;
      }).join('');
    };

    const fetchAllTitles = async () => {
      if (!hasAllTitlesUI) return;
      setAllTitlesLoading(true);
      try {
        const params = new URLSearchParams({
          page: String(allTitlesState.page),
          perPage: String(allTitlesState.perPage),
        });
        if (allTitlesState.search) params.set('search', allTitlesState.search);
        if (allTitlesState.status) params.set('status', allTitlesState.status);
        if (allTitlesState.category) params.set('category', allTitlesState.category);

        const { data, meta } = await jsonFetch(`${apiBase}/admin/catalog.php?${params.toString()}`);
        allTitlesState.totalPages = meta?.totalPages || 1;
        allTitlesState.totalRows = meta?.total || 0;
        renderAllTitles(Array.isArray(data) ? data : []);
      } catch (err) {
        renderAllTitles([]);
        showAdminToast(err.message || 'Nu am putut încărca lista de titluri.', 'error');
      } finally {
        setAllTitlesLoading(false);
      }
    };

    const openAllTitlesModal = () => {
      if (!hasAllTitlesUI) return;
      allTitlesModal.classList.add('active');
      refreshBodyScrollLock();
      if (!allTitlesState.loading) {
        fetchAllTitles();
      }
      allTitlesSearch?.focus();
    };

    const closeAllTitlesModal = () => {
      if (!hasAllTitlesUI) return;
      allTitlesModal.classList.remove('active');
      refreshBodyScrollLock();
    };

    const scheduleTitlesFetch = () => {
      clearTimeout(allTitlesState.debounce);
      allTitlesState.debounce = setTimeout(() => {
        allTitlesState.page = 1;
        fetchAllTitles();
      }, 300);
    };

    if (hasAllTitlesUI) {
      totalMoviesCard.addEventListener('click', openAllTitlesModal);
      totalMoviesCard.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' || event.key === ' ') {
          event.preventDefault();
          openAllTitlesModal();
        }
      });
      closeAllTitlesBtn?.addEventListener('click', closeAllTitlesModal);
      allTitlesModal.addEventListener('click', (event) => {
        if (event.target === allTitlesModal) {
          closeAllTitlesModal();
        }
      });

      if (allTitlesSearch) {
        allTitlesSearch.addEventListener('input', (event) => {
          allTitlesState.search = event.target.value.trim();
          scheduleTitlesFetch();
        });
      }

      allTitlesStatusFilter?.addEventListener('change', (event) => {
        allTitlesState.status = event.target.value;
        scheduleTitlesFetch();
      });

      allTitlesCategoryFilter?.addEventListener('change', (event) => {
        allTitlesState.category = event.target.value;
        scheduleTitlesFetch();
      });

      allTitlesPrev?.addEventListener('click', () => {
        if (allTitlesState.page > 1 && !allTitlesState.loading) {
          allTitlesState.page -= 1;
          fetchAllTitles();
        }
      });

      allTitlesNext?.addEventListener('click', () => {
        if (allTitlesState.page < allTitlesState.totalPages && !allTitlesState.loading) {
          allTitlesState.page += 1;
          fetchAllTitles();
        }
      });

      allTitlesModal.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
          closeAllTitlesModal();
        }
      });
    }

    const setAllUsersLoading = (isLoading) => {
      allUsersState.loading = isLoading;
      if (isLoading && allUsersBody) {
        allUsersBody.innerHTML = '<tr><td colspan="7">Se încarcă utilizatorii...</td></tr>';
        if (allUsersTable) {
          allUsersTable.style.display = 'table';
        }
        if (allUsersEmpty) {
          allUsersEmpty.style.display = 'none';
        }
      }
    };

    const renderAllUsers = (rows = []) => {
      if (!allUsersBody) return;
      if (!rows.length) {
        allUsersBody.innerHTML = '';
        if (allUsersTable) {
          allUsersTable.style.display = 'none';
        }
        if (allUsersEmpty) {
          allUsersEmpty.style.display = 'block';
        }
        if (allUsersMeta) {
          allUsersMeta.textContent = '0 utilizatori';
        }
        return;
      }

      if (allUsersTable) {
        allUsersTable.style.display = 'table';
      }
      if (allUsersEmpty) {
        allUsersEmpty.style.display = 'none';
      }

      allUsersBody.innerHTML = rows.map((user) => {
        const roleLabel = user.role === 'admin' ? 'Admin' : 'User';
        const watchlistCount = typeof user.watchlist_count === 'number' ? user.watchlist_count : 0;
        const pendingCount = typeof user.pending_count === 'number' ? user.pending_count : 0;
        const createdAt = user.created_at ? escapeHtml(user.created_at) : '—';
        return `
          <tr>
            <td>#${user.id}</td>
            <td>${escapeHtml(user.username || '')}</td>
            <td>${escapeHtml(user.email || '')}</td>
            <td>${roleLabel}</td>
            <td>${watchlistCount}</td>
            <td>${pendingCount}</td>
            <td>${createdAt}</td>
          </tr>
        `;
      }).join('');

      if (allUsersMeta) {
        const total = rows.length;
        allUsersMeta.textContent = total === 1 ? '1 utilizator' : `${total} utilizatori`;
      }
    };

    const fetchAllUsers = async () => {
      if (!hasAllUsersUI) return;
      setAllUsersLoading(true);
      const basePath = window.location.pathname.replace(/[^/]+$/, ''); // path of current dir
      const primaryUrl = `${basePath}api/admin/users.php`;
      const fallbackUrl = '/api/admin/users.php';
      try {
        let payload;
        try {
          payload = await jsonFetch(primaryUrl);
        } catch (err) {
          // Retry with absolute root if relative failed
          payload = await jsonFetch(fallbackUrl);
        }
        allUsersState.totalRows = Array.isArray(payload?.data) ? payload.data.length : 0;
        allUsersState.loaded = true;
        renderAllUsers(Array.isArray(payload?.data) ? payload.data : []);
      } catch (err) {
        renderAllUsers([]);
        if (allUsersEmpty) {
          allUsersEmpty.textContent = err.message || 'Nu am putut încărca utilizatorii.';
          allUsersEmpty.style.display = 'block';
        }
        showAdminToast(err.message || 'Nu am putut încărca utilizatorii.', 'error');
      } finally {
        setAllUsersLoading(false);
      }
    };

    const openAllUsersModal = () => {
      if (!hasAllUsersUI) return;
      allUsersModal.classList.add('active');
      refreshBodyScrollLock();
      if (!allUsersState.loaded && !allUsersState.loading) {
        fetchAllUsers();
      }
    };

    const closeAllUsersModal = () => {
      if (!hasAllUsersUI) return;
      allUsersModal.classList.remove('active');
      refreshBodyScrollLock();
    };

    if (hasAllUsersUI) {
      usersCard.addEventListener('click', openAllUsersModal);
      usersCard.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' || event.key === ' ') {
          event.preventDefault();
          openAllUsersModal();
        }
      });
      closeAllUsersBtn?.addEventListener('click', closeAllUsersModal);
      allUsersModal.addEventListener('click', (event) => {
        if (event.target === allUsersModal) {
          closeAllUsersModal();
        }
      });
    }

    // ESC global pentru modalele din admin (utilizatori + titluri)
    document.addEventListener('keydown', (event) => {
      if (event.key !== 'Escape') return;
      if (hasAllUsersUI && allUsersModal?.classList.contains('active')) {
        closeAllUsersModal();
      }
      if (hasAllTitlesUI && allTitlesModal?.classList.contains('active')) {
        closeAllTitlesModal();
      }
    });

    if (actionButtons.length) {
      actionButtons.forEach((button) => {
        button.addEventListener('click', async (event) => {
          event.preventDefault();
          const movieId = Number(button.dataset.movieId || 0);
          const action = button.dataset.action;
          if (!movieId || !action) {
            showAdminToast('ID film invalid.', 'error');
            return;
          }

          button.disabled = true;
          button.classList.add('loading');

          try {
            const response = await fetch(`${apiBase}/admin/movies.php`, {
              method: 'POST',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify({ movieId, action })
            });

            const payload = await response.json();
            if (!response.ok || !payload.success) {
              throw new Error(payload.error || payload.message || 'Acțiunea a eșuat');
            }

            const row = button.closest('tr');
            if (row) {
              row.remove();
              toggleEmptyState();
            }

            updateStats(payload.stats || {});

            showAdminToast(action === 'approve'
              ? 'Titlul a fost publicat în catalog.'
              : 'Titlul a fost respins și arhivat.');
          } catch (error) {
            showAdminToast(error.message || 'Nu am putut executa acțiunea.', 'error');
          } finally {
            button.disabled = false;
            button.classList.remove('loading');
          }
        });
      });
    }
  };

  initAdminPanel();

  // ========== SEARCH FUNCTIONALITATE ========== 
  const searchInput = document.getElementById('search');
  const searchSuggestions = document.getElementById('searchSuggestions');

  const triggerSuggestionDetails = (item) => {
    const virtualCard = document.createElement('article');
    virtualCard.dataset.title = item.dataset.title || item.dataset.movie || item.querySelector('.suggestion-text')?.textContent?.trim() || '';
    virtualCard.dataset.movieId = item.dataset.movieId || '';
    virtualCard.dataset.imdb = item.dataset.imdb || '';
    virtualCard.dataset.tmdb = item.dataset.tmdb || '';
    virtualCard.dataset.year = item.dataset.year || '';
    virtualCard.dataset.genre = item.dataset.genre || '';
    virtualCard.dataset.rating = item.dataset.rating || '';
    virtualCard.dataset.description = item.dataset.description || '';
    virtualCard.dataset.color = item.dataset.color || '#0d1117';
    virtualCard.dataset.category = item.dataset.category || 'movie';
    if (item.dataset.posterUrl) {
      virtualCard.dataset.posterUrl = item.dataset.posterUrl;
    }
    handleFilmCardSelection(virtualCard);
  };

  if (searchInput && searchSuggestions) {
    let searchDebounce;
    let lastQuery = '';

    const showSuggestions = () => {
      searchSuggestions.style.display = 'block';
      searchSuggestions.style.maxHeight = '400px';
      searchSuggestions.style.opacity = '1';
      searchSuggestions.classList.add('is-open');
    };

    const hideSuggestions = () => {
      searchSuggestions.style.opacity = '0';
      searchSuggestions.style.maxHeight = '0';
      searchSuggestions.classList.remove('is-open');
      setTimeout(() => {
        searchSuggestions.style.display = 'none';
      }, 200);
    };

    const renderSuggestions = (items) => {
      if (!items.length) {
        searchSuggestions.innerHTML = '<p class="suggestion-empty">Niciun rezultat.</p>';
        showSuggestions();
        return;
      }

      searchSuggestions.innerHTML = items.map(movie => {
        movieCache.set(movie.id, movie);
        return `
          <button type="button" class="suggestion-item"
            data-movie-id="${movie.id || ''}"
            data-category="${movie.category === 'series' ? 'series' : 'movie'}"
            data-title="${escapeHtml(movie.title || '')}"
            data-imdb="${movie.imdb_id || ''}"
            data-tmdb="${movie.tmdb_id || ''}"
            data-year="${movie.release_year || ''}"
            data-genre="${escapeHtml(movie.genres || '')}"
            data-rating="${movie.rating_average || ''}"
            data-description="${escapeHtml(movie.overview || '')}"
            data-color="${movie.accent_color || '#0d1117'}"
            data-poster-url="${escapeHtml(movie.poster_url || '')}">
            <span class="suggestion-icon">🎬</span>
            <span class="suggestion-text">${escapeHtml(movie.title || '')}</span>
            <span class="suggestion-year">${movie.release_year || ''}</span>
          </button>
        `;
      }).join('');
      showSuggestions();
    };

    const showDefaultSuggestions = async () => {
      if (defaultSuggestionsLoading) {
        return;
      }

      if (defaultSuggestionCache?.length) {
        renderSuggestions(defaultSuggestionCache);
        return;
      }

      try {
        defaultSuggestionsLoading = true;
        const { data } = await jsonFetch(`${apiBase}/movies.php?scope=trending&limit=6`);
        defaultSuggestionCache = data || [];
        if (defaultSuggestionCache.length) {
          renderSuggestions(defaultSuggestionCache);
        }
      } catch (err) {
        searchSuggestions.innerHTML = '<p class="suggestion-empty">Tastează pentru a căuta filme.</p>';
        showSuggestions();
      } finally {
        defaultSuggestionsLoading = false;
      }
    };

    const fetchSuggestions = async (query) => {
      if (!query) {
        if (document.activeElement === searchInput) {
          await showDefaultSuggestions();
        } else {
          hideSuggestions();
        }
        return;
      }
      try {
        const { data } = await jsonFetch(`${apiBase}/search.php?q=${encodeURIComponent(query)}`);
        renderSuggestions(data);
      } catch (err) {
        searchSuggestions.innerHTML = `<p class="suggestion-empty">${escapeHtml(err.message)}</p>`;
        showSuggestions();
      }
    };

    searchInput.addEventListener('input', (event) => {
      const query = event.target.value.trim();
      if (query === lastQuery) return;
      lastQuery = query;
      clearTimeout(searchDebounce);
      searchDebounce = setTimeout(() => fetchSuggestions(query), 250);
    });

    searchInput.addEventListener('focus', () => {
      const query = searchInput.value.trim();
      if (query) {
        if (searchSuggestions.childElementCount) {
          showSuggestions();
        } else {
          fetchSuggestions(query);
        }
      } else {
        showDefaultSuggestions();
      }
    });

    searchSuggestions.addEventListener('click', (event) => {
      const item = event.target.closest('.suggestion-item');
      if (!item) return;
      triggerSuggestionDetails(item);
      hideSuggestions();
    });

    document.addEventListener('click', (event) => {
      if (!searchInput.contains(event.target) && !searchSuggestions.contains(event.target)) {
        hideSuggestions();
      }
    });
  }
});

// animație highlight (siguranță)
const style = document.createElement('style');
style.textContent = `
  @keyframes highlight { 0%,100%{transform:scale(1);box-shadow:0 8px 24px rgba(0,0,0,.3);} 50%{transform:scale(1.05);box-shadow:0 12px 32px rgba(229,185,10,.5);} }
`;
document.head.appendChild(style);

function setupBackToTopButton() {
  const bootstrap = () => {
    let button = document.getElementById('backToTopBtn');
    const backToTopIcon = `
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <line x1="12" y1="19" x2="12" y2="5"></line>
        <polyline points="5 12 12 5 19 12"></polyline>
      </svg>
    `;
    if (!button) {
      button = document.createElement('button');
      button.id = 'backToTopBtn';
      button.className = 'back-to-top-btn';
      button.type = 'button';
      button.setAttribute('aria-label', 'Înapoi sus');
      button.innerHTML = backToTopIcon;
      document.body.appendChild(button);
    } else {
      button.innerHTML = backToTopIcon;
    }

    const content = document.querySelector('main.content') || document.querySelector('main');
    const movieDetailsModal = document.getElementById('movieDetailsModal');
    const modalContent = movieDetailsModal?.querySelector('.movie-details-content') || null;

    const scrollTargets = [window, document, document.documentElement, document.body];
    if (content && !scrollTargets.includes(content)) scrollTargets.push(content);
    if (modalContent && !scrollTargets.includes(modalContent)) scrollTargets.push(modalContent);

    const SCROLL_THRESHOLD = 120;
    const isModalActive = () => movieDetailsModal?.classList.contains('active');

    const smoothScrollToTop = (target, duration = 450) => {
      const start = target === window
        ? window.pageYOffset || document.documentElement.scrollTop || document.body.scrollTop || 0
        : target.scrollTop;
      if (start <= 0) return;
      const startTime = performance.now();
      const easeOutCubic = (t) => 1 - Math.pow(1 - t, 3);

      const animate = (currentTime) => {
        const elapsed = Math.min((currentTime - startTime) / duration, 1);
        const eased = easeOutCubic(elapsed);
        const newPos = start * (1 - eased);
        if (target === window) {
          window.scrollTo(0, newPos);
          document.documentElement.scrollTop = newPos;
          document.body.scrollTop = newPos;
        } else {
          target.scrollTop = newPos;
        }
        if (elapsed < 1) {
          requestAnimationFrame(animate);
        }
      };

      requestAnimationFrame(animate);
    };

    const playButtonAnimation = () => {
      button.classList.remove('clicked');
      void button.offsetWidth;
      button.classList.add('clicked');
    };

    const toggleVisibility = () => {
      const windowScroll = window.pageYOffset || document.documentElement.scrollTop || document.body.scrollTop || 0;
      const contentScroll = content ? content.scrollTop : 0;
      const modalActive = isModalActive() && modalContent;
      const modalScroll = modalActive ? modalContent.scrollTop : 0;
      const scrollPosition = Math.max(windowScroll, contentScroll, modalScroll);

      if (modalActive) {
        button.dataset.context = 'modal';
        if (modalScroll > SCROLL_THRESHOLD) {
          button.classList.add('show');
        } else {
          button.classList.remove('show');
        }
        return;
      }

      button.dataset.context = 'page';
      if (scrollPosition > SCROLL_THRESHOLD) {
        button.classList.add('show');
      } else {
        button.classList.remove('show');
      }
    };

    scrollTargets.forEach(target => {
      if (target && typeof target.addEventListener === 'function') {
        target.addEventListener('scroll', toggleVisibility, { passive: true });
      }
    });
    if (movieDetailsModal && typeof MutationObserver !== 'undefined') {
      const modalObserver = new MutationObserver(toggleVisibility);
      modalObserver.observe(movieDetailsModal, { attributes: true, attributeFilter: ['class'] });
    }
    window.addEventListener('resize', toggleVisibility, { passive: true });
    toggleVisibility();

    button.addEventListener('click', () => {
      playButtonAnimation();
      const modalActive = isModalActive() && modalContent;
      if (modalActive && modalContent.scrollTop > 0) {
        smoothScrollToTop(modalContent, 700);
        return;
      }
      smoothScrollToTop(window, 600);
      if (content && content.scrollTop > 0) {
        smoothScrollToTop(content, 600);
      }
    });
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootstrap, { once: true });
  } else {
    bootstrap();
  }
}

setupBackToTopButton();

// Detectare mobil + toggle clasă pentru CSS (rulează doar după ce există <body>)
function setMobileClass() {
  if (!document.body) return;
  const isPhone = window.matchMedia('(max-width: 900px)').matches ||
                  /Android|iPhone|iPad|iPod|Windows Phone/i.test(navigator.userAgent);
  document.body.classList.toggle('is-mobile', isPhone);
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', setMobileClass);
} else {
  setMobileClass();
}
window.addEventListener('resize', setMobileClass);

// Toggle dropdown la click
document.addEventListener('DOMContentLoaded', function() {
  const dropdownToggle = document.querySelector('.dropdown .nav-link');
  const dropdown = document.querySelector('.dropdown');
  const dropdownMenu = document.querySelector('.dropdown-menu');
  
  if (dropdownToggle && dropdown && dropdownMenu) {
    dropdownToggle.addEventListener('click', function(e) {
      e.preventDefault();
      e.stopPropagation();
      dropdown.classList.toggle('active');
    });
    
    // Nu închide dropdown când dai click pe linkurile din el
    dropdownMenu.addEventListener('click', function(e) {
      // Permite navigarea - nu preveni default
      e.stopPropagation();
    });
    
    // Închide dropdown când dai click în afara lui
    document.addEventListener('click', function(e) {
      if (!dropdown.contains(e.target)) {
        dropdown.classList.remove('active');
      }
    });
  }
});
