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

// ---------------------------------------------------------------------
// Chave de criptografia - usada só para cifrar a senha de email
// corporativo salva no cadastro de Usuários (não afeta a senha de login,
// que continua em hash bcrypt, irreversível). RECOMENDADO trocar por uma
// chave própria antes de colocar o sistema em uso — gere uma nova com:
//   php -r "echo bin2hex(random_bytes(32));"
// Atenção: trocar a chave depois de já ter senhas de email salvas torna
// essas senhas antigas ilegíveis (é preciso recadastrá-las).
// ---------------------------------------------------------------------
define('ENCRYPTION_KEY', hex2bin('79a5a63ff45630192437fbd0fafc62bba7de1632871773b1c086fe1f7d540c6f'));

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
