<?php
require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/helpers.php';
require_once __DIR__ . '/../src/render.php';

$user = current_user();

render_template('about.twig', [
    'activePage' => 'about',
    'pageTitle' => 'About & Platform Tutorial',
]);
