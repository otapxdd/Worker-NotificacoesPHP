<?php

namespace App\Security;

use Exception;

/**
 * Classe para autenticação JWT/OAuth2
 * Compatível com PHP 7.1.1
 */
class JwtAuth
{
    private $audience;
    private $issuerBaseUrl;

    public function __construct()
    {
        $audience = getenv('AUTH0_AUDIENCE');
        $issuer = getenv('AUTH0_ISSUER_BASE_URL');
        
        $this->audience = $audience !== false ? $audience : 'https://api-delivery.com';
        $this->issuerBaseUrl = $issuer !== false ? $issuer : 'https://dev-bj0bt4o68ttf3egu.us.auth0.com/';
    }

    /**
     * Valida token via Auth0 userinfo
     */
    public function validateTokenViaUserInfo($token)
    {
        $ch = curl_init(rtrim($this->issuerBaseUrl, '/') . '/userinfo');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . trim($token),
                'Content-Type: application/json'
            ],
            CURLOPT_TIMEOUT => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError || $httpCode !== 200 || !$response) {
            if ($curlError) {
                error_log("cURL error validating token: " . $curlError);
            }
            return null;
        }

        $data = json_decode($response, true);
        return (json_last_error() === JSON_ERROR_NONE) ? $data : null;
    }
}

