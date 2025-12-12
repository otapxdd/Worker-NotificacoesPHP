<?php

/**
 * Worker de notificações Firebase - VERSÃO 24/7 OTIMIZADA
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('memory_limit', '512M'); 
date_default_timezone_set('America/Sao_Paulo');

require_once __DIR__ . '/vendor/autoload.php';

try {
    $dotenv = new Dotenv\Dotenv(__DIR__);
    $dotenv->safeLoad();
} catch (Exception $e) {}

use App\Config\Database;
use App\Services\FirebaseService;

define('INTERVALO_MS', 5000);
define('LOG_FILE', __DIR__ . '/debug_log.txt');
define('MAX_CICLOS', 1000); 
define('MAX_MEMORIA_MB', 128);

function debugLog($msg) {
    $ts = date('Y-m-d H:i:s');
    if (file_exists(LOG_FILE) && filesize(LOG_FILE) > 5 * 1024 * 1024) {
        file_put_contents(LOG_FILE, "[$ts] Log rotacionado/limpo autom.\n");
    }
    
    $content = "[$ts] $msg\n";
    file_put_contents(LOG_FILE, $content, FILE_APPEND);
}

function sleepMs($ms) {
    usleep($ms * 1000);
}

function checkMemoryAndCycles($cicloAtual) {
    $memUsage = memory_get_usage(true) / 1024 / 1024;
    
    if ($memUsage > MAX_MEMORIA_MB) {
        debugLog("!!! REINICIANDO: Limite de memória atingido ({$memUsage}MB) !!!");
        exit(0);
    }
    
    if ($cicloAtual >= MAX_CICLOS) {
        debugLog("--- REINICIANDO: Ciclo de vida máximo atingido ---");
        exit(0);
    }
}

function startNotificationWorker() {
    debugLog("=== WORKER 24/7 INICIADO ===");
    
    $dbHost = getenv('DB_HOST') ?: '127.0.0.1';
    $dbUser = getenv('DB_USER') ?: 'root';
    $dbPassword = getenv('DB_PASSWORD') ?: '';
    
    $firebaseService = new FirebaseService();
    
    $cachedToken = null;
    $tokenExpiry = 0;

    $ciclo = 0;
    
    while (true) {
        $ciclo++;
        
        try {
            if (!$cachedToken || time() >= $tokenExpiry) {
                $cachedToken = $firebaseService->getGoogleAccessToken();
                $tokenExpiry = time() + (55 * 60); 
                debugLog("Token Google renovado.");
            }

            if (!$cachedToken) {
                debugLog("FALHA CRÍTICA: Sem token do Google. Tentando novamente em breve.");
                $tokenExpiry = 0;
                sleepMs(INTERVALO_MS * 2);
                continue;
            }

            $masterDb = Database::getInstance($dbHost, $dbUser, $dbPassword);
            $databases = $masterDb->fetchAll("SHOW DATABASES LIKE 'delivery_%'");
            
            $masterDb = null; 

            if (empty($databases)) {
                sleepMs(INTERVALO_MS * 5);
                continue;
            }
            
            foreach ($databases as $dbRow) {
                $dbName = reset($dbRow);
                $db = null;

                try {
                    $db = new Database($dbHost, $dbUser, $dbPassword, $dbName);
                    $pedidos = $db->fetchAll("
                        SELECT v.Codigo, v.tipo, u.fcm_token
                        FROM vendas v 
                        INNER JOIN usuarios.usuarios u ON v.id_usuario = u.id
                        WHERE v.pedidoEnviado IS NOT NULL 
                        AND v.notificacao_enviada IS NULL
                        AND u.fcm_token IS NOT NULL 
                        AND u.fcm_token != ''
                        LIMIT 5
                        FOR UPDATE
                    ");
                    
                    if (!empty($pedidos)) {
                        debugLog("[$dbName] Processando " . count($pedidos) . " envio(s).");
                        $db->beginTransaction();
                        
                        foreach ($pedidos as $pedido) {
                            $result = $firebaseService->sendNotification(
                                $cachedToken, // Usa o token em cache
                                $pedido['fcm_token'],
                                $pedido['Codigo'],
                                $pedido['tipo']
                            );
                            
                            // Lógica de sucesso/erro mantida...
                            if ($result['success']) {
                                $db->query("UPDATE vendas SET notificacao_enviada = NOW() WHERE Codigo = ?", [$pedido['Codigo']]);
                                debugLog(" -> Pedido #{$pedido['Codigo']} ENVIADO.");
                            } else {
                                $erro = $result['error'] ?? 'Erro desconhecido';
                                debugLog(" -> Pedido #{$pedido['Codigo']} ERRO: $erro");
                                
                                if (strpos($erro, 'not found') !== false || 
                                    strpos($erro, 'UNREGISTERED') !== false ||
                                    strpos($erro, 'invalid authentication') !== false) {
                                    $db->query("UPDATE vendas SET notificacao_enviada = NOW() WHERE Codigo = ?", [$pedido['Codigo']]);
                                }
                            }
                        }
                        $db->commit();
                    }
                    
                } catch (Exception $e) {
                    try { if ($db) $db->rollback(); } catch (Exception $x) {}
                }
                
                $db = null;
                unset($pedidos);
            }

            gc_collect_cycles();
            
            checkMemoryAndCycles($ciclo);

        } catch (Exception $e) {
            debugLog("Erro Fatal no Ciclo: " . $e->getMessage());
            $db = null;
            $masterDb = null;
        }
        
        sleepMs(INTERVALO_MS);
    }
}

startNotificationWorker();