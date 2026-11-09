<?php
/**
 * Built-in PHP server router for WordPress local preview.
 *
 * Serves existing static files directly and routes all dynamic paths through index.php.
 */

$root = $_SERVER['DOCUMENT_ROOT'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

if ($path !== '/' && file_exists($root . $path) && !is_dir($root . $path)) {
    // Serve static assets (CSS, JS, images, audio, video, PDFs, webmanifest) directly.
    $ext = strtolower(pathinfo($root . $path, PATHINFO_EXTENSION));
    $mime_map = array(
        'webmanifest' => 'application/manifest+json',
        'mp3'         => 'audio/mpeg',
        'wav'         => 'audio/wav',
        'm4a'         => 'audio/mp4',
        'ogg'         => 'audio/ogg',
        'mp4'         => 'video/mp4',
        'webm'        => 'video/webm',
        'mov'         => 'video/quicktime',
        'pdf'         => 'application/pdf',
    );
    if (isset($mime_map[$ext])) {
        header('Content-Type: ' . $mime_map[$ext]);
        header('Content-Length: ' . filesize($root . $path));
        header('Accept-Ranges: bytes');
        readfile($root . $path);
        return true;
    }
    return false;
}

if (is_dir($root . $path) && file_exists(rtrim($root . $path, '/') . '/index.php')) {
    $_SERVER['SCRIPT_NAME'] = rtrim($path, '/') . '/index.php';
    require rtrim($root . $path, '/') . '/index.php';
    return true;
}

$_SERVER['SCRIPT_NAME'] = '/index.php';
require $root . '/index.php';
