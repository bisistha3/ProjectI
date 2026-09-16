<?php
/**
 * HealthFlow Landing Page
 * Public entry point — redirects logged-in users to dashboard
 */
require_once __DIR__ . '/includes/auth.php';

if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

require __DIR__ . '/index.html';