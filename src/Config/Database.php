<?php

namespace App\Config;

use PDO;
use PDOException;

/**
 * Classe de conexão com banco de dados
 * Segurança: Prepared statements, tratamento de erros
 */
class Database
{
    private static $instances = [];
    private $connection;
    private $host;
    private $user;
    private $password;
    private $database;

    public function __construct($host, $user, $password, $database = null)
    {
        $this->host = $host;
        $this->user = $user;
        $this->password = $password;
        $this->database = $database;
        $this->connect();
    }

    /**
     * Obtém instância singleton
     */
    public static function getInstance($host = null, $user = null, $password = null, $database = null)
    {
        $key = md5($host . $user . ($database ?? ''));
        
        if (!isset(self::$instances[$key])) {
            self::$instances[$key] = new self($host, $user, $password, $database);
        }
        
        return self::$instances[$key];
    }

    /**
     * Conecta ao banco de dados
     */
    private function connect()
    {
        try {
            // Usar utf8 para compatibilidade com MySQL antigo
            $dsn = "mysql:host={$this->host};charset=utf8";
            if ($this->database) {
                $dsn .= ";dbname={$this->database}";
            }
            
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8 COLLATE utf8_general_ci",
                PDO::ATTR_PERSISTENT => false,
                PDO::ATTR_TIMEOUT => 10
            ];

            // --- ADICIONADO: Configuração SSL ---
            // Verifica se a constante foi definida no arquivo principal e se o arquivo existe
            if (defined('SSL_CERT_PATH') && file_exists(SSL_CERT_PATH)) {
                $options[PDO::MYSQL_ATTR_SSL_CA] = SSL_CERT_PATH;
                // Opcional: Descomente a linha abaixo se der erro de verificação de nome (comum em IPs diretos)
                // $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
            }
            // ------------------------------------
            
            $this->connection = new PDO($dsn, $this->user, $this->password, $options);
            
        } catch (PDOException $e) {
            $errorMsg = "Database connection error: " . $e->getMessage();
            // Cuidado: Logar DSN pode expor credenciais se não sanitizado, mas aqui user/pass estão fora
            $errorMsg .= " | User: {$this->user}";
            
            // Adiciona info sobre SSL no log de erro para facilitar debug
            if (defined('SSL_CERT_PATH')) {
                 $errorMsg .= " | SSL Path: " . SSL_CERT_PATH;
            }

            error_log($errorMsg);
            throw new \RuntimeException("Database connection failed: " . $e->getMessage(), 0, $e);
        }
    }
    /**
     * Obtém a conexão PDO
     */
    public function getConnection()
    {
        if ($this->connection === null) {
            $this->connect();
        }
        return $this->connection;
    }

    /**
     * Executa query com prepared statement
     */
    public function query($sql, $params = [])
    {
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            error_log("Database query error: " . $e->getMessage());
            throw new \RuntimeException("Query execution failed", 0, $e);
        }
    }

    /**
     * Executa query e retorna todos os resultados
     */
    public function fetchAll($sql, $params = [])
    {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }

    /**
     * Executa query e retorna um resultado
     */
    public function fetchOne($sql, $params = [])
    {
        $stmt = $this->query($sql, $params);
        return $stmt->fetch();
    }

    /**
     * Inicia transação
     */
    public function beginTransaction()
    {
        return $this->connection->beginTransaction();
    }

    /**
     * Confirma transação
     */
    public function commit()
    {
        return $this->connection->commit();
    }

    /**
     * Reverte transação
     */
    public function rollback()
    {
        return $this->connection->rollBack();
    }

    private function __clone() {}
    
    public function __wakeup()
    {
        throw new \Exception("Cannot unserialize singleton");
    }
}

