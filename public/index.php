<?php
require __DIR__ . '/../src/config.php';
require __DIR__ . '/../src/db.php';
require __DIR__ . '/../src/auth.php';

header('Location: ' . (current_user() ? 'dashboard.php' : 'login.php'));
exit;
