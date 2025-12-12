<?php

/**
 * API REST Principal
 * Compatível com PHP 7.1.1
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/php_errors.log');

date_default_timezone_set('America/Sao_Paulo');

require_once __DIR__ . '/vendor/autoload.php';

try {
    $dotenv = new Dotenv\Dotenv(__DIR__);
    $dotenv->safeLoad();
} catch (Exception $e) {
    error_log("Warning: .env file not found, using defaults");
}

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

$origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '*';
header("Access-Control-Allow-Origin: {$origin}");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Credentials: true");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit(0);
}

$requestUri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
$path = parse_url($requestUri, PHP_URL_PATH);
$path = rtrim($path, '/');

try {
    if ($path === '/api/users/sync' || $path === '/index.php/api/users/sync') {
        $controller = new \App\Controllers\UserController();
        $controller->sync();
        exit;
    }

    http_response_code(404);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Not Found']);
    
} catch (Exception $e) {
    error_log("API Error: " . $e->getMessage());
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Internal Server Error']);
}

