<?php

if (PHP_SAPI !== 'cli' && (getenv('APP_ENV') ?: 'production') === 'production') {
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
}

header('Content-Type: text/html; charset=UTF-8');

require_once __DIR__ . '/Core/View.php';
require_once __DIR__ . '/Models/EventsRepository.php';
require_once __DIR__ . '/Controllers/PageController.php';
require_once __DIR__ . '/Controllers/AdminController.php';
