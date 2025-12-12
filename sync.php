<?php

/**
 * Endpoint de sincronização de usuário
 * Compatível com PHP 7.1.1
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/php_errors.log');

date_default_timezone_set('America/Sao_Paulo');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    header('Content-Type: application/json');
    http_response_code(200);
    echo json_encode([
        'status' => 'ok',
        'message' => 'Endpoint de sincronização ativo',
        'method' => 'Este endpoint aceita apenas requisições POST'
    ]);
    exit;
}

$allowedOrigins = [
    'http://localhost',
    'https://agapesi.ddns.com.br',
];

$origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '*';
if (in_array($origin, $allowedOrigins) || $origin === '*') {
    header("Access-Control-Allow-Origin: {$origin}");
}

header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Credentials: false");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/vendor/autoload.php';

define('SSL_CERT_PATH', __DIR__ . '/certs/arquivo-ca.pem');

if (!file_exists(SSL_CERT_PATH)) {
    error_log("Aviso: Certificado SSL não encontrado em: " . SSL_CERT_PATH);
}

try {
    $dotenv = new Dotenv\Dotenv(__DIR__);
    $dotenv->safeLoad();
} catch (Exception $e) {
    error_log("Warning: .env file not found, using defaults");
}

try {
    (new \App\Controllers\UserController())->sync();
} catch (Exception $e) {
    error_log("Sync endpoint error: " . $e->getMessage());
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(["error" => "Falha na sincronização"]);
}