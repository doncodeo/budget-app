<?php
require __DIR__ . '/../src/config.php';
require __DIR__ . '/../src/db.php';
require __DIR__ . '/../src/auth.php';
require __DIR__ . '/../src/helpers.php';
require __DIR__ . '/../src/render.php';

$user = require_login();

render_template('about.twig', [
    'activePage' => 'about',
    'pageTitle' => 'About & Platform Tutorial',
]);
