<?php
require __DIR__ . '/studenti/config/database.php';
$pdo = db();
$hash = '$2y$10$bvl05UOVohVxwEv98ExzJOHrs6sRQo/52wEjrm2g6dPVUiaq1vIou';
$pdo->prepare("UPDATE users SET password_hash=? WHERE username='admin'")->execute([$hash]);
echo "Admin password reset to admin123\n";
