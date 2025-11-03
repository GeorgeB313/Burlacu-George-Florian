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

  // ---------- UI ----------
  const detailsModal = document.getElementById('movieDetailsModal');
  const closeDetailsBtn = document.getElementById('closeDetailsBtn');
  const watchlistBtn = document.getElementById('detailsWatchlistBtn');

  function setText(id, val) { const el = document.getElementById(id); if (el) el.textContent = val; }
  function showLoading() {
    setText('detailsTitle', 'Se încarcă...');
    setText('detailsDescription', 'Așteptați...');
    setText('aboutMovie', 'Așteptați...');
    setText('detailsYear', '-'); setText('detailsGenre', '-'); setText('detailsRuntime', '⏱ -'); setText('detailsRating', '-');
    setText('directorName', '-'); setText('actorsList', '-');
    setText('statRating', '-'); setText('statYear', '-'); setText('statGenre', '-'); setText('statRuntime', '-'); setText('statVotes', '-');
    const tc = document.getElementById('trailerContainer'); if (tc) tc.innerHTML = '<div class="loading-spinner"></div>';
  }
  function openModal() { if (!detailsModal) return; detailsModal.classList.add('active'); document.body.style.overflow = 'hidden'; }
  function closeModal() { if (!detailsModal) return; detailsModal.classList.remove('active'); document.body.style.overflow = 'auto'; const tc = document.getElementById('trailerContainer'); if (tc) tc.innerHTML = ''; }
  if (closeDetailsBtn) closeDetailsBtn.addEventListener('click', closeModal);
  if (detailsModal) detailsModal.addEventListener('click', e => { if (e.target === detailsModal) closeModal(); });
  document.addEventListener('keydown', e => { if (e.key === 'Escape' && detailsModal?.classList.contains('active')) closeModal(); });

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
  // Detalii cu fallback de limbă + includere video languages
  async function tmdbDetails(id, lang = 'ro-RO') {
    const url = new URL(`https://api.themoviedb.org/3/movie/${id}`);
    url.searchParams.set('api_key', TMDB_API_KEY);
    url.searchParams.set('append_to_response', 'credits,videos');
    url.searchParams.set('language', lang);
    url.searchParams.set('include_video_language', 'en,null,ro-RO');
    const r = await fetch(url.toString());
    if (!r.ok) throw new Error('TMDb details error');
    const d = await r.json();
    if (lang === 'ro-RO' && (!d.overview || d.overview.trim() === '' || !d.title)) {
      try {
        const en = await tmdbDetails(id, 'en-US');
        d.overview = d.overview || en.overview;
        d.title = d.title || en.title || en.original_title;
        d.genres = d.genres?.length ? d.genres : en.genres;
        d.runtime = d.runtime || en.runtime;
        d.vote_average = d.vote_average || en.vote_average;
        d.vote_count = d.vote_count || en.vote_count;
        d.credits = d.credits?.cast?.length ? d.credits : en.credits;
        d.videos = d.videos?.results?.length ? d.videos : en.videos;
      } catch {}
    }
    return d;
  }
  async function tmdbVideos(id, lang) {
    const url = new URL(`https://api.themoviedb.org/3/movie/${id}/videos`);
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
  async function resolveTrailerKey(title, year, tmdbId, card) {
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
      const v1 = await tmdbVideos(tmdbId, 'ro-RO');
      let key = pickTrailerKey(v1);
      if (key) return key;
      const v2 = await tmdbVideos(tmdbId, 'en-US');
      key = pickTrailerKey(v2);
      if (key) return key;
      const v3 = await tmdbVideos(tmdbId);
      key = pickTrailerKey(v3);
      if (key) return key;
    } catch (_) {}

    // 3) Fallback local/din card
    return card?.dataset?.trailerId || fallbackTrailerIds[title] || null;
  }

  function fmt(n) { try { return Number(n).toLocaleString('ro-RO'); } catch { return n; } }
  function preload(src) { return new Promise((res, rej) => { const i = new Image(); i.onload = () => res(src); i.onerror = rej; i.src = src; }); }

  // ---------- Postere pe Home ----------
  async function setCardPoster(card) {
    if (!TMDB_API_KEY || TMDB_API_KEY === 'YOUR_TMDB_KEY') return;
    const imdbId = card.dataset.imdb;
    const title = card.dataset.title || card.querySelector('h3')?.textContent?.trim();
    const year = (card.dataset.year || '').slice(0, 4);

    try {
      let tmdbObj = await tmdbFindByImdb(imdbId).catch(() => null);
      if (!tmdbObj) {
        const list = await tmdbSearch(title, year);
        tmdbObj = list
          .sort((a,b)=>(b.vote_count||0)-(a.vote_count||0))
          .find(r => (r.title||'').toLowerCase() === (title||'').toLowerCase() ||
                     (r.release_date||'').startsWith(year)) || list[0];
      }
      if (!tmdbObj) return;

      card.dataset.tmdb = tmdbObj.id;

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
  function hydrateCardPosters() { document.querySelectorAll('.film-card').forEach(setCardPoster); }

  // ---------- Click pe card => DETALII + TRAILER ----------
  document.querySelectorAll('.film-card').forEach(card => {
    card.addEventListener('click', async (e) => {
      if (e.target.closest?.('.watchlist-btn')) return;

      const title = card.dataset.title || card.querySelector('h3')?.textContent?.trim();
      const yearHint = (card.dataset.year || '').slice(0, 4);
      const posterEl = document.getElementById('detailsPoster');
      const posterTitle = document.getElementById('detailsPosterTitle');

      // pre-loader + fallback culoare
      showLoading();
      const color = card.dataset.color || card.querySelector('.poster')?.style.backgroundColor || '#0d1117';
      if (posterEl && posterTitle) {
        posterEl.style.background = color;
        posterEl.style.backgroundImage = 'none';
        posterTitle.textContent = title || '';
        posterTitle.style.display = 'block';
      }
      openModal();

      try {
        if (!TMDB_API_KEY || TMDB_API_KEY === 'YOUR_TMDB_KEY') throw new Error('Lipsește TMDb key');

        // găsește TMDb id (IMDb -> search)
        const imdbId = card.dataset.imdb;
        let tmdbId = card.dataset.tmdb || null;
        if (imdbId && !tmdbId) { const f = await tmdbFindByImdb(imdbId).catch(()=>null); if (f) tmdbId = f.id; }
        if (!tmdbId) {
          const results = await tmdbSearch(title, yearHint);
          const best = results
            .sort((a,b)=>(b.vote_count||0)-(a.vote_count||0))
            .find(r => (r.title||'').toLowerCase() === (title||'').toLowerCase() ||
                       (r.release_date||'').startsWith(yearHint)) || results[0];
          tmdbId = best?.id;
        }
        if (!tmdbId) throw new Error('TMDb search: no result');

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
        setText('detailsDescription', desc);
        setText('aboutMovie', desc);
        setText('directorName', directors.length ? directors.join(', ') : 'N/A');
        setText('actorsList', actors.length ? actors.join(', ') : 'N/A');
        setText('statRating', `${rating}/10`);
        setText('statYear', year);
        setText('statGenre', genres);
        setText('statRuntime', runtime);
        setText('statVotes', votes);

        // Etapa 1: YouTube -> Etapa 2: TMDb -> Fallback local
        const trailerKey = await resolveTrailerKey(d.title || title, year, tmdbId, card);
        const tc = document.getElementById('trailerContainer');
        if (tc) {
          tc.innerHTML = trailerKey
            ? `<iframe width="100%" height="100%" src="https://www.youtube.com/embed/${trailerKey}?autoplay=0" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>`
            : '<p style="text-align:center; color:#c9d1d9; padding:50px;">Trailer indisponibil</p>';
        }

        // memo
        card.dataset.tmdb = tmdbId;
        detailsModal.dataset.currentMovie = d.title || title || '';
      } catch (err) {
        console.error('TMDb/Trailer error:', err);
        // fallback minimal: date din card
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
        setText('detailsDescription', desc);
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
      }
    });
  });

  // Watchlist (dacă ai endpoint)
  if (watchlistBtn) {
    watchlistBtn.addEventListener('click', async function () {
      const movieTitle = detailsModal?.dataset.currentMovie;
      if (!movieTitle) return;
      try {
        const res = await fetch('add_to_watchlist.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: `movie=${encodeURIComponent(movieTitle)}`
        });
        const json = await res.json();
        if (json.success) { this.innerHTML = '<span>✓ În Watchlist</span>'; this.style.background = '#6e7681'; this.disabled = true; }
        else alert(json.message || 'Eroare la adăugare');
      } catch { alert('Eroare de rețea'); }
    });
  }

  // Pornește încărcarea posterelor cardurilor
  hydrateCardPosters();

  // Buton "Mergi sus" – se asigură că există ancora și butonul
  (function initBackToTop() {
    // injectează stilul dacă lipsește
    if (!document.getElementById('backToTopStyles')) {
      const st = document.createElement('style');
      st.id = 'backToTopStyles';
      st.textContent = `
        #backToTop{position:fixed;right:20px;bottom:20px;width:44px;height:44px;border-radius:50%;
          display:grid;place-items:center;text-decoration:none;color:#fff;background:#1f6feb;border:1px solid #30363d;
          box-shadow:0 6px 18px rgba(0,0,0,.35);font-size:18px;z-index:9999;opacity:0;pointer-events:none;
          transition:opacity .2s, transform .2s;}
        #backToTop.show{opacity:1;pointer-events:auto;}
        #backToTop:hover{transform:translateY(-2px);}
        html{scroll-behavior:smooth;}
      `;
      document.head.appendChild(st);
    }

    // ancora #top
    let topAnchor = document.getElementById('top');
    if (!topAnchor) {
      topAnchor = document.createElement('div');
      topAnchor.id = 'top';
      document.body.prepend(topAnchor);
    }
    // butonul #backToTop
    let btn = document.getElementById('backToTop');
    if (!btn) {
      btn = document.createElement('a');
      btn.id = 'backToTop';
      btn.href = '#top';
      btn.setAttribute('aria-label', 'Mergi sus');
      btn.textContent = '⬆';
      document.body.appendChild(btn);
    }

    // prag mai mic (nu mai e minim 300px)
    const computeThreshold = () => Math.max(120, Math.floor(window.innerHeight * 0.15));
    let threshold = computeThreshold();

    const toggle = () => {
      threshold = computeThreshold();
      const y = window.pageYOffset || document.documentElement.scrollTop || 0;
      btn.classList.toggle('show', y > threshold);
    };

    window.addEventListener('scroll', toggle, { passive: true });
    window.addEventListener('resize', toggle, { passive: true });
    toggle();

    btn.addEventListener('click', (e) => {
      e.preventDefault();
      window.scrollTo({ top: 0, behavior: 'smooth' });
    });
  })();
});

// animație highlight (siguranță)
const style = document.createElement('style');
style.textContent = `
  @keyframes highlight { 0%,100%{transform:scale(1);box-shadow:0 8px 24px rgba(0,0,0,.3);} 50%{transform:scale(1.05);box-shadow:0 12px 32px rgba(229,185,10,.5);} }
`;
document.head.appendChild(style);

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
