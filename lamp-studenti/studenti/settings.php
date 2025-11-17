<?php
session_start();

define('REMEMBER_SECRET', 'NZcJe9lFUck5pNBEOhT2yM805nzRRyISKb195KMDHzt2hsg7h2');

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

$currentUser = $_SESSION['user'];
?>
<!DOCTYPE html>
<html lang="ro">
<head>
  <meta charset="UTF-8">
  <title>MovieHub - Setări Cont</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
  <div id="top"></div>

  <header class="main-header">
    <div class="logo">
      <img src="logo.jpeg" alt="MovieHub Logo">
    </div>
    <nav>
      <a href="index.php" class="nav-link nav-home">
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
      </div>
      <a href="settings.php" class="nav-link nav-settings active">
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"></path><circle cx="12" cy="12" r="3"></circle></svg>
      </a>
      <a href="logout.php" class="logout-btn">
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" x2="9" y1="12" y2="12"></line></svg>
      </a>
    </nav>
  </header>

  <main class="content settings-content">
    <h2 class="page-title">⚙️ Setări Cont</h2>
    
    <div class="settings-container">
      <!-- Secțiune profil -->
      <div class="settings-section">
        <div class="settings-header">
          <div class="user-avatar-large">
            <?php echo strtoupper(substr($currentUser, 0, 1)); ?>
          </div>
          <div class="user-info">
            <h3><?php echo htmlspecialchars($currentUser); ?></h3>
            <p class="user-status">👤 Utilizator activ</p>
          </div>
        </div>
      </div>

      <!-- Modificare Username -->
      <div class="settings-section">
        <h3 class="section-title">👤 Schimbă Username</h3>
        <form id="changeUsernameForm" class="settings-form">
          <div class="form-group">
            <label for="currentUsername">Username curent:</label>
            <input type="text" id="currentUsername" value="<?php echo htmlspecialchars($currentUser); ?>" disabled>
          </div>
          <div class="form-group">
            <label for="newUsername">Username nou:</label>
            <input type="text" id="newUsername" name="newUsername" placeholder="Introdu username-ul nou" required>
          </div>
          <button type="submit" class="settings-btn">Actualizează Username</button>
        </form>
      </div>

      <!-- Modificare Parolă -->
      <div class="settings-section">
        <h3 class="section-title">🔒 Schimbă Parola</h3>
        <form id="changePasswordForm" class="settings-form">
          <div class="form-group">
            <label for="currentPassword">Parola curentă:</label>
            <input type="password" id="currentPassword" name="currentPassword" placeholder="Introdu parola curentă" required>
          </div>
          <div class="form-group">
            <label for="newPassword">Parolă nouă:</label>
            <input type="password" id="newPassword" name="newPassword" placeholder="Introdu parola nouă" required>
          </div>
          <div class="form-group">
            <label for="confirmPassword">Confirmă parola nouă:</label>
            <input type="password" id="confirmPassword" name="confirmPassword" placeholder="Confirmă parola nouă" required>
          </div>
          <button type="submit" class="settings-btn">Actualizează Parola</button>
        </form>
      </div>

      <!-- Ștergere Cont -->
      <div class="settings-section danger-section">
        <h3 class="section-title">⚠️ Stergere Cont</h3>
        <p class="danger-text">Odată ce ștergi contul, nu există cale de întoarcere. Te rugăm să fii sigur.</p>
        <button id="deleteAccountBtn" class="settings-btn danger-btn">Șterge Contul</button>
      </div>
    </div>
  </main>

  <!-- Modal confirmare ștergere cont -->
  <div id="deleteModal" class="modal" style="display: none;">
    <div class="modal-content-small">
      <h3>⚠️ Confirmare Ștergere Cont</h3>
      <p>Ești sigur că vrei să ștergi contul? Această acțiune este <strong>ireversibilă</strong>!</p>
      <p>Toate datele tale (watchlist, review-uri) vor fi șterse permanent.</p>
      <div class="modal-actions">
        <button id="confirmDeleteBtn" class="settings-btn danger-btn">Da, Șterge Contul</button>
        <button id="cancelDeleteBtn" class="settings-btn">Anulează</button>
      </div>
    </div>
  </div>

  <a href="#top" id="goToTop" class="go-to-top">↑</a>

  <script>
    // Change Username
    document.getElementById('changeUsernameForm').addEventListener('submit', async (e) => {
      e.preventDefault();
      const newUsername = document.getElementById('newUsername').value.trim();
      
      if (!newUsername) {
        alert('Te rugăm să introduci un username nou!');
        return;
      }

      try {
        const response = await fetch('update_account.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ action: 'change_username', newUsername })
        });

        const result = await response.json();
        
        if (result.success) {
          alert('✅ Username actualizat cu succes!');
          setTimeout(() => location.reload(), 1000);
        } else {
          alert('❌ ' + (result.message || 'Eroare la actualizarea username-ului'));
        }
      } catch (err) {
        alert('❌ Eroare de conexiune!');
        console.error(err);
      }
    });

    // Change Password
    document.getElementById('changePasswordForm').addEventListener('submit', async (e) => {
      e.preventDefault();
      const currentPassword = document.getElementById('currentPassword').value;
      const newPassword = document.getElementById('newPassword').value;
      const confirmPassword = document.getElementById('confirmPassword').value;

      if (newPassword !== confirmPassword) {
        alert('❌ Parolele nu se potrivesc!');
        return;
      }

      if (newPassword.length < 6) {
        alert('❌ Parola trebuie să aibă minimum 6 caractere!');
        return;
      }

      try {
        const response = await fetch('update_account.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ 
            action: 'change_password', 
            currentPassword, 
            newPassword 
          })
        });

        const result = await response.json();
        
        if (result.success) {
          alert('✅ Parola actualizată cu succes!');
          document.getElementById('changePasswordForm').reset();
        } else {
          alert('❌ ' + (result.message || 'Eroare la actualizarea parolei'));
        }
      } catch (err) {
        alert('❌ Eroare de conexiune!');
        console.error(err);
      }
    });

    // Delete Account
    const deleteModal = document.getElementById('deleteModal');
    document.getElementById('deleteAccountBtn').addEventListener('click', () => {
      deleteModal.style.display = 'flex';
    });

    document.getElementById('cancelDeleteBtn').addEventListener('click', () => {
      deleteModal.style.display = 'none';
    });

    document.getElementById('confirmDeleteBtn').addEventListener('click', async () => {
      try {
        const response = await fetch('update_account.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ action: 'delete_account' })
        });

        const result = await response.json();
        
        if (result.success) {
          alert('✅ Contul a fost șters cu succes!');
          window.location.href = 'logout.php';
        } else {
          alert('❌ ' + (result.message || 'Eroare la ștergerea contului'));
        }
      } catch (err) {
        alert('❌ Eroare de conexiune!');
        console.error(err);
      }
    });

    // Go to top button
    const goToTopBtn = document.getElementById('goToTop');
    window.addEventListener('scroll', () => {
      if (window.scrollY > 300) {
        goToTopBtn.style.display = 'flex';
      } else {
        goToTopBtn.style.display = 'none';
      }
    });
  </script>
</body>
</html>
