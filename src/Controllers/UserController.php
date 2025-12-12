<?php

namespace App\Controllers;

use App\Config\Database;
use App\Security\JwtAuth;
use App\Helpers\InputValidator;
use Exception;

/**
 * Controller para rotas de usuário
 * Compatível com PHP 7.1.1
 */
class UserController
{
    private $db;
    private $jwtAuth;

    public function __construct()
    {
        $dbHost = getenv('DB_HOST');
        $dbUser = getenv('DB_USER');
        $dbPassword = getenv('DB_PASSWORD');
        
        $this->db = Database::getInstance(
            $dbHost !== false ? $dbHost : '192.168.1.122',
            $dbUser !== false ? $dbUser : 'root',
            $dbPassword !== false ? $dbPassword : '',
            'usuarios'
        );
        $this->jwtAuth = new JwtAuth();
    }

    /**
     * Sincroniza dados do usuário e FCM token
     */
    public function sync()
    {
        try {
            // Validar método HTTP
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                $this->sendResponse(405, ['error' => 'Method not allowed']);
                return;
            }

            // Obter token JWT do header Authorization
            $authHeader = '';
            if (function_exists('getallheaders')) {
                $headers = getallheaders();
                $authHeader = isset($headers['Authorization']) ? $headers['Authorization'] : (isset($headers['authorization']) ? $headers['authorization'] : '');
            }
            if (empty($authHeader) && isset($_SERVER['HTTP_AUTHORIZATION'])) {
                $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
            } else if (empty($authHeader) && isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
                $authHeader = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
            } else if (empty($authHeader) && function_exists('apache_request_headers')) {
                $headers = apache_request_headers();
                $authHeader = isset($headers['Authorization']) ? $headers['Authorization'] : (isset($headers['authorization']) ? $headers['authorization'] : '');
            }
            
            if (empty($authHeader) || !preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
                $this->sendResponse(401, ['error' => 'Unauthorized - Missing token']);
                return;
            }

            $userInfo = $this->jwtAuth->validateTokenViaUserInfo(trim($matches[1]));
            if (!$userInfo) {
                $this->sendResponse(401, ['error' => 'Unauthorized - Invalid token']);
                return;
            }

            // Obter dados do body
            $rawInput = file_get_contents('php://input');
            $input = !empty($rawInput) ? json_decode($rawInput, true) : [];
            if ($input === null && !empty($rawInput)) {
                $this->sendResponse(400, ['error' => 'Invalid JSON']);
                return;
            }
            $fcmToken = isset($input['fcmToken']) ? trim($input['fcmToken']) : null;
            
            // Validar e sanitizar dados
            $email = InputValidator::validateEmail(isset($userInfo['email']) ? $userInfo['email'] : '');
            if (!$email) {
                $this->sendResponse(400, ['error' => 'Invalid email']);
                return;
            }
            
            $name = InputValidator::sanitizeString(isset($userInfo['name']) ? $userInfo['name'] : '', 255);
            $picture = InputValidator::validateUrl(isset($userInfo['picture']) ? $userInfo['picture'] : '') ?: '';
            $sub = InputValidator::sanitizeString(isset($userInfo['sub']) ? $userInfo['sub'] : '', 255);
            
            if (empty($sub)) {
                $this->sendResponse(400, ['error' => 'Invalid user identifier']);
                return;
            }

            // Verificar se usuário existe
            $user = $this->db->fetchOne(
                "SELECT auth0_sub, numero, numeroVerificado FROM usuarios WHERE email = ?",
                [$email]
            );

            if ($user) {
                // Atualizar usuário existente
                $this->db->query(
                    "UPDATE usuarios SET nome = ?, foto = ?, auth0_sub = ?, dataAtualizada = NOW() WHERE email = ?",
                    [$name, $picture, $sub, $email]
                );
            } else {
                // Criar novo usuário
                $this->db->query(
                    "INSERT INTO usuarios (auth0_sub, email, nome, foto, dataCriada) VALUES (?, ?, ?, ?, NOW())",
                    [$sub, $email, $name, $picture]
                );
            }

            // Atualizar FCM token se fornecido
            if ($fcmToken) {
                $validatedToken = InputValidator::validateFcmToken($fcmToken);
                if (!$validatedToken) {
                    $this->sendResponse(400, ['error' => 'Invalid FCM token format']);
                    return;
                }
                $this->db->query("UPDATE usuarios SET fcm_token = ? WHERE email = ?", [$validatedToken, $email]);
                $this->sendResponse(200, ['status' => 'success', 'message' => 'Token FCM atualizado.']);
                return;
            }

            // Retornar dados do usuário
            $userData = $this->db->fetchOne(
                "SELECT numero, numeroVerificado FROM usuarios WHERE email = ?",
                [$email]
            );

            $this->sendResponse(200, ['status' => 'success', 'user' => $userData]);

        } catch (Exception $e) {
            error_log("UserController sync error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
            $this->sendResponse(500, ['error' => 'Falha na sincronização']);
        }
    }

    /**
     * Envia resposta HTTP
     */
    private function sendResponse($statusCode, $data)
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        
        // CORS headers
        $origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '*';
        header("Access-Control-Allow-Origin: {$origin}");
        header("Access-Control-Allow-Methods: POST, OPTIONS");
        header("Access-Control-Allow-Headers: Content-Type, Authorization");
        header("Access-Control-Allow-Credentials: true");

        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            exit(0);
        }

        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}

