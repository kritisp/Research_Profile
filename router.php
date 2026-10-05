<?php
/**
 * Built-in PHP CLI Web Server Router
 * Emulates Apache mod_rewrite for local testing and CI runners.
 */
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Clean researcher profile URL: /researchers/{slug} -> profile.php?slug={slug}
if (preg_match('#^/(?:research_profile/)?researchers/([a-zA-Z0-9_\-]+)/?$#', $uri, $matches)) {
    $_GET['slug'] = $matches[1];
    require __DIR__ . '/profile.php';
    return true;
}

// Serve existing static or PHP files directly
if ($uri !== '/' && file_exists(__DIR__ . $uri)) {
    return false;
}

// Fallback to index.php
require __DIR__ . '/index.php';
