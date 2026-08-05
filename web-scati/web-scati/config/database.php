<?php
/**
 * Web SCATI - Configuração de conexão com o banco de dados
 *
 * Ajuste as constantes abaixo conforme o ambiente de instalação.
 */

declare(strict_types=1);

// ---------------------------------------------------------------------
// Dados de acesso ao MySQL - ALTERE conforme seu ambiente
// ---------------------------------------------------------------------
define('DB_HOST', 'localhost');
define('DB_NAME', 'scati');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// ---------------------------------------------------------------------
// URL base da aplicação - ALTERE para o caminho onde o sistema
// está hospedado (sem barra no final). Ex: http://localhost/web-scati
// ---------------------------------------------------------------------
define('BASE_URL', '/web-scati');

/**
 * Retorna uma conexão PDO única (padrão Singleton simples).
 */
function db(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            die('Erro ao conectar ao banco de dados: ' . htmlspecialchars($e->getMessage()));
        }
    }

    return $pdo;
}
