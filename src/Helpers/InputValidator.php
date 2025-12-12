<?php

namespace App\Helpers;

/**
 * Classe helper para validação e sanitização de inputs
 * Compatível com PHP 7.1.1
 */
class InputValidator
{
    /**
     * Sanitiza string para uso seguro
     */
    public static function sanitizeString($value, $maxLength = null)
    {
        if (!is_string($value)) {
            return '';
        }
        
        $value = trim($value);
        $value = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        
        if ($maxLength !== null && mb_strlen($value) > $maxLength) {
            $value = mb_substr($value, 0, $maxLength);
        }
        
        return $value;
    }

    /**
     * Valida e sanitiza email
     */
    public static function validateEmail($email)
    {
        $email = filter_var(trim($email), FILTER_SANITIZE_EMAIL);
        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : false;
    }

    /**
     * Valida e sanitiza URL
     */
    public static function validateUrl($url)
    {
        $url = filter_var(trim($url), FILTER_SANITIZE_URL);
        return filter_var($url, FILTER_VALIDATE_URL) ? $url : false;
    }

    /**
     * Valida FCM token
     * Tokens FCM podem ter formatos variados, então validamos de forma mais flexível
     */
    public static function validateFcmToken($token)
    {
        if (!is_string($token)) {
            return false;
        }
        
        $token = trim($token);
        
        // Tokens FCM geralmente têm pelo menos 50 caracteres
        // Mas podem ser menores em alguns casos, então reduzimos o mínimo
        if (strlen($token) < 20 || strlen($token) > 500) {
            return false;
        }
        
        // Tokens FCM podem conter: letras, números, hífens, underscores, dois pontos, pontos
        // Exemplos de formatos válidos:
        // - Tokens simples: abc123...
        // - Tokens com dois pontos: abc:def:123...
        // - Tokens com pontos: abc.def.123...
        if (!preg_match('/^[A-Za-z0-9_:\-\.]+$/', $token)) {
            return false;
        }
        
        return $token;
    }
}

