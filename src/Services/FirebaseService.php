<?php

namespace App\Services;

use Firebase\JWT\JWT;
use GuzzleHttp\Client;
use Exception;

/**
 * Serviço para envio de notificações Firebase (API HTTP v1)
 * Compatível com PHP 7.1.1
 */
class FirebaseService
{
    private $projectId;
    private $credentialsPath;
    private $httpClient;
    private $cachedToken;
    private $tokenExpiry;

    public function __construct()
    {
        $this->credentialsPath = getenv('GOOGLE_CREDENTIALS_PATH') ?: __DIR__ . '/../../firebase-credentials.json';

        if (file_exists($this->credentialsPath)) {
            $data = json_decode(file_get_contents($this->credentialsPath), true);
            $this->projectId = isset($data['project_id']) ? $data['project_id'] : (getenv('FIREBASE_PROJECT_ID') ?: '');
        } else {
            $this->projectId = getenv('FIREBASE_PROJECT_ID') ?: '';
        }

        $this->httpClient = new Client([
            'timeout' => 10,
            'verify' => false
        ]);
        $this->cachedToken = null;
        $this->tokenExpiry = 0;
    }

    /**
     * Obtém token de acesso do Google (com cache)
     */
    public function getGoogleAccessToken()
    {
        $now = time();
        if ($this->cachedToken && $now < $this->tokenExpiry) {
            return $this->cachedToken;
        }

        try {
            if (!file_exists($this->credentialsPath)) {
                error_log("Firebase credentials file not found: " . $this->credentialsPath);
                return null;
            }

            $credentialsData = json_decode(file_get_contents($this->credentialsPath), true);
            if (!$credentialsData || !isset($credentialsData['private_key']) || !isset($credentialsData['client_email'])) {
                error_log("Erro ao ler arquivo de credenciais ou campos faltando");
                return null;
            }

            $now = time();
            $jwtPayload = [
                'iss' => $credentialsData['client_email'],
                'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
                'aud' => 'https://oauth2.googleapis.com/token',
                'exp' => $now + 3600,
                'iat' => $now
            ];
            
            $jwt = JWT::encode($jwtPayload, $credentialsData['private_key'], 'RS256');
            
            // Trocar JWT por access token usando cURL
            $tokenUrl = 'https://oauth2.googleapis.com/token';
            $postData = http_build_query([
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt
            ]);
            
            $ch = curl_init($tokenUrl);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $postData,
                CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
                CURLOPT_TIMEOUT => 10,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);
            
            if ($curlError) {
                error_log("cURL error ao obter token: " . $curlError);
                return null;
            }
            
            if ($httpCode === 200 && $response) {
                $tokenData = json_decode($response, true);
                if (isset($tokenData['access_token'])) {
                    $this->cachedToken = $tokenData['access_token'];
                    $this->tokenExpiry = $now + (50 * 60);
                    return $this->cachedToken;
                }
            } else {
                error_log("Erro ao obter token. HTTP {$httpCode}: " . substr($response, 0, 200));
            }
            
            return null;
        } catch (Exception $e) {
            error_log("Worker: Erro ao gerar token do Google: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Envia notificação Firebase
     */
    public function sendNotification($accessToken, $fcmToken, $pedidoId, $tipo)
    {
        $url = "https://fcm.googleapis.com/v1/projects/{$this->projectId}/messages:send";
        
        $title = '';
        $body = '';
        
        $tipo = strtoupper($tipo);

        if ($tipo === 'E') {
            $title = "Seu pedido #{$pedidoId} saiu para entrega!";
            $body = "Oba! Seu pedido está a caminho e chegará em breve. 🛵";
        } else if ($tipo === 'R') {
            $title = "Seu pedido #{$pedidoId} está pronto para retirada!";
            $body = "Pode vir buscar seu pedido quando quiser. 📦";
        } else {
            return ['success' => false, 'error' => "Tipo de pedido inválido: $tipo"];
        }

        $messagePayload = [
            'message' => [
                'token' => $fcmToken,
                'notification' => [
                    'title' => $title,
                    'body' => $body
                ],
                'webpush' => [
                    'headers' => [
                        'Urgency' => 'high'
                    ],
                    'notification' => [
                        'icon' => 'https://agapesi.ddns.com.br/deliveryNovo/assets/daffari.png',
                        'badge' => 'https://agapesi.ddns.com.br/deliveryNovo/assets/daffari-badge.png',
                        'requireInteraction' => true // Obriga o usuário a clicar ou fechar
                    ],
                    'fcm_options' => [
                        'link' => "https://agapesi.ddns.com.br/deliveryNovo/summary?codvenda={$pedidoId}"
                    ]
                ]
            ]
        ];

        try {
            $response = $this->httpClient->post($url, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Content-Type' => 'application/json'
                ],
                'json' => $messagePayload,
                'verify' => false
            ]);
            return ['success' => true, 'error' => null];

        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $response = $e->getResponse();
            $body = $response ? json_decode($response->getBody()->getContents(), true) : null;
            
            $googleMsg = isset($body['error']['message']) ? $body['error']['message'] : '';
            $finalMsg = $googleMsg ?: $e->getMessage();
            
            return ['success' => false, 'error' => $finalMsg];
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}