<?php
declare(strict_types=1);

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'GET'
    && !array_key_exists('HTTP_FORWARDED', $_SERVER)
    && !array_key_exists('HTTP_X_FORWARDED_FOR', $_SERVER)
    && !array_key_exists('HTTP_X_REAL_IP', $_SERVER)) {
    $_GET['local'] ??= '1';
}

require __DIR__ . '/dashboard.php';
