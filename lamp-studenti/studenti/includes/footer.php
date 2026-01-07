<?php
$footerRole = $currentUserRole ?? ($_SESSION['user_role'] ?? null);
$showAdminLink = ($footerRole === 'admin');
?>
<footer class="site-footer">
  <div class="footer-grid">
    <div class="footer-brand">
      <a href="/index.php" class="logo" aria-label="Înapoi la Home">🎬 <span class="logo-movie">Movie</span><span class="logo-hub">Hub</span></a>
      <p>Descoperă, salvează și urmărește cele mai bune titluri. Rămânem aproape prin secțiunea de contact.</p>
    </div>
    <div>
      <h4>Contact</h4>
      <p>Ai o sugestie sau o problemă? Trimite-ne un mesaj.</p>
      <a class="footer-btn" href="/contact.php">Trimite mesaj</a>
      <p class="footer-meta">Mesajele sunt salvate în Contact DB.</p>
    </div>
    <div>
      <h4>Linkuri rapide</h4>
      <ul class="footer-links">
        <li><a href="/index.php">Acasă</a></li>
        <li><a href="/watchlist.php">Watchlist</a></li>
        <li><a href="/top-rated.php">Top Rated</a></li>
        <?php if ($showAdminLink): ?>
        <li><a href="/admin.php">Admin</a></li>
        <?php endif; ?>
      </ul>
    </div>
    <div class="footer-social-wrap">
      <h4>Urmărește-ne</h4>
      <ul class="footer-social">
        <li>
          <a href="https://www.facebook.com" aria-label="Facebook" target="_blank" rel="noopener noreferrer">
            <svg aria-hidden="true" width="22" height="22" viewBox="0 0 24 24" fill="currentColor">
              <path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z" />
            </svg>
          </a>
        </li>
        <li>
          <a href="https://www.instagram.com" aria-label="Instagram" target="_blank" rel="noopener noreferrer">
            <svg aria-hidden="true" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <rect x="3" y="3" width="18" height="18" rx="5" ry="5" />
              <circle cx="12" cy="12" r="4" />
              <circle cx="17" cy="7" r="1" />
            </svg>
          </a>
        </li>
        <li>
          <a href="https://www.tiktok.com" aria-label="TikTok" target="_blank" rel="noopener noreferrer">
            <svg aria-hidden="true" width="22" height="22" viewBox="0 0 24 24" fill="currentColor">
              <path d="M15 3.5c.5 1.1 1.6 2.3 3.3 2.3V9c-1.5-.1-2.7-.6-3.8-1.4V13c0 3-2.4 5-5 5a5 5 0 0 1 0-10c.3 0 .7 0 1 .1V10a2.6 2.6 0 0 0-1-.1 2 2 0 1 0 2 2V3.5h3.5Z" />
            </svg>
          </a>
        </li>
        <li>
          <a href="https://www.youtube.com" aria-label="YouTube" target="_blank" rel="noopener noreferrer">
            <svg aria-hidden="true" width="22" height="22" viewBox="0 0 24 24" fill="currentColor">
              <path d="M21.6 7.2c-.2-.8-.8-1.4-1.6-1.6C18.2 5 12 5 12 5s-6.2 0-8 .6c-.8.2-1.4.8-1.6 1.6C2 9 2 12 2 12s0 3 .4 4.8c.2.8.8 1.4 1.6 1.6C5.8 19 12 19 12 19s6.2 0 8-.6c.8-.2 1.4-.8 1.6-1.6C22 15 22 12 22 12s0-3-.4-4.8Z" />
              <path d="M10 15.5 15.2 12 10 8.5Z" fill="#0b0f14" />
            </svg>
          </a>
        </li>
      </ul>
    </div>
  </div>
  <div class="footer-bottom">© <?php echo date('Y'); ?> MovieHub · Built for studenți</div>
</footer>
